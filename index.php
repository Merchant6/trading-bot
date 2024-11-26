<?php

use Merchant\TradingBot\Core\Utils\Cryptocurrency\Futures\PlaceOrder;
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

// $order = new PlaceOrder($loop);
// $order->execute([
//     'symbol' => 'BTCUSDT',
//     'side' => 'BUY',
//     'type' => 'LIMIT',
//     'timeInForce' => 'GTC',
//     'quantity' => 0.25,
//     'price' => 68939.9,
//     'recvWindow' => 5000
// ], 10);

$fetcher = new PriceFetcher($loop, 'BTCUSDT');
$fetcher->fetch(function ($data, $exception) {
    if($exception){
        Logger::create()->info("Error fetching price: " . $exception->getMessage());
    }

    echo json_encode($data, JSON_PRETTY_PRINT);
});

//Run the event loop
$loop->run();