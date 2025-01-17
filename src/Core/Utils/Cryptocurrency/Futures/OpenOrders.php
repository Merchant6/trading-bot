<?php

namespace Merchant\TradingBot\Core\Utils\Cryptocurrency\Futures;

use Merchant\TradingBot\Core\Utils\Logger;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Http\Browser;
use React\Promise\PromiseInterface;

class OpenOrders
{   
    public LoopInterface $loop;
    public Browser $http;
    public string $openOrdersUrl = '';
    public array $headers = [];
    public function __construct()
    {
        $this->boot();
    }

    public function boot()
    {
        $this->loop = Loop::get();
        $this->http = new Browser();
        $this->openOrdersUrl = $_ENV['BINANCE_API_URL'] . "/fapi/v1/openOrders";
        $this->headers = [
            'X-MBX-APIKEY' => $_ENV['BINANCE_API_KEY'],
        ];
    }


    /**
     * Query all open orders of a given symbol
     * 
     * @param array $options {
     *  string symbol
     *  string|int timestamp
     * }
     * @return PromiseInterface
     */
    public function queryOpenOrders(array $options): PromiseInterface
    {   
        $httpQuery = http_build_query($options);
        $signature = hmac($httpQuery, $_ENV['BINANCE_SECRET_KEY']);
        $finalQuery = $httpQuery . '&signature=' . $signature;
        
        return $this->http->get( "$this->openOrdersUrl?$finalQuery", $this->headers)
            ->then(function (ResponseInterface $response) {

                    return json_decode($response->getBody(), true) ;

            }, function (\Exception $exception) {

                Logger::create()->info('Error setting leverage: ' . $exception->getMessage());

                if (method_exists($exception, 'getResponse')) {
                    $response = $exception->getResponse();
                    if ($response) {
                        Logger::create()->info('Leverage error response: ' . $response->getBody());
                    }
                }

                throw $exception;
            });
    }
}
