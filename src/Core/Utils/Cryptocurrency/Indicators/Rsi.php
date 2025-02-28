<?php

namespace Merchant\TradingBot\Core\Utils\Cryptocurrency\Indicators;

class Rsi
{   
    private float $currentValue = 0;
    public function __construct()
    {

    }

    /**
     * Calculate RSI using KLine data values,
     * returns the current value of RSI, overbought
     * or oversold
     * 
     * @param array $realValues
     * @param int $timePeriod
     * @return float
     */
    public function calculate(array $realValues, int $timePeriod = 14): float
    {   
        $values = trader_rsi($realValues, $timePeriod);
        $rsiValue = round(end($values), 3);

        $this->currentValue = $rsiValue;

        return $rsiValue; 
    }
    
    /**
     * Check if RSI is at overbought or over
     * sold
     * 
     * @param string $chartTimeInterval
     * @return bool
     */
    public function isOverSold(string $chartTimeInterval = '5m')
    {   
        $minRsiValue = getenv('MIN_RSI_VALUE') ?? 25;
        $maxRsiValue = getenv('MAX_RSI_VALUE') ?? 30;
        
        if($chartTimeInterval == '1m' || $chartTimeInterval == '5m'){
            return $this->currentValue >= $minRsiValue && $this->currentValue <= $maxRsiValue;
        }

        return $this->currentValue >= $minRsiValue && $this->currentValue <= $maxRsiValue; 
    }
}
