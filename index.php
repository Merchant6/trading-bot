<?php

use Merchant\TradingBot\Core\Trades\Strategy\BollingerRsiStrategy;
use Merchant\TradingBot\Core\Utils\ExchangeManager;
use React\EventLoop\Loop;

use function React\Async\async;
use function React\Async\await;

require __DIR__ . "/vendor/autoload.php";

// Initializing Dotenv
$dotenv = Dotenv\Dotenv::createUnsafeImmutable(__DIR__);
$dotenv->load();

// Initializing the event loop
$loop = Loop::get();

$options = getopt("", [
    "symbol:",       // Required
]);

if (!isset($options['symbol'])) {
    die("Error: Missing required parameter --symbol.\n");
}

// Using Bitget Futures
$exchange = new ExchangeManager('bitget', [
    'apiKey' => getenv('BITGET_API_KEY'),
    'secret' => getenv('BITGET_SECRET_KEY'),
    'password' => getenv('BITGET_PASSWORD'), // Required for Bitget
    'enableRateLimit' => getenv('RATE_LIMIT'),
    'verbose' => true,
    'options' => [
        'defaultType' => 'swap', // For Bitget Futures
        'recvWindow' => 20000,
        'marginType' => 'cross',
    ],
]);

$exchange->getExchange()->set_sandbox_mode(false);

// Bitget symbol format: BTC/USDT:USDT
$symbol = $options['symbol'] . "/USDT:USDT";

$side = getenv('SIDE');
$orderType = getenv('ORDER_TYPE');
$contractType = getenv('CONTRACT_TYPE');
$interval = getenv('INTERVAL');
$limit = getenv('LIMIT');
$amountPercentage = getenv('AMOUNT_PERCENTAGE');

if (await($exchange->hasSymbol($symbol)) === false) {
    die("Error: Symbol $symbol is not available on the exchange.\n");
}

$leverage = await($exchange->fetchMaxLeverage($symbol));
await($exchange->getExchange()->setLeverage($leverage, $symbol));

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

async(fn () => await($bbRsi->execute()))();

// Run the event loop
$loop->run();
