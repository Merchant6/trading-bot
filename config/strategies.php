<?php

use Merchant\TradingBot\Core\Trades\Strategy\BollingerRsiStrategy;
use Merchant\TradingBot\Core\Trades\Strategy\BreakoutStrategy;
use Merchant\TradingBot\Core\Trades\Strategy\MacdStrategy;
use Merchant\TradingBot\Core\Trades\Strategy\MovingAverageCrossoverStrategy;
use Merchant\TradingBot\Core\Trades\Strategy\RsiMeanReversionStrategy;
use Merchant\TradingBot\Core\Trades\Strategy\VolumeSpikeStrategy;

return [
    'default' => $_ENV['DEFAULT_STRATEGY'] ?? 'bollinger-rsi',
    'available' => [
        'bollinger-rsi' => BollingerRsiStrategy::class,
        'ma-crossover' => MovingAverageCrossoverStrategy::class,
        'macd' => MacdStrategy::class,
        'rsi-mean-reversion' => RsiMeanReversionStrategy::class,
        'breakout' => BreakoutStrategy::class,
        'volume-spike' => VolumeSpikeStrategy::class,
    ],
    'settings' => [
        'bollinger-rsi' => [
            'period' => 20,
            'stdDev' => 2,
            'rsiPeriod' => 14,
            'oversold' => 30,
        ],
        'ma-crossover' => [
            'fastPeriod' => 9,
            'slowPeriod' => 21,
        ],
        'macd' => [
            'fastPeriod' => 12,
            'slowPeriod' => 26,
            'signalPeriod' => 9,
        ],
        'rsi-mean-reversion' => [
            'rsiPeriod' => 14,
            'oversold' => 30,
            'overbought' => 70,
        ],
        'breakout' => [
            'lookback' => 20,
            'bufferPercentage' => 0.15,
        ],
        'volume-spike' => [
            'lookback' => 20,
            'multiplier' => 1.8,
        ],
    ],
];
