<?php

namespace Merchant\TradingBot\Core\Traits;

use Merchant\TradingBot\Core\Utils\Cryptocurrency\Futures\AccountBalance;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\Futures\PlaceOrder;
use Merchant\TradingBot\Core\Trading\Config;
use Merchant\TradingBot\Core\Trading\RiskManager;
use Merchant\TradingBot\Core\Trading\StrategySignal;
use Merchant\TradingBot\Core\Trading\TradingStateRepository;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;
use Throwable;

use function React\Async\async;
use function React\Async\await;
use function React\Promise\Timer\sleep;

trait OrderPlacement
{
    private bool $isOrderInProgress = false;
    private PlaceOrder $placeOrder;
    private ?TimerInterface $monitoringTimer = null;
    private RiskManager $riskManager;
    private TradingStateRepository $stateRepository;


    abstract public function execute();

    public function init(array $options)
    {
        $this->riskManager = new RiskManager($options['risk'] ?? []);
        $this->stateRepository = new TradingStateRepository();
        $this->placeOrder = new PlaceOrder($this->riskManager->normalizeLeverage((int)($options['leverage'] ?? 1)));
    }

    public function isOrderInProgress(): bool
    {
        return $this->isOrderInProgress;
    }

    public function recordSignal(StrategySignal $signal, float $currentPrice): void
    {
        $this->stateRepository->recordEvent('signal', [
            'strategy' => $this->options['strategy'] ?? (method_exists($this, 'name') ? $this->name() : static::class),
            'symbol' => $this->options['symbol'] ?? null,
            'price' => $currentPrice,
            'signal' => $signal->toArray(),
        ]);
    }

    public function placeOrder(float $currentPrice, float $quantityWithLeverage = 0): void
    {
        try {
            if ($this->isOrderInProgress) {
                return;
            }

            $this->isOrderInProgress = true;

            if ($this->isPaperTrading()) {
                $quantity = $quantityWithLeverage > 0
                    ? $quantityWithLeverage
                    : $this->riskManager->calculateQuantity(
                        (float)($this->options['paper_balance'] ?? 1000),
                        $currentPrice,
                        $this->placeOrder->leverage
                    );

                $this->stateRepository->recordEvent('paper_order', [
                    'params' => [
                        'symbol' => $this->options['symbol'],
                        'side' => $this->options['side'] ?? 'BUY',
                        'type' => $this->options['type'] ?? 'MARKET',
                        'quantity' => $quantity,
                    ],
                    'price' => $currentPrice,
                ]);
                $this->isOrderInProgress = false;
                sleep(time: $this->coolDownPeriod())->then(fn() => $this->execute());
                return;
            }

            [$hasOpenPositions, $hasOpenOrders] = checkOpenPositionsAndOrders($this->options['symbol']);
            if ($hasOpenPositions || $hasOpenOrders) {
                $this->isOrderInProgress = false;
                sleep(time: $this->coolDownPeriod())->then(fn() => $this->execute());
                return;
            }

            $accountBalance = new AccountBalance();
            $userAccountBalance = await($accountBalance->getBalance());
            if (!$userAccountBalance || $userAccountBalance <= 0) {
                logger()->error("Insufficient account balance.");
                $this->isOrderInProgress = false;
                return;
            }

            $quantity = $quantityWithLeverage > 0
                ? $quantityWithLeverage
                : $this->riskManager->calculateQuantity(
                    (float)$userAccountBalance,
                    $currentPrice,
                    $this->placeOrder->leverage
                );

            if ($quantity <= 0) {
                logger()->error("Calculated order quantity is zero.");
                $this->isOrderInProgress = false;
                return;
            }

            $orderParams = [
                'symbol' => $this->options['symbol'],
                'side' => $this->options['side'] ?? 'BUY',
                'type' => $this->options['type'] ?? 'MARKET',
                'quantity' => $quantity,
                'recvWindow' => 20000,
                'timestamp' => time() * 1000
            ];

            $this->placeOrder->executeLeveragedOrder($orderParams)
                ->then(function (ResponseInterface $response){
                    $this->isOrderInProgress = false;
                    $this->stateRepository->recordEvent('order_placed', [
                        'symbol' => $this->options['symbol'] ?? null,
                        'response' => (string)$response->getBody(),
                    ]);
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
            $positions = getPositionInfo($symbol);
            if (empty($positions)) {
                $this->stopMonitoring();
                logger()->info("No positons found for {$symbol}. Monitoring stopped.");
                return;
            }
        
            $position = $positions[0];
            $entryPrice = (float)$position['entryPrice'];
            $currentPrice = (float)$position['markPrice'];
            $positionAmt = (float)$position['positionAmt'];

            $profitPercentage = $this->riskManager->profitPercentage($entryPrice, $currentPrice, $this->placeOrder->leverage);

            $exchangeInfo = getExchangeInfo($symbol);
            $precision = (int)$exchangeInfo['symbols'][0]['baseAssetPrecision'];
            $quantity = abs(round($positionAmt, $precision));

            // Take profit condition
            if ($this->riskManager->shouldTakeProfit($profitPercentage)) {
                $this->executeMarketOrder($symbol, $quantity);
                return;
            }

            // // Stop loss condition
            if ($this->riskManager->shouldStopLoss($profitPercentage)) {
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
            sleep($this->coolDownPeriod())
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

        $stopLossPrice = $this->riskManager->stopLossPrice($entryPrice);
        $exchangeInfo = getExchangeInfo($symbol);
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
            if ($this->isPaperTrading()) {
                return;
            }

            // Await the result of getPositionInfo
            $positions = getPositionInfo($options['symbol']);
            
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

    private function coolDownPeriod(): int
    {
        return (int)($this->riskManager->config()['cool_down_period'] ?? ($_ENV['COOL_DOWN_PERIOD'] ?? 30));
    }

    private function isPaperTrading(): bool
    {
        return filter_var($this->options['paper_trading'] ?? Config::value('trading', 'paper_trading', true), FILTER_VALIDATE_BOOLEAN);
    }
}
