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
    // private PlaceOrder $placeOrder;
    private ?TimerInterface $monitoringTimer = null;
    private ExchangeManager $exchange;


    abstract public function execute();

    public function placeOrder(float $currentPrice, ExchangeManager $exchange): void
    {
        try {
            if ($this->isOrderInProgress) {
                return;
            }

            $this->isOrderInProgress = true;

            [$hasOpenPositions, $hasOpenOrders] = await($exchange->fetchOpenOrdersAndPositions($this->options['symbol']));
            if ($hasOpenPositions || $hasOpenOrders) {
                sleep(time: getenv('COOL_DOWN_PERIOD'))->then(fn() => $this->execute());
                return;
            }

            $userAccountBalance = await($exchange->fetchBalance());
            if (!$userAccountBalance) {
                logger()->error("Insufficient account balance.");
                $this->isOrderInProgress = false;
                return;
            }

            $quantity = round((($userAccountBalance * 0.02) * $this->placeOrder->leverage) / $currentPrice, 4);

            $orderParams = [
                'symbol' => $this->options['symbol'],
                'side' => 'BUY',
                'type' => 'MARKET',
                'quantity' => $quantity,
                'recvWindow' => 20000,
                'timestamp' => time() * 1000
            ];
            
            $exchange->placeOrder(
                $this->options['symbol'], 
                $this->options['side'], 
                $this->options['ordertype'],
                $this->options['leverage'], 
            )
            ->then(function () {
                $this->isOrderInProgress = false;
                $this->startMonitoring($this->options['symbol']);
            });
            
        } catch (Throwable $e) {
            $this->isOrderInProgress = false;
            logger()->error($e->getMessage());
        }
    }

    public function startMonitoring(string $symbol): void
    {
        if ($this->monitoringTimer !== null) {
            return; // Avoid duplicate monitoring timers or concurrent orders
        }

        $this->monitoringTimer = Loop::addPeriodicTimer(0.5, async(function () use ($symbol) {
            // Prevent further execution if position has been closed
            $positions = await(getPositionInfo($symbol));
            if (empty($positions)) {
                $this->stopMonitoring();
                logger()->info("No positons found for {$symbol}. Monitoring stopped.");
                return;
            }
        
            $position = $positions[0];
            $entryPrice = (float)$position['entryPrice'];
            $currentPrice = (float)$position['markPrice'];
            $positionAmt = (float)$position['positionAmt'];

            $profitPercentage = round((($currentPrice - $entryPrice) / $entryPrice) * 100 * $this->placeOrder->leverage, 3);

            $exchangeInfo = await(getExchangeInfo($symbol));
            $precision = (int)$exchangeInfo['symbols'][0]['baseAssetPrecision'];
            $quantity = abs(round($positionAmt, $precision));

            // Take profit condition
            if ($profitPercentage >= 15 && $profitPercentage <= 20) {
                $this->executeMarketOrder($symbol, $quantity);
                return;
            }

            // // Stop loss condition
            if ($profitPercentage <= -15) {
                $this->placeStopLossOrder($entryPrice, $symbol, $quantity);
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
            sleep($_ENV['COOL_DOWN_PERIOD'])
                ->then(fn () => $this->execute());
        }        
    }

    private function executeMarketOrder(string $symbol, float $quantity): void
    {   
        $this->isOrderInProgress = true;

        $params = [
            'symbol' => $symbol,
            'side' => 'SELL',
            'type' => 'MARKET',
            'quantity' => $quantity,
            'recvWindow' => 20000,
            'timestamp' => time() * 1000
        ];

        $this->placeOrder->executeOrder($params)->then(
            function () use ($symbol) {
                $this->stopMonitoring();
            },
            function (Throwable $e) use ($symbol) {
                logger()->error("Failed to execute order for {$symbol}: " . $e->getMessage());
                $this->isOrderInProgress = false;
            }
        );
    }

    public function placeStopLossOrder(float $entryPrice, string $symbol, float $quantity): void
    {   
        $this->isOrderInProgress = true;

        $stopLossPrice = $entryPrice * 0.65;
        $exchangeInfo = await(getExchangeInfo($symbol));
        $precision = (int)$exchangeInfo['symbols'][0]['baseAssetPrecision'];
        $adjustedPrice = round($stopLossPrice, $precision);

        $params = [
            'symbol' => $symbol,
            'side' => 'SELL',
            'type' => 'MARKET',
            'quantity' => round($quantity, $precision),
            'recvWindow' => 20000,
            'timestamp' => time() * 1000
        ];

        $this->placeOrder->executeOrder($params)->then(
            function ($response) use ($symbol, $adjustedPrice) {
                logger()->info("Stop loss executed at {$adjustedPrice} for {$symbol}");
                $this->stopMonitoring();
            },
            function (Throwable $e) use ($symbol) {
                logger()->error("Failed to place stop loss for {$symbol}: " . $e->getMessage());
            }
        );
    }

    public function recoverOpenPositions(array $options)
    {
        try {
            // Await the result of getPositionInfo
            $positions = await(getPositionInfo($options['symbol']));
            
            if (!empty($positions)) {
                logger()->info("Open position found for {$options['symbol']}. Resuming monitoring.");
            
                $this->isOrderInProgress = true;
                
                $this->startMonitoring($options['symbol']);
                
                return;
            } 

            return;
        } catch (Throwable $e) {
            logger()->error("Failed to recover open positions: " . $e->getMessage());
        }
       
    }
}
