<?php

namespace Merchant\TradingBot\Core\Trades\Strategy;

use Merchant\TradingBot\Core\Trading\IndicatorCalculator;
use Merchant\TradingBot\Core\Trading\StrategySignal;

/**
 * Implements Bollinger Bands and RSI trading strategy.
 */
class BollingerRsiStrategy extends AbstractKlineStrategy
{
    private int $period = 20;
    private float $stdDev = 2.0;

    /**
     * Bootstraps the strategy with configuration options.
     */
    public function boot(): void
    {
        parent::boot();

        $this->period = (int)$this->option('period', $this->period);
        $this->stdDev = (float)$this->option('stdDev', $this->stdDev);
    }

    public function name(): string
    {
        return 'bollinger-rsi';
    }

    public function generateSignal(array $klineData): StrategySignal
    {
        $closePrices = $this->closePrices($klineData);
        $rsiPeriod = (int)$this->option('rsiPeriod', 14);

        if (count($closePrices) < max($this->period, $rsiPeriod) + 2) {
            return StrategySignal::hold('Not enough candles for Bollinger RSI.');
        }

        $bands = IndicatorCalculator::bollinger($closePrices, $this->period, $this->stdDev);
        $rsi = IndicatorCalculator::rsi($closePrices, $rsiPeriod);
        $lowerBand = (float)end($bands['lower']);
        $middleBand = (float)end($bands['middle']);
        $currentPrice = (float)end($closePrices);
        $lastTwoPrices = array_slice($closePrices, -2);
        $currentRsi = (float)end($rsi);
        $oversold = (float)$this->option('oversold', 30);

        $tradeCondition = $currentPrice > $lowerBand
            && count($lastTwoPrices) === 2
            && $lastTwoPrices[0] > $lowerBand
            && $lastTwoPrices[1] > $lowerBand
            && $currentPrice < $middleBand
            && $currentRsi <= $oversold;

        if ($tradeCondition) {
            return StrategySignal::buy('Price recovered above lower Bollinger band while RSI is oversold.', 0.74, [
                'price' => round($currentPrice, 6),
                'lowerBand' => round($lowerBand, 6),
                'middleBand' => round($middleBand, 6),
                'rsi' => round($currentRsi, 3),
            ]);
        }

        return StrategySignal::hold('Bollinger RSI entry conditions are not aligned.', [
            'price' => round($currentPrice, 6),
            'lowerBand' => round($lowerBand, 6),
            'middleBand' => round($middleBand, 6),
            'rsi' => round($currentRsi, 3),
        ]);
    }
}
