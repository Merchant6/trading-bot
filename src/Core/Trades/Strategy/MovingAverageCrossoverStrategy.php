<?php

namespace Merchant\TradingBot\Core\Trades\Strategy;

use Merchant\TradingBot\Core\Trading\IndicatorCalculator;
use Merchant\TradingBot\Core\Trading\StrategySignal;

final class MovingAverageCrossoverStrategy extends AbstractKlineStrategy
{
    public function name(): string
    {
        return 'ma-crossover';
    }

    public function generateSignal(array $klineData): StrategySignal
    {
        $prices = $this->closePrices($klineData);
        $fastPeriod = (int)$this->option('fastPeriod', 9);
        $slowPeriod = (int)$this->option('slowPeriod', 21);

        if (count($prices) < $slowPeriod + 2) {
            return StrategySignal::hold('Not enough candles for moving averages.');
        }

        $fast = IndicatorCalculator::sma($prices, $fastPeriod);
        $slow = IndicatorCalculator::sma($prices, $slowPeriod);
        $last = count($prices) - 1;
        $previous = $last - 1;

        $crossedUp = $fast[$previous] !== null
            && $slow[$previous] !== null
            && $fast[$previous] <= $slow[$previous]
            && $fast[$last] > $slow[$last];

        if ($crossedUp) {
            return StrategySignal::buy('Fast moving average crossed above slow moving average.', 0.72, [
                'fast' => round($fast[$last], 6),
                'slow' => round($slow[$last], 6),
            ]);
        }

        return StrategySignal::hold('No bullish moving average crossover.', [
            'fast' => round((float)$fast[$last], 6),
            'slow' => round((float)$slow[$last], 6),
        ]);
    }
}
