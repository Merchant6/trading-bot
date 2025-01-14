<?php

namespace Merchant\TradingBot\Core\Utils\Cryptocurrency\Futures;

use Exception;
use Merchant\TradingBot\Core\Utils\Logger;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Http\Browser;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use function React\Async\await;

class AccountBalance
{   
    public LoopInterface $loop;
    public Browser $http;
    public string $userBalanceUrl = '';

    public function __construct()
    {
        $this->boot();
    }

    public function boot()
    {
        $this->loop = Loop::get();
        $this->http = new Browser(loop:$this->loop);
        $this->userBalanceUrl = $_ENV['BINANCE_API_URL'] . "/fapi/v2/balance";
    }

    public function getBalance(string $asset = 'USDT'): PromiseInterface
    {
        $timestamp = round(microtime(true) * 1000); // Current timestamp in milliseconds
        $queryString = "timestamp=$timestamp";
        $signature = hash_hmac('sha256', $queryString, $_ENV['BINANCE_SECRET_KEY']);

        $url = "$this->userBalanceUrl?$queryString&signature=$signature";

        return $this->http->get($url, [
            'X-MBX-APIKEY' => $_ENV['BINANCE_API_KEY'],
        ])->then(
            function (ResponseInterface $response) use ($asset) {
                $balances = json_decode($response->getBody(), true);

                if (!is_array($balances)) {
                    throw new Exception("Invalid balance response from Binance API");
                }

                foreach ($balances as $balance) {
                    if (isset($balance['asset'], $balance['availableBalance']) && $balance['asset'] === $asset) {
                        return (float)$balance['availableBalance'];
                    }
                }

                throw new Exception("Asset '$asset' not found in balance response");
            }
        );
    }
}
