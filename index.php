<?php

use Merchant\TradingBot\Core\Trades\Strategy\BollingerRsiStrategy;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\Futures\PlaceOrder;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\Indicators\BollingerBands;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\MarketData\ContractKLineData;
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
$limit = 100;
$leverage = 10; // Example leverage

$Kline = new ContractKLineData($loop, [
        'pair' => $symbol, 
        'contractType' => $contractType, 
        'interval' => $interval, 
        'limit' => 100
    ]
);
$bbRsi = new BollingerRsiStrategy($Kline);
$bbRsi->execute();

// Run the event loop
$loop->run();
