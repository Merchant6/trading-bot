<?php

namespace Merchant\TradingBot\Core\Traits;

use Merchant\TradingBot\Core\Utils\Cryptocurrency\Futures\AccountBalance;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\Futures\PlaceOrder;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;
use React\Promise\Timer;
use Throwable;

use function React\Async\async;
use function React\Async\await;
use function React\Promise\Timer\sleep;

trait OrderPlacement
{
    private bool $isOrderInProgress = false;
    private LoggerInterface $logger;
    private PlaceOrder $placeOrder;
    private ?TimerInterface $monitoringTimer = null;

    abstract public function execute();

    public function placeOrder(float $currentPrice, array $options): void
    {
        try {
            if ($this->isOrderInProgress) {
                return;
            }

            $this->isOrderInProgress = true;

            $this->placeOrder = new PlaceOrder($options['leverage']);

            [$hasOpenPositions, $hasOpenOrders] = checkOpenPositionsAndOrders($options['symbol']);
            if ($hasOpenPositions || $hasOpenOrders) {
                sleep(time: $_ENV['COOL_DOWN_PERIOD'])->then(fn() => $this->execute());
                return;
            }

            $accountBalance = new AccountBalance();
            $userAccountBalance = await($accountBalance->getBalance());
            if (!$userAccountBalance || $userAccountBalance <= 0) {
                $this->logger->error("Insufficient account balance.");
                $this->isOrderInProgress = false;
                return;
            }

            $quantity = round((($userAccountBalance * 0.02) * $this->placeOrder->leverage) / $currentPrice, 3);

            $orderParams = [
                'symbol' => $options['symbol'],
                'side' => $options['side'],
                'type' => 'MARKET',
                'quantity' => $quantity,
                'recvWindow' => 5000,
                'timestamp' => time() * 1000
            ];
            
            $this->placeOrder->executeLeveragedOrder($orderParams)
                ->then(function (ResponseInterface $response) use($options) {
                    $this->isOrderInProgress = false;
                    $this->startMonitoring($options['symbol']);
                });
        } catch (Throwable $e) {
            $this->isOrderInProgress = false;
            $this->logger->error($e->getMessage());
        }
    }

    public function startMonitoring(string $symbol): void
    {
        if ($this->monitoringTimer !== null) {
            return; // Avoid duplicate monitoring timers or concurrent orders
        }

        $this->monitoringTimer = Loop::addPeriodicTimer(0.5, async(function () use ($symbol) {
            // Prevent further execution if position has been closed
            $positions = getPositionInfo($symbol);
            if (empty($positions)) {
                $this->stopMonitoring();
                $this->logger->info("No positons found for {$symbol}. Monitoring stopped.");
                return;
            }
        
            $position = $positions[0];
            $entryPrice = (float)$position['entryPrice'];
            $currentPrice = (float)$position['markPrice'];
            $positionAmt = (float)$position['positionAmt'];

            $profitPercentage = round((($currentPrice - $entryPrice) / $entryPrice) * 100 * $this->placeOrder->leverage, 3);

            $exchangeInfo = getExchangeInfo($symbol);
            $precision = (int)$exchangeInfo['symbols'][0]['baseAssetPrecision'];
            $quantity = abs(round($positionAmt, $precision));

            $this->logger->info($profitPercentage);
            // Take profit condition
            if ($profitPercentage >= 15 || $profitPercentage <= 20) {
                $this->logger->info('Should TP.');
                $this->executeMarketOrder($symbol, $quantity);
                return;
            }

            // // Stop loss condition
            if ($profitPercentage <= -15) {
                $this->logger->info('Should SL.');
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

        $this->isOrderInProgress = false;
        sleep($_ENV['COOL_DOWN_PERIOD'])
            ->then(fn () => $this->execute());
    }

    private function executeMarketOrder(string $symbol, float $quantity): void
    {   
        $this->isOrderInProgress = true;

        $params = [
            'symbol' => $symbol,
            'side' => 'SELL',
            'type' => 'MARKET',
            'quantity' => $quantity,
            'recvWindow' => 5000,
            'timestamp' => time() * 1000
        ];

        $this->placeOrder->executeOrder($params)->then(
            function () use ($symbol) {
                $this->stopMonitoring();
            },
            function (Throwable $e) use ($symbol) {
                $this->logger->error("Failed to execute order for {$symbol}: " . $e->getMessage());
                $this->isOrderInProgress = false;
            }
        );
    }

    public function placeStopLossOrder(float $entryPrice, string $symbol, float $quantity): void
    {   
        $this->isOrderInProgress = true;

        $stopLossPrice = $entryPrice * 0.65;
        $exchangeInfo = getExchangeInfo($symbol);
        $precision = (int)$exchangeInfo['symbols'][0]['baseAssetPrecision'];
        $adjustedPrice = round($stopLossPrice, $precision);

        $params = [
            'symbol' => $symbol,
            'side' => 'SELL',
            'type' => 'MARKET',
            'quantity' => round($quantity, $precision),
            'recvWindow' => 5000,
            'timestamp' => time() * 1000
        ];

        $this->placeOrder->executeOrder($params)->then(
            function ($response) use ($symbol, $adjustedPrice) {
                $this->logger->info("Stop loss executed at {$adjustedPrice} for {$symbol}");
                $this->stopMonitoring();
            },
            function (Throwable $e) use ($symbol) {
                $this->logger->error("Failed to place stop loss for {$symbol}: " . $e->getMessage());
            }
        );
    }

    public function recoverOpenPositions(array $options): void
    {
        try{
            $positions = getPositionInfo($options['symbol']);
            if (empty($positions)) {
                $this->logger->info("No open positions found for {$options['symbol']}.");
                return;
            }

            $this->logger->info("Open position found for {$options['symbol']}. Resuming monitoring.");
            $this->startMonitoring($options['symbol']);

        } catch (Throwable $e) {
            $this->logger->error("Failed to recover open positions: " . $e->getMessage());
        }
    }
}
