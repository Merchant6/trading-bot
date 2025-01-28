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
    public function checkOpenPositionsAndOrders(string $symbol): array
    {
        return checkOpenPositionsAndOrders($symbol);
    }

    /**
     * Main entry point for executing the strategy.
     */
    public function execute(): void
    {
        if ($this->isOrderInProgress) {
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

            $bands = getBollingerBands(
                $closePrices, 
                $this->period, 
                $this->stdDev
            );

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
                Timer\sleep(time: $this->cooldownPeriod)->then(fn() => $this->execute());
            }
        });
    }

    /**
     * Places an order if conditions are met.
     */
    public function placeOrder(float $currentPrice): ?PromiseInterface
    {
        try{

            if ($this->isOrderInProgress) {
                return null;
            }
    
            $this->isOrderInProgress = true;
            $symbol = $this->options['symbol'];
            $accountBalance = new AccountBalance();
    
            [$hasOpenPositions, $hasOpenOrders] = $this->checkOpenPositionsAndOrders($symbol);
            if ($hasOpenPositions || $hasOpenOrders) {
                Timer\sleep(time: $this->cooldownPeriod)->then(fn() => $this->execute());
                return null;
            }
    
            $userAccountBalance = await($accountBalance->getBalance());
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
                'type' => 'MARKET',
                'quantity' => $quantityWithLeverage,
                'recvWindow' => 5000,
                'timestamp' => time() * 1000
            ];
    
            return $this->placeOrder->executeLimitOrder($orderParams)
                ->then(function ($response) use ($symbol, $currentPrice, $quantityWithLeverage) {
                    $this->placeTakeProfitOrder($currentPrice, $symbol, $quantityWithLeverage);
                    Timer\sleep(time: $this->cooldownPeriod)->then(fn() => $this->execute());
                });
    
                
        } catch (Throwable $e) {
            $this->isOrderInProgress = false;
            $this->logger->error($e->getMessage());
        }
    }

    public function placeTakeProfitOrder(float $entryPrice, string $symbol, float $quantity)
    {
        placeTakeProfitOrder(
            $this->placeOrder, 
            $entryPrice, 
            $symbol, 
            $quantity
        );
    }

    public function placeStopLossOrder(float $entryPrice, string $symbol, float $quantity)
    {
        placeStopLossOrder(
            $this->placeOrder, 
            $entryPrice, 
            $symbol, 
            $quantity
        );
    }
}
