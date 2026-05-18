<?php

use Merchant\TradingBot\Core\Trading\Config;
use Merchant\TradingBot\Core\Trading\StrategyRegistry;
use Merchant\TradingBot\Core\Trading\TradingStateRepository;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\MarketData\ContractKLineData;
use React\EventLoop\Loop;

require __DIR__ . "/vendor/autoload.php";

//Initializing Dotenv
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

//Initializing the event loop
$loop = Loop::get();

$options = getopt("", [
    "symbol:",       // Required
    "strategy::",    // Optional
    "side::",        // Optional (default: BUY)
    "ordertype::",   // Optional (default: MARKET)
    "contractType::",// Optional (default: PERPETUAL)
    "interval::",    // Optional (default: 5m)
    "limit::",       // Optional (default: 100)
    "leverage:",     // Required
    "paper::",       // Optional (default from config)
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
$strategyName = $options['strategy'] ?? Config::value('strategies', 'default', 'bollinger-rsi');
$paperTrading = array_key_exists('paper', $options)
    ? filter_var($options['paper'], FILTER_VALIDATE_BOOLEAN)
    : Config::value('trading', 'paper_trading', true);

$kline = new ContractKLineData($loop, [
        'pair' => $symbol, 
        'contractType' => $contractType, 
        'interval' => $interval, 
        'limit' => $limit
    ]
);

$state = new TradingStateRepository();
$state->update([
    'running' => true,
    'paper_trading' => $paperTrading,
    'strategy' => $strategyName,
    'symbol' => $symbol,
    'interval' => $interval,
    'updated_at' => date(DATE_ATOM),
]);

$registry = new StrategyRegistry();
$strategy = $registry->create(
    $strategyName,
    $kline,
    [
        'symbol' => $symbol,
        'side' => $side,
        'type' => $orderType,
        'leverage' => $leverage,
        'paper_trading' => $paperTrading,
    ]
);

$strategy->execute();

// Run the event loop
$loop->run();
