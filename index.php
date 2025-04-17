<?php

use Merchant\TradingBot\Core\Trades\Strategy\BollingerRsiStrategy;
use Merchant\TradingBot\Core\Utils\ExchangeManager;
use React\EventLoop\Loop;

use function React\Async\async;
use function React\Async\await;

require __DIR__ . "/vendor/autoload.php";

// Load environment
$dotenv = Dotenv\Dotenv::createUnsafeImmutable(__DIR__);
$dotenv->load();

// CLI options
$options = getopt("", ["symbol:"]);
if (!isset($options['symbol'])) {
    die("Error: Missing required parameter --symbol.\n");
}

// Get event loop
$loop = Loop::get();

async(function () use ($options) {

    $symbol = $options['symbol'] . "/USDT:USDT";

    $exchange = new ExchangeManager('bitget', [
        'apiKey' => getenv('BITGET_API_KEY'),
        'secret' => getenv('BITGET_SECRET_KEY'),
        'password' => getenv('BITGET_PASSWORD'),
        'enableRateLimit' => getenv('RATE_LIMIT'),
        //'verbose' => true,
        'options' => [
            'defaultType' => 'swap',
            'recvWindow' => 20000,
            'marginType' => 'cross',
        ],
    ]);

    $exchange->getExchange()->set_sandbox_mode(false);

    $side = getenv('TRADE_SIDE');
    $orderType = getenv('ORDER_TYPE');
    $contractType = getenv('CONTRACT_TYPE');
    $interval = getenv('INTERVAL');
    $limit = getenv('LIMIT');
    $amountPercentage = getenv('AMOUNT_PERCENTAGE');

    if (await($exchange->hasSymbol($symbol)) === false) {
        echo "Error: Symbol $symbol is not available on the exchange.\n";
        return;
    }

    await($exchange->getExchange()->setPositionMode(false, $symbol));

    $leverage = await($exchange->fetchMaxLeverage($symbol));
    await($exchange->setLeverage($symbol, (int)$leverage));

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

    await($bbRsi->execute());


})();

// Run the loop
$loop->run();
