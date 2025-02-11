<?php

namespace Merchant\TradingBot\Core\Trades\Strategy;

use Merchant\TradingBot\Core\Traits\OrderPlacement;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\MarketData\ContractKLineData;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\MarketData\OrderBook;
use Psr\Log\LoggerInterface;
use React\Promise\Timer;

use function React\Promise\Timer\sleep;

/**
 * Implements Bollinger Bands and RSI trading strategy.
 */
class BollingerRsiStrategy
{
    use OrderPlacement;

    private int $period = 20;
    private int $stdDev = 2;
    
    public function __construct(
        private ContractKLineData $contractKLineData,
        private OrderBook $orderBook,
        LoggerInterface $logger,
        public array $options = []
    ) {
        $this->logger = $logger;
        $this->boot();
    }

    /**
     * Bootstraps the strategy with configuration options.
     */
    public function boot(): void
    {
        $this->period = $this->options['period'] ?? $this->period;
        $this->stdDev = $this->options['stdDev'] ?? $this->stdDev;
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
            $middleBand = round(end($bands['MiddleBand']), 3);
            $currentPrice = round(end($closePrices), 3);
            $lastTwoPrices = array_slice($closePrices, -2);

            $tradeCondition = $currentPrice > $lowerBand && 
            count($lastTwoPrices) === 2 && 
            $lastTwoPrices[0] > $lowerBand && 
            $lastTwoPrices[1] > $lowerBand &&
            $currentPrice < $middleBand;

            if ($tradeCondition) {
                $this->placeOrder($currentPrice, $this->options);
            } else {
                sleep(time: 10)->then(fn() => $this->execute());
            }
        });
    }
}
