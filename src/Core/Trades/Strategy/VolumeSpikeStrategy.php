<?php

namespace Merchant\TradingBot\Core\Trades\Strategy;

use Merchant\TradingBot\Core\Trading\StrategySignal;

final class VolumeSpikeStrategy extends AbstractKlineStrategy
{
    public function name(): string
    {
        return 'volume-spike';
    }

    public function generateSignal(array $klineData): StrategySignal
    {
        $prices = $this->closePrices($klineData);
        $volumes = $this->volumes($klineData);
        $lookback = (int)$this->option('lookback', 20);

        if (count($prices) < $lookback + 1 || count($volumes) < $lookback + 1) {
            return StrategySignal::hold('Not enough candles for volume spike.');
        }

        $currentPrice = (float)end($prices);
        $previousPrice = (float)$prices[count($prices) - 2];
        $currentVolume = (float)end($volumes);
        $averageVolume = array_sum(array_slice($volumes, -($lookback + 1), $lookback)) / $lookback;
        $multiplier = (float)$this->option('multiplier', 1.8);

        if ($currentPrice > $previousPrice && $currentVolume >= $averageVolume * $multiplier) {
            return StrategySignal::buy('Bullish candle confirmed by volume spike.', 0.64, [
                'volume' => round($currentVolume, 6),
                'averageVolume' => round($averageVolume, 6),
            ]);
        }

        return StrategySignal::hold('No bullish volume spike.', [
            'volume' => round($currentVolume, 6),
            'averageVolume' => round($averageVolume, 6),
        ]);
    }
}
