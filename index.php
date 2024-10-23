<?php

use Merchant\TradingBot\Core\Utils\Cryptocurrency\MarketData\OrderBook;
use Merchant\TradingBot\Core\Utils\Logger;
use React\EventLoop\Loop;

require __DIR__ . "/vendor/autoload.php";

//Initializing Dotenv
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

//Initializing the event loop
$loop = Loop::get();

//Core Logic goes here
$orderBook = new OrderBook($loop, [
    'limit' => 10,
    'symbol' => 'BTCUSDT',
    'minPriceDiff' => 10,
]);

$orderBook->details(function ($details) {
    echo json_encode($details, JSON_PRETTY_PRINT);
});

//Run the event loop
$loop->run();