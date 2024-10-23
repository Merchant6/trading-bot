<?php

namespace Merchant\TradingBot\Core\Utils\Cryptocurrency\MarketData;

use Merchant\TradingBot\Core\Utils\Logger;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\LoopInterface;
use React\Http\Browser;

class KLineData
{   
    private Browser $http;
    private string $KLineDataUrl = '';
    private int|string|float $pollInterval = 5;

    /**
     * Summary of __construct
     * @param \React\EventLoop\LoopInterface $loop
     * @param array{
     *     symbol: string,
     *     interval: string,
     *     limit: int
     * } $options
     */
    public function __construct(private LoopInterface $loop, private array$options)
    {
        $this->boot();
    }

    /**
     * Boot the KLineData class
     * 
     * @return void
     */
    public function boot(): void
    {   
        $symbol = $this->options['symbol'];
        $interval = $this->options['interval'];
        $limit = $this->options['limit'];

        $this->KLineDataUrl = $_ENV['BINANCE_API_URL'] . "/api/v3/klines?" . "symbol=$symbol&interval=$interval&limit=$limit";
        $this->http = new Browser(loop: $this->loop);
        $this->pollInterval = $_ENV['PRICE_FETCH_INTERVAL'];
    }

    /**
     * Get the KLine details
     * 
     * @param callable $callback
     * @return void
     */
    public function details(callable $callback): void
    {
        $this->loop->addPeriodicTimer($this->pollInterval, function () use($callback)  {
            $this->http->get($this->KLineDataUrl)->then(function (ResponseInterface $response) use($callback) {
                $KLineData = json_decode($response->getBody());

                $KLineData = json_decode($response->getBody(), true);

                $kline = $KLineData[0];

                $KLineDataArray = [
                    'open_time' => $kline[0],             // Kline open time
                    'open_price' => $kline[1],            // Open price
                    'high_price' => $kline[2],            // High price
                    'low_price' => $kline[3],             // Low price
                    'close_price' => $kline[4],           // Close price
                    'volume' => $kline[5],                // Volume
                    'close_time' => $kline[6],            // Kline close time
                    'quote_asset_volume' => $kline[7],    // Quote asset volume
                    'number_of_trades' => $kline[8],      // Number of trades
                    'taker_buy_base_volume' => $kline[9], // Taker buy base asset volume
                    'taker_buy_quote_volume' => $kline[10], // Taker buy quote asset volume
                    'unused_field' => $kline[11],         // Unused field
                ];
                
                $callback($KLineDataArray);
            }, function (\Exception $exception) use ($callback) {
                Logger::create()->info("Error fetching price: " . $exception->getMessage());
                $this->details($callback);
            });
        } );
    }
}
