<?php

namespace Merchant\TradingBot\Core\Trades\Strategy;

use Merchant\TradingBot\Core\Interfaces\StrategyInterface;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\Futures\AccountBalance;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\Futures\OpenOrders;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\Futures\PlaceOrder;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\Futures\QueryPositions;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\MarketData\ContractKLineData;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\MarketData\OrderBook;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\Indicators\BollingerBands;
use Psr\Log\LoggerInterface;
use React\Promise\Timer;
use React\Promise\PromiseInterface;
use Throwable;

use function React\Async\await;

/**
 * Implements Bollinger Bands and RSI trading strategy.
 */
class BollingerRsiStrategy
{
    private bool $isOrderInProgress = false;
    private int|float $cooldownPeriod = 10;
    private int $period = 120;
    private int $stdDev = 2;

    /**
     * Constructor to initialize trading parameters.
     */
    public function __construct(
        private ContractKLineData $contractKLineData,
        private PlaceOrder $placeOrder,
        private OrderBook $orderBook,
        private LoggerInterface $logger,
        private array $options = []
    ) {
        $this->boot();
    }

    /**
     * Bootstraps the strategy with configuration options.
     */
    public function boot(): void
    {
        $this->period = $this->options['period'] ?? $this->period;
        $this->stdDev = $this->options['stdDev'] ?? $this->stdDev;
        $this->cooldownPeriod = $this->options['cooldownPeriod'] ?? $this->cooldownPeriod;
    }

    /**
     * Check for open positions and orders.
     */
    public function checkOpenPositionsAndOrders(string $symbol): PromiseInterface
    {
        $queryPositions = new QueryPositions();
        $openOrders = new OpenOrders();

        return \React\Promise\all([
            $queryPositions->getPosition(['symbol' => $symbol, 'timestamp' => time() * 1000]),
            $openOrders->queryOpenOrders(['symbol' => $symbol, 'timestamp' => time() * 1000])
        ])->then(function (array $results) use ($symbol) {
            [$positions, $orders] = $results;

            $hasOpenPositions = !empty(array_filter($positions, fn($position) => $position['symbol'] === $symbol && abs((float)$position['positionAmt']) > 0));
            $hasOpenOrders = !empty(array_filter($orders, fn($order) => $order['symbol'] === $symbol));

            return [$hasOpenPositions, $hasOpenOrders];
        });
    }

    /**
     * Main entry point for executing the strategy.
     */
    public function execute(): void
    {
        if ($this->isOrderInProgress) {
            $this->logger->info("Order already in progress for {$this->options['symbol']}. Skipping execution.");
            return;
        }

        $this->processTrade();
    }

    /**
     * Process trade logic based on Bollinger Bands and RSI conditions.
     */
    public function processTrade(): void
    {
        $this->contractKLineData->details(function (array $data) {
            $closePrices = array_column($data, 'close_price');

            $bb = new BollingerBands();
            $bands = $bb->calculate([
                'prices' => $closePrices,
                'period' => $this->period,
                'stdDev' => $this->stdDev
            ], TRADER_MA_TYPE_SMA);

            $lowerBand = round(end($bands['LowerBand']), 3);
            $currentPrice = round(end($closePrices), 3);
            $lastTwoPrices = array_slice($closePrices, -2);

            if ($currentPrice > $lowerBand &&
                count($lastTwoPrices) === 2 &&
                $lastTwoPrices[0] > $lowerBand &&
                $lastTwoPrices[1] > $lowerBand
            ) {
                $this->placeOrder($currentPrice);
            } else {
                $this->logger->info("Trade conditions not met. Waiting for the next opportunity.");
                Timer\sleep(time: $this->cooldownPeriod)->then(fn() => $this->execute());
            }
        });
    }

    /**
     * Places an order if conditions are met.
     */
    public function placeOrder(float $currentPrice): ?PromiseInterface
    {
        if ($this->isOrderInProgress) {
            $this->logger->info("Order already in progress. Skipping.");
            return null;
        }

        $this->isOrderInProgress = true;
        $symbol = $this->options['symbol'];
        $accountBalance = new AccountBalance();

        return $this->checkOpenPositionsAndOrders($symbol)
            ->then(function (array $results) use ($currentPrice, $symbol, $accountBalance) {
                [$hasOpenPositions, $hasOpenOrders] = $results;

                if ($hasOpenPositions || $hasOpenOrders) {
                    $this->logger->info("Open positions or orders exist for {$symbol}. Skipping order placement.");
                    Timer\sleep(time: $this->cooldownPeriod)->then(fn() => $this->execute());
                    return null;
                }

                return $accountBalance->getBalance()->then(
                    function (?float $userAccountBalance) use ($currentPrice, $symbol) {
                        if (!$userAccountBalance || $userAccountBalance <= 0) {
                            $this->logger->error("Insufficient account balance.");
                            $this->isOrderInProgress = false;
                            return null;
                        }

                        $balancePercentage = 2 / 100;
                        $quantityWithLeverage = round(
                            (($userAccountBalance * $balancePercentage) * $this->placeOrder->leverage) / $currentPrice,
                            3
                        );

                        $orderParams = [
                            'symbol' => $symbol,
                            'side' => $this->options['side'],
                            'type' => $this->options['type'],
                            'timeInForce' => 'GTC',
                            'price' => $currentPrice,
                            'quantity' => $quantityWithLeverage,
                            'recvWindow' => 5000,
                            'timestamp' => time() * 1000
                        ];

                        return $this->placeOrder->executeLimitOrder($orderParams)
                            ->then(function ($response) use ($symbol) {
                                $this->logger->info("Order placed successfully for {$symbol}.", ['response' => $response]);
                                Timer\sleep(time: $this->cooldownPeriod)->then(fn() => $this->execute());
                            });
                    },
                    function (Throwable $e) {
                        $this->logger->error("Error fetching account balance: {$e->getMessage()}");
                        $this->isOrderInProgress = false;
                    }
                );
            })
            ->finally(function () {
                $this->isOrderInProgress = false;
            });
    }
}
