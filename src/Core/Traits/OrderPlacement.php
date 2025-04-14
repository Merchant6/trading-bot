<?php

namespace Merchant\TradingBot\Core\Traits;

use Merchant\TradingBot\Core\Utils\ExchangeManager;
use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;
use React\Promise\Timer;
use Throwable;

use function React\Async\async;
use function React\Async\await;
use function React\Promise\Timer\sleep;
use React\Promise\all;
use React\Promise\PromiseInterface;

trait OrderPlacement
{
    private bool $isOrderInProgress = false;
    private ?TimerInterface $monitoringTimer = null;
    
    abstract public function execute();

    public function placeOrder(float $currentPrice, ExchangeManager $exchange): void
    {
        async(function () use ($currentPrice, $exchange) {
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
                 * Formula: (accountBalance * (amountPercentage / 100)), leverage is automatically applied
                 * e.g. (1000 * (5 / 100)) = 500
                 */
                $this->options['amount'] = round(($userAccountBalance * ($this->options['amountPercentage'] / 100)) / $currentPrice, 4);
                $this->openPosition($exchange);
                
            } catch (Throwable $e) {
                $this->isOrderInProgress = false;
                logger()->error($e->getMessage());
            }
        })();
    }

    public function startMonitoring(string $symbol, ExchangeManager $exchange): void
    {
        if ($this->monitoringTimer !== null) {
            return; // Avoid duplicate monitoring timers or concurrent orders
        }

        $this->monitoringTimer = Loop::addPeriodicTimer(getenv('POLLING_INTERVAL'), async(function () use ($symbol, $exchange) {
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
            
            if(!isset($this->options['amount']) || $this->options['amount'] === null){
                $this->options['amount'] = $quantity;
            }

            // Take profit condition
            if ($profitPercentage >= (float)getenv('MIN_PROFIT') && $profitPercentage <= (float)getenv('MAX_PROFIT')) {
                $this->takeProfitStopLossOrder($exchange);
                return;
            }

            // // Stop loss condition
            if ($profitPercentage <= (float)getenv('MAX_LOSS')) {
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
        $params = [
            'posSide' => $this->options['side'] === 'BUY' ? 'long' : 'short',
            'oneWayMode' => true,
        ];

        $exchange->placeOrder(
            $this->options['symbol'],
            $this->options['type'],
            $this->options['side'],
            $this->options['amount'],
            params: $params
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
        $type = $this->options['type']; // Order type (MARKET/LIMIT)
    
        // Determine inverse side
        $inverseSide = ($side === 'BUY') ? 'SELL' : 'BUY';
    
        $exchange->placeOrder($symbol, $type, $inverseSide, $amount)
            ->then(fn() => 
                $this->stopMonitoring()
            )
            ->catch(fn(Throwable $e) => 
                logger()->error($e->getMessage())
            );
    }

    public function recoverOpenPositions(array $options, ExchangeManager $exchange): PromiseInterface
    {
        return async(function () use($options, $exchange) {
            try {
                // Await the result of getPositionInfo
                $positions = await($exchange->fetchOpenPositions($options['symbol']));
                
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
        })();       
    }
}
