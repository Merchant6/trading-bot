<?php

use Merchant\TradingBot\Core\Trades\Strategy\BollingerRsiStrategy;
use Merchant\TradingBot\Core\Utils\ExchangeManager;
use React\EventLoop\Loop;

use function React\Async\async;
use function React\Async\await;

require __DIR__ . "/vendor/autoload.php";

//Initializing Dotenv
$dotenv = Dotenv\Dotenv::createUnsafeImmutable(__DIR__);
$dotenv->load();

//Initializing the event loop
$loop = Loop::get();

$options = getopt("", [
    "symbol:",       // Required
    "side::",        // Optional (default: BUY)
    "ordertype::",   // Optional (default: MARKET)
    "contractType::",// Optional (default: PERPETUAL)
    "interval::",    // Optional (default: 5m)
    "limit::",       // Optional (default: 100)
    "leverage:",     // Required
    "amountPercentage::", // Optional (default: 5)
]);

if (!isset($options['symbol']) || !isset($options['leverage'])) {
    die("Error: Missing required parameters --symbol and --leverage.\n");
}

$symbol = $options['symbol'];
$side = $options['side'] ?? 'BUY';
$orderType = $options['ordertype'] ?? 'MARKET';
$contractType = $options['contractType'] ?? 'PERPETUAL';
$interval = $options['interval'] ?? '5m';
$limit = isset($options['limit']) ? (int)$options['limit'] : 100;
$leverage = (int) $options['leverage'];
$amountPercentage = isset($options['amountPercentage']) ? (int)$options['amountPercentage'] : 20;

$exchange = new ExchangeManager('binanceusdm', [
    'apiKey' => getenv('BINANCE_API_KEY'),
    'secret' => getenv('BINANCE_SECRET_KEY'),
    'enableRateLimit' => getenv('RATE_LIMIT'),
    'verbose' => true,
    'options' => [
        'defaultType' => 'future',
    ]
]);
$exchange->getExchange()->set_sandbox_mode(getenv('SANDBOX'));

$bbRsi = new BollingerRsiStrategy(
    $exchange,
    [
        'symbol' => $symbol,
        'side' => $side,
        'type' => $orderType,
        'contractType' => $contractType,
        'interval' => $interval,
        'limit' => $limit,
        'leverage' => $leverage,
        'amountPercentage' => $amountPercentage
    ]
);

$bbRsi->execute();

// Run the event loop
$loop->run();
