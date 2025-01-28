<?php

use Merchant\TradingBot\Core\Utils\HttpClientManager;
use Merchant\TradingBot\Core\Utils\Logger;
use React\Http\Browser;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\Futures\QueryPositions;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\Futures\OpenOrders;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\Indicators\BollingerBands;
use Psr\Http\Message\ResponseInterface;
use React\Promise\PromiseInterface;

use function React\Async\async;
use function React\Async\await;

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
 * @return array
 */
function checkOpenPositionsAndOrders(string $symbol): array
{
    try {
        $queryPositions = new QueryPositions();
        $openOrders = new OpenOrders();

        $positions = await($queryPositions->getPosition(['symbol' => $symbol, 'timestamp' => time() * 1000]));
        $orders = await($openOrders->queryOpenOrders(['symbol' => $symbol, 'timestamp' => time() * 1000]));

        $hasOpenPositions = !empty(array_filter($positions, fn($position) => $position['symbol'] === $symbol && abs((float)$position['positionAmt']) > 0));
        $hasOpenOrders = !empty(array_filter($orders, fn($order) => $order['symbol'] === $symbol));

        return [$hasOpenPositions, $hasOpenOrders];
    } catch (Throwable $e)  {
        logger()->error("Error fetching account balance: {$e->getMessage()}");
        return [];
    }
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
function getExchangeInfo(string $symbol): array
{
    static $exchangeInfoCache = [];

    if (isset($exchangeInfoCache[$symbol])) {
        return $exchangeInfoCache[$symbol];
    }

    $exchangeInfoApiUrl = 'https://api.binance.com/api/v3/exchangeInfo';
    $browser = http();
    
    $fetchExchangeInfo = async(function () use ($browser, $exchangeInfoApiUrl, $symbol, &$exchangeInfoCache) {
        try {
            /** @var ResponseInterface $response */
            $response = await($browser->get("$exchangeInfoApiUrl?symbol=$symbol"));
            
            $data = json_decode($response->getBody(), true);

            $exchangeInfoCache[$symbol] = $data;

            return $data;
        } catch (Throwable $e) {
            logger()->error("Error fetching exchange info for {$symbol}: " . $e->getMessage());
            return []; // Return an empty array on error.
        }
    });

    // Execute the async function and return the result.
    return await($fetchExchangeInfo());
}

/**
 * Get an instance of logger
 * 
 * @return Logger
 */
function logger()
{
    return new Logger();
}