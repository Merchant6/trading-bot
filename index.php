<?php

use Merchant\TradingBot\Core\Utils\Cryptocurrency\Futures\PlaceOrder;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\Indicators\BollingerBands;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\MarketData\ContractKLineData;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\MarketData\PriceFetcher;
use Merchant\TradingBot\Core\Utils\Logger;
use React\EventLoop\Loop;

require __DIR__ . "/vendor/autoload.php";

//Initializing Dotenv
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

//Initializing the event loop
$loop = Loop::get();

$symbol = 'BTCUSDT';
$contractType = 'PERPETUAL';
$interval = '5m';
$limit = 500;
$pollInterval = 2;

// Step 1: Fetch Historical KLine Data
$klineData = new ContractKLineData($loop, [
    'pair' => $symbol,
    'contractType' => $contractType,
    'interval' => $interval,
    'limit' => $limit,
]);

$klineData->details(function ($data) {
    var_dump($data);
});



//Run the event loop
$loop->run();