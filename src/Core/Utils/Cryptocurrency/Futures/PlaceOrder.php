<?php

namespace Merchant\TradingBot\Core\Utils\Cryptocurrency\Futures;

use Merchant\TradingBot\Core\Utils\Logger;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\LoopInterface;
use React\Http\Browser;
use React\Promise\PromiseInterface;

class PlaceOrder
{   
    public int|float $price = 0;
    public int|float $quantity = 0;
    public string $setLeverageUrl = '';
    public string $placeOrderUrl = '';
    public Browser $http;
    public array $headers = [];

    /**
     * Setup the order for derivative trading
     * @param \React\EventLoop\LoopInterface $loop
     * @param array $params{
     *     symbol: string
     *     side: string
     *     type: string
     *     timeInForce: string
     *     quantity: int|float
     *     recvWindow: int
     * }
     * @param int $leverage
     */
    public function __construct(
        public LoopInterface $loop, 
        public array $params, 
        public int $leverage = 10
    )
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
     * @return PromiseInterface
     */
    public function execute()
    {   
        return $this->setLeverageAndPlaceOrder([
            'symbol' => $this->params['symbol'],
            'leverage' => $this->leverage,
        ], $this->params);

    }

    public function setLeverageAndPlaceOrder(array $options, array $params)
    {   
        $options['timestamp'] = time() * 1000;
        $options['signature'] = hmac(http_build_query($options), $_ENV['BINANCE_SECRET_KEY']);

        return $this->http->post($this->setLeverageUrl, $this->headers, http_build_query($options))
            ->then(function (ResponseInterface $response) use ($params) {

                if($response->getStatusCode() == 200){
                    return $this->placeOrder($params);
                }
            }, function (\Exception $exception) {
                Logger::create()->info('Error setting leverage: ' . $exception->getMessage());
        });
    }

    public function placeOrder(array $params)
    {   
        $params['timestamp'] = time() * 1000;
        $params['signature'] = hmac(http_build_query($params), $_ENV['BINANCE_SECRET_KEY']);
        $params['price'] = $this->price;
        $params['quantity'] = $this->quantity;
        
        return $this->http->post($this->placeOrderUrl, $this->headers, http_build_query($params))
            ->then(function (ResponseInterface $response) {
                
                return $response;

            }, function (\Exception $exception) {
                Logger::create()->info('Error placing order: ' . $exception->getMessage());
        });
    }

}
