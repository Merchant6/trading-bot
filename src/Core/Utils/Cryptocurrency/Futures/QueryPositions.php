<?php

namespace Merchant\TradingBot\Core\Utils\Cryptocurrency\Futures;

use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Http\Browser;
use Throwable;

class QueryPositions
{   
    public LoopInterface $loop;
    public Browser $http;
    public string $positionUrl = "";
    public array $headers = [];

    public function __construct()
    {
        $this->boot();
    }

    public function boot()
    {
        $this->loop = Loop::get();
        $this->http = new Browser();
        $this->positionUrl = $_ENV['BINANCE_API_URL'] . "/fapi/v2/positionRisk";
        $this->headers = [
            'X-MBX-APIKEY' => $_ENV['BINANCE_API_KEY'],
        ];
    }

    public function getPosition(array $options = []): \React\Promise\PromiseInterface
    {
        $httpQuery = http_build_query($options);
        $signature = hash_hmac('sha256', $httpQuery, $_ENV['BINANCE_SECRET_KEY']);
        $finalQuery = $httpQuery . '&signature=' . $signature;

        return $this->http->get("$this->positionUrl?$finalQuery", $this->headers)
            ->then(
                function (ResponseInterface $response) {
                    return json_decode($response->getBody(), true);
                },
                function (Throwable $e) {
                    throw new \RuntimeException("Failed to fetch positions: " . $e->getMessage(), 0, $e);
                }
            );
    }
}
