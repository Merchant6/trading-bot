<?php

namespace Merchant\TradingBot\Core\Trades\Strategy;

use Merchant\TradingBot\Core\Trading\IndicatorCalculator;
use Merchant\TradingBot\Core\Trading\StrategySignal;

final class MacdStrategy extends AbstractKlineStrategy
{
    public function name(): string
    {
        return 'macd';
    }

    public function generateSignal(array $klineData): StrategySignal
    {
        $prices = $this->closePrices($klineData);
        $slowPeriod = (int)$this->option('slowPeriod', 26);

        if (count($prices) < $slowPeriod + 2) {
            return StrategySignal::hold('Not enough candles for MACD.');
        }

        $macd = IndicatorCalculator::macd(
            $prices,
            (int)$this->option('fastPeriod', 12),
            $slowPeriod,
            (int)$this->option('signalPeriod', 9)
        );

        $last = count($prices) - 1;
        $previous = $last - 1;
        $crossedUp = $macd['macd'][$previous] <= $macd['signal'][$previous]
            && $macd['macd'][$last] > $macd['signal'][$last]
            && $macd['histogram'][$last] > 0;

        if ($crossedUp) {
            return StrategySignal::buy('MACD crossed above signal line with positive histogram.', 0.7, [
                'macd' => round($macd['macd'][$last], 6),
                'signal' => round($macd['signal'][$last], 6),
            ]);
        }

        return StrategySignal::hold('MACD has no bullish confirmation.', [
            'macd' => round($macd['macd'][$last], 6),
            'signal' => round($macd['signal'][$last], 6),
        ]);
    }
}
