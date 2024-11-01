<?php

namespace Merchant\TradingBot\Core\Utils\Cryptocurrency\Futures;

use Merchant\TradingBot\Core\Utils\Logger;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\LoopInterface;
use React\Http\Browser;

class PlaceOrder
{   
    public string $setLeverageUrl = '';
    public string $placeOrderUrl = '';
    public Browser $http;
    public array $headers = [];

    public function __construct(public LoopInterface $loop)
    {
        $this->boot();
    }

    public function boot()
    {   
        $this->setLeverageUrl = $_ENV['BINANCE_API_URL'] . "/fapi/v1/leverage";
        $this->placeOrderUrl = $_ENV['BINANCE_API_URL'] . "/fapi/v1/order";
        $this->http = new Browser(loop: $this->loop);
        $this->headers = [
            'X-MBX-APIKEY' => $_ENV['BINANCE_API_KEY'],
        ];
    }

    /**
     * Place a derivatives(futures) order
     * 
     * @param array{
     *     symbol: string
     *     side: string
     *     type: string
     *     timeInForce: string
     *     quantity: int|float
     *     price: int|float
     *     recvWindow: int
     * } $params
     * @return void
     */
    public function execute(array $params, int $leverage)
    {   
        $this->setLeverageAndPlaceOrder([
            'symbol' => $params['symbol'],
            'leverage' => $leverage,
        ], $params);

    }

    public function setLeverageAndPlaceOrder(array $options, array $params)
    {   
        $options['timestamp'] = time() * 1000;
        $options['signature'] = hmac(http_build_query($options), $_ENV['BINANCE_SECRET_KEY']);
        $this->http->post($this->setLeverageUrl, $this->headers, http_build_query($options))
            ->then(function (ResponseInterface $response) use ($params) {

                if($response->getStatusCode() == 200){
                    $this->placeOrder($params);
                }
                echo $response->getBody();

            }, function (\Exception $exception) {
                Logger::create()->info('Error setting leverage: ' . $exception->getMessage());
        });
    }

    public function placeOrder(array $params)
    {   
        $params['timestamp'] = time() * 1000;
        $params['signature'] = hmac(http_build_query($params), $_ENV['BINANCE_SECRET_KEY']);

        $this->http->post($this->placeOrderUrl, $this->headers, http_build_query($params))
            ->then(function (ResponseInterface $response) {
                echo $response->getBody();
            }, function (\Exception $exception) {
                Logger::create()->info('Error placing order: ' . $exception->getMessage());
        });
    }

}
