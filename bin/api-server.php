<?php

use Merchant\TradingBot\Core\Api\TradingApi;
use Merchant\TradingBot\Core\Trading\Config;
use React\EventLoop\Loop;
use React\Http\HttpServer;
use React\Socket\SocketServer;

require __DIR__ . '/../vendor/autoload.php';

if (is_file(__DIR__ . '/../.env')) {
    Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
}

$loop = Loop::get();
$host = Config::value('trading', 'api_host', '127.0.0.1');
$port = Config::value('trading', 'api_port', 8080);
$api = new TradingApi();

$server = new HttpServer($loop, fn ($request) => $api->handle($request));
$socket = new SocketServer("{$host}:{$port}", [], $loop);
$server->listen($socket);

echo "Trading API server running at http://{$host}:{$port}\n";

$loop->run();
