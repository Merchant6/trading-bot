<?php

namespace Merchant\TradingBot\Core\Utils\Cryptocurrency\MarketData;

use Merchant\TradingBot\Core\Utils\Logger;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\LoopInterface;
use React\Http\Browser;

class PriceFetcher
{   
    public string $marketPriceUrl = '';
    public Browser $http;
    public int|float|string $pollInterval = 5;

    /**
     * Instantiate the PriceFetcher class
     * 
     * @param \React\EventLoop\LoopInterface $loop
     * @param string $symbol
     */
    public function __construct(
        public LoopInterface $loop, 
        public string $symbol
    ) {
        $this->boot();
    }

    /**
     * Boot the PriceFetcher class
     * @return void
     */
    public function boot(): void
    {
        $this->marketPriceUrl = $_ENV['BINANCE_API_URL'] . "/fapi/v2/ticker/price?symbol=" . $this->symbol;
        $this->http = new Browser(loop: $this->loop);
        $this->pollInterval = $_ENV['PRICE_FETCH_INTERVAL'];
    }

    /**
     * Fetch the current price of the cryptocurrency
     * pair from Binance Rest API 
     * 
     * @param callable $callable
     * @return void
     */
    public function fetch(callable $callable): void
    {
        $this->loop->addPeriodicTimer($this->pollInterval, function () use ($callable) {
            $this->http->get($this->marketPriceUrl)->then(function (ResponseInterface $response)  use ($callable) {
                $priceData = json_decode($response->getBody());

                    $priceDataArray = [
                        'ticker' => $priceData->symbol,
                        'price' => $priceData->price,
                    ];
                    
                    $callable($priceDataArray);

            }, function (\Exception $exception) use ($callable) {
                Logger::create()->info("Error fetching price: " . $exception->getMessage());
                $this->fetch($callable);
            });
        });
    }

}
