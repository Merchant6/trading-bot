<?php

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

function placeTakeProfitOrder(PlaceOrder $placeOrder, float $entryPrice, string $symbol, float $quantity)
{
    $minProfitOnMargin = 8;  // Minimum 8% profit on margin
        $maxProfitOnMargin = 12; // Maximum 12% profit on margin

        $profitOnMargin = mt_rand($minProfitOnMargin, $maxProfitOnMargin) / 100 . PHP_EOL;

        $exchangeInfo = getExchangeInfo($symbol);
        $tickSize = (float)$exchangeInfo['symbols'][0]['filters'][0]['tickSize'];
        $precision = (int)$exchangeInfo['symbols'][0]['baseAssetPrecision'];

        $profitAmount = $entryPrice * $profitOnMargin / $placeOrder->leverage; // Adjust profit for leverage
        $takeProfitPrice = round($entryPrice + $profitAmount, $precision);

        // Adjust price according to tick size and precision
        $scaled = $takeProfitPrice / $tickSize;
        $rounded = round($scaled);
        $adjustedPrice = round($rounded * $tickSize, $precision);
        
        // Round quantity according to precision requirements
        $adjustedQuantity = round($quantity, $precision);

        $limitOrderParams = [
            'symbol' => $symbol,
            'side' => 'SELL', 
            'type' => 'LIMIT',
            'quantity' => $adjustedQuantity,
            'price' => $adjustedPrice,
            'timeInForce' => 'GTC',
            'recvWindow' => 5000,
            'timestamp' => time() * 1000
        ];

        $placeOrder->executeOrder($limitOrderParams)->then(
            function ($response) use ($symbol) {
                $this->isOrderInProgress = false;
            },
            function (Throwable $e) use ($symbol) {
                $this->logger->error("Failed to place take profit order for {$symbol}: " . $e->getMessage(), ['exception' => $e]);
                $this->isOrderInProgress = true;
            }
        );
}

function placeStopLossOrder(PlaceOrder $placeOrder, float $entryPrice, string $symbol, float $quantity)
{
    // Stop loss price: 35% below the entry price
    $stopLossPercentage = 35 / 100; 
    $stopLossPrice = $entryPrice * (1 - $stopLossPercentage);

    // Fetch exchange information for precision and tick size
    $exchangeInfo = getExchangeInfo($symbol);
    $tickSize = (float)$exchangeInfo['symbols'][0]['filters'][0]['tickSize'];
    $precision = (int)$exchangeInfo['symbols'][0]['baseAssetPrecision'];

    // Adjust price and quantity according to exchange rules
    $adjustedStopLossPrice = round(floor($stopLossPrice / $tickSize) * $tickSize, $precision);
    $adjustedQuantity = round($quantity, $precision);

    $stopLossOrderParams = [
        'symbol' => $symbol,
        'side' => 'SELL',
        'type' => 'STOP_MARKET',
        'quantity' => $adjustedQuantity,
        'stopPrice' => $adjustedStopLossPrice, // The stop price for triggering the stop-loss
        'recvWindow' => 5000,
        'timestamp' => time() * 1000
    ];

    // Place the stop loss order
    $placeOrder->executeOrder($stopLossOrderParams)->then(
        function ($response) use ($symbol, $entryPrice) {
            $this->logger->info("Stop loss order placed successfully for {$symbol} at price {$entryPrice}");
            $this->isOrderInProgress = false;
        },
        function (Throwable $e) use ($symbol) {
            $this->logger->error("Failed to place stop loss order for {$symbol}: " . $e->getMessage(), ['exception' => $e]);
            $this->isOrderInProgress = true;
        }
    );
}