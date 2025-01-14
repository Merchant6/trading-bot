<?php

namespace Merchant\TradingBot\Core\Utils\Cryptocurrency\MarketData;

use Merchant\TradingBot\Core\Utils\Logger;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\LoopInterface;
use React\Http\Browser;

class OrderBook
{   
    public Browser $http;
    public string $orderBookUrl = '';
    public int|string|float $pollInterval = 5;

    /**
     * Instantiate the OrderBook class.
     *
     * @param \React\EventLoop\LoopInterface $loop
     * @param array $options {
     *     limit: int|string,  // Limit for the number of results to fetch
     *     symbol: string,     // The trading pair symbol (e.g., 'BTCUSDT')
     *     minPriceDiff: float // Minimum price difference for filtering bids/asks
     * }
     */
    public function __construct(
            public LoopInterface $loop, 
            public array $options
        )
    {
        $this->boot();
    }

    /**
     * Boots the OrderBook class
     * 
     * @return void
     */
    public function boot(): void
    {
        $this->http = new Browser(loop: $this->loop);

        $limit = $this->options['limit'];
        $symbol = $this->options['symbol'];
        $this->orderBookUrl = $_ENV['BINANCE_API_URL'] . "/fapi/v1/depth?" . "limit=$limit&symbol=$symbol";
        $this->pollInterval = $_ENV['PRICE_FETCH_INTERVAL'];
    }

    /**
     * Get the order book details for a speicifc cryptocurrency
     * pair with filtered bids and asks according to the minimum
     * price difference
     * 
     * @param callable $callback
     * @return void
     */
    public function details(callable $callback): void
    {
        $this->loop->addPeriodicTimer($this->pollInterval, function () use($callback)  {
            $this->http->get($this->orderBookUrl)->then(function (ResponseInterface $response) use($callback) {
                $orderBookData = json_decode($response->getBody());

                $orderBookDataArray = [
                    'symbol' => $this->options['symbol'],
                    'bids' => $this->filterBidsAndAsksWithDifference(
                        $orderBookData->bids, 
                        $this->options['minPriceDiff']
                    ),
                    'asks' => $this->filterBidsAndAsksWithDifference(
                        $orderBookData->asks, 
                        $this->options['minPriceDiff']
                    ),
                ];

                $callback($orderBookDataArray);
            }, function (\Exception $exception) use ($callback) {
                Logger::create()->info("Error fetching price: " . $exception->getMessage());
                $this->details($callback);
            });
        } );
    }
    
    /**
     * Filter the bids and ask according to the minimum
     * price difference
     * 
     * @param array $data
     * @param int|float $minDifference
     * @return array{price: float, quantity: float[]}
     */
    private function filterBidsAndAsksWithDifference(array $data, int|float $minDifference): array
    {
        $filteredData = [];
        $previousDataPrice = null;

        foreach ($data as $item) {
            $price = (float) $item[0];
            $quantity = (float) $item[1];

            if ($previousDataPrice === null || $price <= $previousDataPrice - $minDifference) {
                $filteredData[] = ['price' => $price, 'quantity' => $quantity];
                $previousDataPrice = $price;
            }
        }

        return $filteredData;
    }

}
