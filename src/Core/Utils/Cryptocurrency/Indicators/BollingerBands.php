<?php

namespace Merchant\TradingBot\Core\Utils\Cryptocurrency\Indicators;

class BollingerBands
{
    public function __construct()
    {

    }

    public function calculate(array|null $bbOptions, int|string $movingAverageType = TRADER_MA_TYPE_SMA)
    {
        $prices = $bbOptions['prices'];
        $period = $bbOptions['period'];
        $stdDev = $bbOptions['stdDev'];

        $bbands = trader_bbands(
            $prices, 
            $period, 
            $stdDev, 
            $stdDev, 
            $movingAverageType
        );

        return $bbands;
    }
}
