<?php

namespace Merchant\TradingBot\Core\Utils\Cryptocurrency\Indicators;

class BollingerBands
{
    public function __construct()
    {

    }

    /**
     * Summary of calculate
     * @param array<
     *     array prices
     *     string|int period
     *     string|int stdDev
     * > $bbOptions
     * @param int|string $movingAverageType
     * @return array
     */
    public function calculate(array $bbOptions, int|string $movingAverageType = TRADER_MA_TYPE_SMA)
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
