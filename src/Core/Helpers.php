<?php

use Merchant\TradingBot\Core\Utils\HttpClientManager;
use React\Http\Browser;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\Futures\QueryPositions;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\Futures\OpenOrders;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\Indicators\BollingerBands;
use Psr\Http\Message\ResponseInterface;
use React\Promise\PromiseInterface;

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
 * @return PromiseInterface<bool[]|TRejected>
 */
function checkOpenPositionsAndOrders(string $symbol): PromiseInterface
{
    $queryPositions = new QueryPositions();
    $openOrders = new OpenOrders();

    return \React\Promise\all([
        $queryPositions->getPosition(['symbol' => $symbol, 'timestamp' => time() * 1000]),
        $openOrders->queryOpenOrders(['symbol' => $symbol, 'timestamp' => time() * 1000])
    ])->then(function (array $results) use ($symbol) {
        [$positions, $orders] = $results;

        $hasOpenPositions = !empty(array_filter($positions, fn($position) => $position['symbol'] === $symbol && abs((float)$position['positionAmt']) > 0));
        $hasOpenOrders = !empty(array_filter($orders, fn($order) => $order['symbol'] === $symbol));

        return [$hasOpenPositions, $hasOpenOrders];
    });
}

function getBollingerBands(array $closePrices, int $period, int $stdDev, int|string $movingAverageType = TRADER_MA_TYPE_SMA):array 
{
    $bb = new BollingerBands();
    return $bb->calculate([
        'prices' => $closePrices,
        'period' => $period,
        'stdDev' => $stdDev
    ], $movingAverageType);
}

function getExchangeInfo(string $symbol)
{   
    $exchangeInfoApiUrl = $_ENV['BINANCE_API_URL'] . '/fapi/v1/exchangeInfo';
    http()->get("$exchangeInfoApiUrl?$symbol")
        ->then(function (ResponseInterface $response) {
            return json_decode($response->getBody(), true);
        })->catch(function (Throwable $e) {
            "";
        });
}