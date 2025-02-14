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

/**
 * Generate a HMAC signature 
 * 
 * @param array|string $data
 * @param string $secret
 * @param string $algo
 * @return string
 */
function hmac(array|string $data, string $secret, string $algo = 'sha256'): string
{
    return hash_hmac($algo, $data, $secret);
}

/**
 * Get an instance of Browser class
 * 
 * @return React\Http\Browser
 */
function http(): Browser
{
    return HttpClientManager::getBrowser();
}

/**
 * Check for open positions and orders for a 
 * given symbol
 * 
 * @param string $symbol
 * @return PromiseInterface
 */
function checkOpenPositionsAndOrders(string $symbol): PromiseInterface
{
    return async(function() use ($symbol) {
        try {
            $queryPositions = new QueryPositions();
            $openOrders = new OpenOrders();
            $positions = await($queryPositions->getPosition([
                'symbol' => $symbol,
                'timestamp' => time() * 1000,
                'recvWindow' => 20000,
            ])) ?? [];
            $orders = await($openOrders->queryOpenOrders([
                'symbol' => $symbol,
                'timestamp' => time() * 1000,
                'recvWindow' => 20000,
            ])) ?? [];

            if (!is_array($positions)) {
                logger()->error("Unexpected response: positions is not an array", ['response' => $positions]);
                $positions = [];
            }
            if (!is_array($orders)) {
                logger()->error("Unexpected response: orders is not an array", ['response' => $orders]);
                $orders = [];
            }

            $hasOpenPositions = !empty(array_filter($positions, fn($position) => 
                isset($position['symbol']) && 
                $position['symbol'] === $symbol && 
                abs((float)$position['positionAmt']) > 0
            ));

            $hasOpenOrders = !empty(array_filter($orders, fn($order) => 
                isset($order['symbol']) && 
                $order['symbol'] === $symbol
            ));

            return [$hasOpenPositions, $hasOpenOrders];
        } catch (Throwable $e) {
            logger()->error("Error querying open positions and orders: {$e->getMessage()}");
            return [false, false]; // Changed to return explicit boolean values instead of empty array
        }
    })();
}

function getPositionInfo(string $symbol): PromiseInterface
{
    return async(function () use($symbol){
        try{
            $queryPositions = new QueryPositions();
            $positions = await($queryPositions->getPosition([
                'symbol' => $symbol, 
                'timestamp' => time() * 1000,
                'recvWindow' => 20000,
            ]));

            if (!is_array($positions) || empty($positions) || $positions[0]['positionAmt'] == 0) {
                return [];
            }

            return $positions;
        } catch (Throwable $e) {
            logger()->error('Error querying positions: ' . $e->getMessage());
            return [];
        } 
    })();
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
 * Get exchange information of a certain symbol
 * 
 * @param string $symbol
 * @return PromiseInterface
 */
function getExchangeInfo(string $symbol): PromiseInterface
{
    static $exchangeInfoCache = [];

    if (isset($exchangeInfoCache[$symbol])) {
        return resolve($exchangeInfoCache[$symbol]);
    }

    $exchangeInfoApiUrl = 'https://api.binance.com/api/v3/exchangeInfo';
    $browser = http();
    
    return async(function () use ($browser, $exchangeInfoApiUrl, $symbol, &$exchangeInfoCache) {
        try {
            $response = await($browser->get("$exchangeInfoApiUrl?symbol=$symbol"));
            
            $data = json_decode($response->getBody(), true);

            $exchangeInfoCache[$symbol] = $data;

            return $data;
        } catch (Throwable $e) {
            logger()->error("Error fetching exchange info for {$symbol}: " . $e->getMessage());
            return []; // Return an empty array on error.
        }
    })();
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
