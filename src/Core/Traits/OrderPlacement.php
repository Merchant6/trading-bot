<?php

namespace Merchant\TradingBot\Core\Traits;

use ccxt\async\Exchange;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\Futures\AccountBalance;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\Futures\PlaceOrder;
use Merchant\TradingBot\Core\Utils\ExchangeManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;
use React\Promise\Timer;
use Throwable;

use function React\Async\async;
use function React\Async\await;
use function React\Promise\Timer\sleep;
use React\Promise\all;

trait OrderPlacement
{
    private bool $isOrderInProgress = false;
    private ?TimerInterface $monitoringTimer = null;
    
    abstract public function execute();

    public function placeOrder(ExchangeManager $exchange): void
    {
        try {
            if ($this->isOrderInProgress) {
                return;
            }

            $this->isOrderInProgress = true;

            [$hasOpenOrders, $hasOpenPositions] = await($exchange->fetchOpenOrdersAndPositions($this->options['symbol']));
            if ($hasOpenOrders || $hasOpenPositions) {
                sleep(time: getenv('COOL_DOWN_PERIOD'))->then(fn() => $this->execute());
                return;
            }

            $userAccountBalance = await($exchange->fetchAccountBalance());
            if (!$userAccountBalance) {
                logger()->error("Insufficient account balance.");
                $this->isOrderInProgress = false;
                return;
            }

            /**
             * Calculate the amount to trade based on the user's account balance
             * Formula: (accountBalance * (amountPercentage / 100)) * leverage
             * e.g. (1000 * (5 / 100)) * 10 = 500
             */
            $this->options['amount'] = ($userAccountBalance * ($this->options['amountPercentage'] / 100)) * $this->options['leverage'];
            $this->openPosition($exchange);
            
        } catch (Throwable $e) {
            $this->isOrderInProgress = false;
            logger()->error($e->getMessage());
        }
    }

    public function startMonitoring(string $symbol, ExchangeManager $exchange): void
    {
        if ($this->monitoringTimer !== null) {
            return; // Avoid duplicate monitoring timers or concurrent orders
        }

        $this->monitoringTimer = Loop::addPeriodicTimer(0.5, async(function () use ($symbol, $exchange) {
            // Prevent further execution if position has been closed
            $positions = await($exchange->fetchOpenPositions($symbol));
            if (empty($positions)) {
                $this->stopMonitoring();
                logger()->info("No positons found for {$symbol}. Monitoring stopped.");
                return;
            }
        
            $profitPercentage = (float)array_column($positions, 'percentage')[0];
            $quantity = (float)array_column($positions, 'contracts')[0];
            $entryPrice = (float)array_column($positions, 'entryPrice')[0];

            // Take profit condition
            if ($profitPercentage >= 15 && $profitPercentage <= 20) {
                $this->takeProfitStopLossOrder($exchange);
                return;
            }

            // // Stop loss condition
            if ($profitPercentage <= -15) {
                $this->takeProfitStopLossOrder($exchange);
                return;
            }
        }));
    }

    public function stopMonitoring(): void
    {   
        if($this->monitoringTimer !== null){
            Loop::cancelTimer($this->monitoringTimer);
            $this->monitoringTimer = null;
            
            $this->isOrderInProgress = false;
            sleep(getenv('COOL_DOWN_PERIOD'))
                ->then(fn () => $this->execute());
        }        
    }

    public function openPosition(ExchangeManager $exchange): void
    {
        $exchange->setLeverage($this->options['symbol'], $this->options['leverage'])
            ->then(fn() => 
                $exchange->placeOrder(
                    $this->options['symbol'], 
                    $this->options['side'], 
                    $this->options['type'], 
                    $this->options['amount']
                )
            )
            ->then(fn() => 
                $this->startMonitoring(
                    $this->options['symbol'], 
                    $exchange
                )
            )
            ->catch(fn(Throwable $e) => 
                logger()->error($e->getMessage())
            );
    }

    public function takeProfitStopLossOrder(ExchangeManager $exchange)
    {
        $symbol = $this->options['symbol'];
        $side = $this->options['side']; // Original position side (buy/sell)
        $amount = $this->options['amount']; // Position size
    
        // Determine inverse side
        $inverseSide = ($side === 'BUY') ? 'SELL' : 'BUY';
    
        $exchange->placeOrder($symbol, $inverseSide, $this->options['type'], $amount)
            ->then(fn() => 
                $this->stopMonitoring()
            )
            ->catch(fn(Throwable $e) => 
                logger()->error($e->getMessage())
            );
    }

    // private function executeMarketOrder(string $symbol, float $quantity): void
    // {   
    //     $this->isOrderInProgress = true;

    //     $params = [
    //         'symbol' => $symbol,
    //         'side' => 'SELL',
    //         'type' => 'MARKET',
    //         'quantity' => $quantity,
    //         'recvWindow' => 20000,
    //         'timestamp' => time() * 1000
    //     ];

    //     $this->placeOrder->executeOrder($params)->then(
    //         function () use ($symbol) {
    //             $this->stopMonitoring();
    //         },
    //         function (Throwable $e) use ($symbol) {
    //             logger()->error("Failed to execute order for {$symbol}: " . $e->getMessage());
    //             $this->isOrderInProgress = false;
    //         }
    //     );
    // }

    // public function placeStopLossOrder(float $entryPrice, string $symbol, float $quantity): void
    // {   
    //     $this->isOrderInProgress = true;

    //     $stopLossPrice = $entryPrice * 0.65;
    //     $exchangeInfo = await(getExchangeInfo($symbol));
    //     $precision = (int)$exchangeInfo['symbols'][0]['baseAssetPrecision'];
    //     $adjustedPrice = round($stopLossPrice, $precision);

    //     $params = [
    //         'symbol' => $symbol,
    //         'side' => 'SELL',
    //         'type' => 'MARKET',
    //         'quantity' => round($quantity, $precision),
    //         'recvWindow' => 20000,
    //         'timestamp' => time() * 1000
    //     ];

    //     $this->placeOrder->executeOrder($params)->then(
    //         function ($response) use ($symbol, $adjustedPrice) {
    //             logger()->info("Stop loss executed at {$adjustedPrice} for {$symbol}");
    //             $this->stopMonitoring();
    //         },
    //         function (Throwable $e) use ($symbol) {
    //             logger()->error("Failed to place stop loss for {$symbol}: " . $e->getMessage());
    //         }
    //     );
    // }

    public function recoverOpenPositions(array $options, ExchangeManager $exchange): void
    {
        try {
            // Await the result of getPositionInfo
            $positions = await(getPositionInfo($options['symbol']));
            
            if (!empty($positions)) {
                logger()->info("Open position found for {$options['symbol']}. Resuming monitoring.");
            
                $this->isOrderInProgress = true;
                
                $this->startMonitoring($options['symbol'], $exchange);
                
                return;
            } 

            return;
        } catch (Throwable $e) {
            logger()->error("Failed to recover open positions: " . $e->getMessage());
        }
       
    }
}
