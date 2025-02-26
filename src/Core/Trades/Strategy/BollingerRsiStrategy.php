<?php

namespace Merchant\TradingBot\Core\Trades\Strategy;

use Merchant\TradingBot\Core\Traits\OrderPlacement;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\MarketData\ContractKLineData;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\MarketData\OrderBook;
use Merchant\TradingBot\Core\Utils\ExchangeManager;
use Psr\Log\LoggerInterface;
use React\Promise\PromiseInterface;
use React\Promise\Timer;

use function React\Async\async;
use function React\Async\await;
use function React\Promise\Timer\sleep;

/**
 * Implements Bollinger Bands and RSI trading strategy.
 */
class BollingerRsiStrategy
{
    use OrderPlacement;

    public int $period = 20;
    public int $stdDev = 2;
    
    public function __construct(
        public ExchangeManager $exchange,
        public array $options = []
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
        // $this->init($this->options);
    }

    /**
     * Main entry point for executing the strategy.
     */
    public function execute(): PromiseInterface 
    {   
        return async(function () {
            await($this->recoverOpenPositions($this->options, $this->exchange));

            if ($this->isOrderInProgress) {
                return;
            }

            $this->processTrade();
        })();
    }

    /**
     * Process trade logic based on Bollinger Bands and RSI conditions.
     */
    public function processTrade(): void
    {   

        $this->exchange->fetchContinuousClosePrice(function (array $closePrices) {

            $bands = getBollingerBands(
                $closePrices, 
                $this->period, 
                $this->stdDev
            );

            $isOversold = isRsiOversold($closePrices);

            $lowerBand = round(end($bands['LowerBand']), 3);
            $middleBand = round(end($bands['MiddleBand']), 3);
            $currentPrice = round(end($closePrices), 3);
            $lastTwoPrices = array_slice($closePrices, -2);

            $tradeCondition = $currentPrice > $lowerBand && 
            count($lastTwoPrices) === 2 && 
            $lastTwoPrices[0] > $lowerBand && 
            $lastTwoPrices[1] > $lowerBand &&
            $currentPrice < $middleBand &&
            $isOversold;

            if ($tradeCondition) {
                $this->placeOrder($currentPrice, $this->exchange);
            } else {
                sleep(time: 10)->then(fn() => $this->execute());
            }

        }, $this->options['symbol']);
    }
}
