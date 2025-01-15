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

    public function __construct(
        public LoopInterface $loop,
        public array $params,
        public int $leverage = 10
    ) {
        $this->boot();
    }

    public function boot()
    {
        $this->setLeverageUrl = $_ENV['BINANCE_API_URL'] . "/fapi/v1/leverage";
        $this->placeOrderUrl = $_ENV['BINANCE_API_URL'] . "/fapi/v1/order";
        $this->http = new Browser(loop: $this->loop);
        $this->headers = [
            'X-MBX-APIKEY' => $_ENV['BINANCE_API_KEY'],
            'Content-Type' => 'application/x-www-form-urlencoded'
        ];
    }

    public function execute()
    {
        return $this->setLeverageAndPlaceOrder([
            'symbol' => $this->params['symbol'],
            'leverage' => (string)$this->leverage,
        ], $this->params);
    }

    public function setLeverageAndPlaceOrder(array $options, array $params)
    {
        $options['timestamp'] = time() * 1000;
        ksort($options);
        $options['signature'] = hmac(http_build_query($options), $_ENV['BINANCE_SECRET_KEY']);

        return $this->http->post($this->setLeverageUrl, $this->headers, http_build_query($options))
            ->then(
                function (ResponseInterface $response) use ($params) {
                    $body = (string)$response->getBody();
                    Logger::create()->info('Leverage response: ' . $body);
                    if($response->getStatusCode() == 200){
                        return $this->placeOrder($params);
                    }
                    throw new \Exception('Failed to set leverage: ' . $body);
                },
                function (\Exception $exception) {
                    Logger::create()->info('Error setting leverage: ' . $exception->getMessage());
                    if (method_exists($exception, 'getResponse')) {
                        $response = $exception->getResponse();
                        if ($response) {
                            Logger::create()->info('Leverage error response: ' . $response->getBody());
                        }
                    }
                    throw $exception;
                }
            );
    }

    public function placeOrder(array $params)
    {
        // Format parameters properly
        $orderParams = [
            'symbol' => $params['symbol'],
            'side' => $params['side'],
            'type' => $params['type'],
            'timeInForce' => $params['timeInForce'],
            'price' => number_format($this->price, 2, '.', ''), // Format price with 2 decimals
            'quantity' => number_format($this->quantity, 8, '.', ''), // Format quantity with 8 decimals
            'recvWindow' => $params['recvWindow'] ?? 5000,
            'timestamp' => time() * 1000
        ];

        // Sort parameters alphabetically
        ksort($orderParams);

        Logger::create()->info('Order parameters before signature: ' . json_encode($orderParams));

        // Generate signature with sorted parameters
        $queryString = http_build_query($orderParams);
        $orderParams['signature'] = hash_hmac('sha256', $queryString, $_ENV['BINANCE_SECRET_KEY']);

        Logger::create()->info('Final order request: ' . http_build_query($orderParams));

        return $this->http->post($this->placeOrderUrl, $this->headers, http_build_query($orderParams))
            ->then(
                function (ResponseInterface $response) {
                    $body = (string)$response->getBody();
                    Logger::create()->info('Order response: ' . $body);
                    if ($response->getStatusCode() !== 200) {
                        throw new \Exception('Order failed: ' . $body);
                    }
                    return $response;
                },
                function (\Throwable $exception) {
                    Logger::create()->info('Error placing order: ' . $exception->getMessage());
                    if (method_exists($exception, 'getResponse')) {
                        $response = $exception->getResponse();
                        if ($response) {
                            Logger::create()->info('Order error response: ' . $response->getBody());
                        }
                    }
                    throw $exception;
                }
            );
    }
}