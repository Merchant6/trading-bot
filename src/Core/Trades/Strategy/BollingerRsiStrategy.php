<?php

namespace Merchant\TradingBot\Core\Trades\Strategy;

use Merchant\TradingBot\Core\Interfaces\StrategyInterface;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\Futures\AccountBalance;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\Futures\OpenOrders;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\Futures\PlaceOrder;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\Indicators\BollingerBands;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\MarketData\ContractKLineData;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\MarketData\OrderBook;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Implements Bollinger Bands and RSI trading strategy.
 */
class BollingerRsiStrategy implements StrategyInterface
{
    /**
     * Period for Bollinger Bands.
     * @var int
     */
    public int $period = 20;

    /**
     * Standard deviation for Bollinger Bands.
     * @var int
     */
    public int $stdDev = 2;

    /**
     * Constructor to initialize trading parameters.
     *
     * @param ContractKLineData $contractKLineData An instance for processing K-line data.
     * @param PlaceOrder $placeOrder Object to handle order placement.
     * @param OrderBook $orderBook Object to handle order book data.
     * @param LoggerInterface $logger Logger for logging messages.
     * @param array $options Configuration options for the strategy.
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
     * Initializes the strategy with options.
     */
    private function boot(): void
    {
        $this->period = $this->options['period'] ?? $this->period;
        $this->stdDev = $this->options['stdDev'] ?? $this->stdDev;
    }

    /**
     * Executes the Bollinger Bands and RSI strategy.
     */
    public function execute(): void
    {
        $this->contractKLineData->details(function (array $data) {
            $closePrices = array_column($data, 'close_price');

            // Calculate Bollinger Bands
            $bbOptions = [
                'prices' => $closePrices,
                'period' => $this->period,
                'stdDev' => $this->stdDev,
            ];

            $bb = new BollingerBands();
            $bands = $bb->calculate($bbOptions, TRADER_MA_TYPE_SMA);

            $upperBand = round(end($bands['UpperBand']), 3);
            $lowerBand = round(end($bands['LowerBand']), 3);
            $middleBand = round(end($bands['MiddleBand']), 3);
            $currentPrice = round(end($closePrices), 3);
            
            $lastTwoCandlePrices = array_slice($closePrices, -2);

            if (
                count($lastTwoCandlePrices) === 2 &&
                $lastTwoCandlePrices[0] > $lowerBand &&
                $lastTwoCandlePrices[1] > $lowerBand
            ) {
                
                $this->processTrade($currentPrice);
            }
        });
    }

    /**
     * Processes trade logic when conditions are met.
     *
     * @param float $currentPrice The current price of the asset.
     */
    public function processTrade(float $currentPrice): void
    {
        $accountBalance = new AccountBalance();

        $accountBalance->getBalance()->then(
            function (?float $userAccountBalance) use ($currentPrice) {
                if (!$userAccountBalance || $userAccountBalance <= 0) {
                    $this->logger->error("Insufficient account balance.");
                    return;
                }

                $this->orderBook->details(function (array $orderBookData) use ($currentPrice, $userAccountBalance) {
                    if (empty($orderBookData)) {
                        $this->logger->error("Order book data is empty or unavailable.");
                        return;
                    }

                    $bestAsk = $orderBookData['asks'][0]['price'];
                    $bestBid = $orderBookData['bids'][0]['price'];

                    $priceDiff = abs($bestBid - $currentPrice);

                    $balancePercentage = 20 / 100;
                    $quantityWithLeverage = round(
                        ($userAccountBalance * $balancePercentage * $this->placeOrder->leverage) / $currentPrice,
                        3
                    );

                    $this->checkAndPlaceOrder($currentPrice, $quantityWithLeverage);
                });
            },
            function (Throwable $e) {
                $this->logger->error("Error fetching account balance: " . $e->getMessage(), ['exception' => $e]);
            }
        );
    }

    /**
     * Checks for open orders and places a new order if conditions are met.
     *
     * @param float $currentPrice The current price of the asset.
     * @param float $quantityWithLeverage The calculated order quantity with leverage.
     */
    public function checkAndPlaceOrder(float $currentPrice, float $quantityWithLeverage): void
    {
        $openOrders = new OpenOrders();

        $openOrders->queryOpenOrders([
            'symbol' => $this->options['symbol'],
            'timestamp' => time() * 1000,
        ])->then(
            function (array $openOrders) use ($currentPrice, $quantityWithLeverage) {
                if (count($openOrders) > 0) {
                    $this->logger->info("Open order already exists for the given symbol.");
                    return;
                }

                $this->placeOrder->price = $currentPrice;
                $this->placeOrder->quantity = $quantityWithLeverage;

                $this->placeOrder->execute()
                    ->then(
                        function (ResponseInterface $response) {
                            $this->logger->info("Order placed successfully.", ['response' => $response]);
                        },
                        function (Throwable $e) {
                            $this->logger->error("Failed to place order: " . $e->getMessage(), ['exception' => $e]);
                        }
                    );
            },
            function (Throwable $e) {
                $this->logger->error("Error querying open orders: " . $e->getMessage(), ['exception' => $e]);
            }
        );
    }
}
