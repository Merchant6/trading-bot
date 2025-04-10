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
]);


if (!isset($options['symbol'])) {
    die("Error: Missing required parameter --symbol.\n");
}

$exchange = new ExchangeManager('binanceusdm', [
    'apiKey' => getenv('BINANCE_API_KEY'),
    'secret' => getenv('BINANCE_SECRET_KEY'),
    'enableRateLimit' => getenv('RATE_LIMIT'),
    'verbose' => true
    'options' => [
	'defaultType' => 'future',
        'recvWindow' => 20000,
        'marginType' => 'cross',
    ],
]);

$exchange->getExchange()->set_sandbox_mode(false);

$symbol = $options['symbol'] . ":USDT";
$side = getenv('SIDE');
$orderType = getenv('ORDER_TYPE');
$contractType = getenv('CONTRACT_TYPE');
$interval = getenv('INTERVAL');
$limit = getenv('LIMIT');
$amountPercentage = getenv('AMOUNT_PERCENTAGE');

if(await($exchange->hasSymbol($symbol)) === false) {
    die("Error: Symbol $symbol is not available on the exchange.\n");
}

$leverage = await($exchange->fetchMaxLeverage($symbol));

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

/**
 * Execute the execute() method of any strategy inside
 * a async function, so it should be non blocking. 
 * Everything inside this function will still be blocked,
 * when using await() but everything outside this function 
 * can be executed asynchronously without blocking:
 * 
 */
async(fn () => await($bbRsi->execute()))();

// Run the event loop
$loop->run();
