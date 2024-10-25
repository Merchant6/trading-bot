<?php

use Merchant\TradingBot\Core\Utils\Cryptocurrency\MarketData\ContractKLineData;
use React\EventLoop\Loop;

require __DIR__ . "/vendor/autoload.php";

//Initializing Dotenv
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

//Initializing the event loop
$loop = Loop::get();

$klineContract = new ContractKLineData($loop, [
    'pair' => 'BTCUSDT',
    'contractType' => 'PERPETUAL',
    'interval' => '1m',
    'limit' => 10
]);
$klineContract->details(function ($data) {
    echo json_encode($data, JSON_PRETTY_PRINT);
});

//Run the event loop
$loop->run();