<?php

namespace Merchant\TradingBot\Core\Trades\Strategy;

use Merchant\TradingBot\Core\Trading\IndicatorCalculator;
use Merchant\TradingBot\Core\Trading\StrategySignal;

final class RsiMeanReversionStrategy extends AbstractKlineStrategy
{
    public function name(): string
    {
        return 'rsi-mean-reversion';
    }

    public function generateSignal(array $klineData): StrategySignal
    {
        $prices = $this->closePrices($klineData);
        $period = (int)$this->option('rsiPeriod', 14);
        $oversold = (float)$this->option('oversold', 30);

        if (count($prices) < $period + 2) {
            return StrategySignal::hold('Not enough candles for RSI.');
        }

        $rsi = IndicatorCalculator::rsi($prices, $period);
        $current = (float)end($rsi);

        if ($current > 0 && $current <= $oversold) {
            return StrategySignal::buy('RSI is oversold for mean reversion.', 0.68, [
                'rsi' => round($current, 3),
            ]);
        }

        return StrategySignal::hold('RSI is not oversold.', [
            'rsi' => round($current, 3),
        ]);
    }
}
