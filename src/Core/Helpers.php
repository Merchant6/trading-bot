<?php

use Merchant\TradingBot\Core\Utils\Cryptocurrency\Indicators\Rsi;
use Merchant\TradingBot\Core\Utils\HttpClientManager;
use Merchant\TradingBot\Core\Utils\Logger;
use React\Http\Browser;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\Futures\QueryPositions;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\Futures\OpenOrders;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\Futures\PlaceOrder;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\Indicators\BollingerBands;
use Psr\Http\Message\ResponseInterface;
use React\Promise\PromiseInterface;

use function React\Async\async;
use function React\Async\await;
use function React\Promise\resolve;


function timeframeToSeconds(string $timeframe): int
{   
    //We only need timeframe upto weeks
    $units = [
        's' => 1,         // seconds
        'm' => 60,        // minutes
        'h' => 3600,      // hours
        'd' => 86400,     // days
        'w' => 604800,    // weeks
    ];

    preg_match('/(\d+)([smhdwMy])/', strtolower($timeframe), $matches);

    if (!$matches) {
        throw new InvalidArgumentException("Invalid timeframe format: $timeframe");
    }

    [$full, $value, $unit] = $matches;

    return (int)$value * $units[$unit];
}

/**
 * Get Bollinger Bands
 * 
 * @param array $closePrices
 * @param int $period
 * @param int $stdDev
 * @param int|string $movingAverageType
 * @return array
 */
function getBollingerBands(array $closePrices, int $period, int $stdDev, int|string $movingAverageType = TRADER_MA_TYPE_SMA):array 
{
    $bb = new BollingerBands();
    return $bb->calculate([
        'prices' => $closePrices,
        'period' => $period,
        'stdDev' => $stdDev
    ], $movingAverageType);
}

/**
 * Returns if RSI is overbought or oversold
 * 
 * @param array $realValues
 * @param string $chartTimeInterval
 * @param int $timePeriod
 * @return bool
 */
function isRsiOversold(array $realValues, string $chartTimeInterval = '5m', int $timePeriod = 14)
{
  $rsi = new Rsi();
  $rsi->calculate($realValues, $timePeriod);

  return $rsi->isOverSold($chartTimeInterval);
}

/**
 * Get an instance of logger
 * 
 * @return Logger
 */
function logger()
{
    static $instance = null;
    
    if ($instance === null) {
        $instance = new Logger();
    }
    
    return $instance;
}
