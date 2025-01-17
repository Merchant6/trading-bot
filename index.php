<?php

use Merchant\TradingBot\Core\Trades\Strategy\BollingerRsiStrategy;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\Futures\AccountBalance;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\Futures\OpenOrders;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\Futures\PlaceOrder;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\Indicators\BollingerBands;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\MarketData\ContractKLineData;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\MarketData\OrderBook;
use Merchant\TradingBot\Core\Utils\Logger;
use React\EventLoop\Loop;

require __DIR__ . "/vendor/autoload.php";

//Initializing Dotenv
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

//Initializing the event loop
$loop = Loop::get();

$symbol = 'BTCUSDT';
$side = 'BUY';
$orderType = 'LIMIT';
$contractType = 'PERPETUAL';
$interval = '5m';
$limit = 100;
$leverage = 10;

$orderBook = new OrderBook($loop, [
    'limit' => 100,
    'symbol' => 'BTCUSDT',
    'minPriceDiff' => 100
]);

$Kline = new ContractKLineData($loop, [
        'pair' => $symbol, 
        'contractType' => $contractType, 
        'interval' => $interval, 
        'limit' => 100
    ]
);

/**
 * Place a Limit Order, price and quantity can be 
 * set up on $price and $quantity properties
 */
$placeOrder = new PlaceOrder($loop, [
    'symbol' => $symbol,       // Trading pair
    'side' => $side,             // Order side
    'type' => $orderType,           // Order type
    'timeInForce' => 'GTC',      // Good Till Cancelled
    'recvWindow' => 5000,
], 
$leverage);

$logger = new Logger();

$bbRsi = new BollingerRsiStrategy(
    $Kline, 
    $placeOrder,
    $orderBook,
    $logger,
    [
        'symbol' => $symbol
    ]
);
$bbRsi->execute();


// Run the event loop
$loop->run();
