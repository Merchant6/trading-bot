<?php

namespace Merchant\TradingBot\Core\Trades\Strategy;

use Merchant\TradingBot\Core\Trading\StrategySignal;

final class BreakoutStrategy extends AbstractKlineStrategy
{
    public function name(): string
    {
        return 'breakout';
    }

    public function generateSignal(array $klineData): StrategySignal
    {
        $prices = $this->closePrices($klineData);
        $highs = $this->highs($klineData);
        $lookback = (int)$this->option('lookback', 20);

        if (count($prices) < $lookback + 1) {
            return StrategySignal::hold('Not enough candles for breakout.');
        }

        $current = (float)end($prices);
        $recentHighs = array_slice($highs, -($lookback + 1), $lookback);
        $resistance = max($recentHighs);
        $buffer = $resistance * ((float)$this->option('bufferPercentage', 0.15) / 100);

        if ($current > $resistance + $buffer) {
            return StrategySignal::buy('Price broke above recent resistance.', 0.66, [
                'price' => round($current, 6),
                'resistance' => round($resistance, 6),
            ]);
        }

        return StrategySignal::hold('Price remains below breakout level.', [
            'price' => round($current, 6),
            'resistance' => round($resistance, 6),
        ]);
    }
}
