<?php

use Merchant\TradingBot\Core\Utils\Cryptocurrency\MarketData\KLineData;
use React\EventLoop\Loop;

require __DIR__ . "/vendor/autoload.php";

//Initializing Dotenv
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

//Initializing the event loop
$loop = Loop::get();

//Core Logic goes here
$KLineData = new KLineData($loop, [
    'symbol' => 'BTCUSDT',
    'interval' => '3m',
    'limit' => '10',
]);
$KLineData->details(function ($data) {
    echo json_encode($data, JSON_PRETTY_PRINT);
});

//Run the event loop
$loop->run();