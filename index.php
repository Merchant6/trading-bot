<?php

use Merchant\TradingBot\Core\Utils\Cryptocurrency\PriceFetcher;
use Merchant\TradingBot\Core\Utils\Logger;
use React\EventLoop\Loop;

require __DIR__ . "/vendor/autoload.php";

//Initializing Dotenv
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

//Initializing the event loop
$loop = Loop::get();

//Core Logic goes here
$fetcher = new PriceFetcher($loop, 'BTCUSDT');
$fetcher->fetch(function ($priceData) {
    echo json_encode($priceData, JSON_PRETTY_PRINT);
});

//Run the event loop
$loop->run();