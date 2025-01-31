<?php

use Merchant\TradingBot\Core\Trades\Strategy\BollingerRsiStrategy;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\Futures\AccountBalance;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\Futures\OpenOrders;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\Futures\PlaceOrder;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\Futures\QueryPositions;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\Indicators\BollingerBands;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\MarketData\ContractKLineData;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\MarketData\OrderBook;
use Merchant\TradingBot\Core\Utils\Logger;
use React\EventLoop\Loop;

use function React\Async\await;

require __DIR__ . "/vendor/autoload.php";

//Initializing Dotenv
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

//Initializing the event loop
$loop = Loop::get();

$symbol = 'BTCUSDT';
$side = 'BUY';
$orderType = 'MARKET';
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

$logger = logger();

$bbRsi = new BollingerRsiStrategy(
    $Kline, 
    $orderBook,
    $logger,
    [
        'symbol' => $symbol,
        'side' => $side,
        'type' => $orderType,
        'leverage' => $leverage
    ]
);
$bbRsi->execute();

// Run the event loop
$loop->run();
