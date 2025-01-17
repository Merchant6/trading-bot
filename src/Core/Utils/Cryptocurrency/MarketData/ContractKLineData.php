<?php

namespace Merchant\TradingBot\Core\Utils\Cryptocurrency\MarketData;

use Merchant\TradingBot\Core\Utils\Logger;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\LoopInterface;
use React\Http\Browser;

class ContractKLineData
{
    public Browser $http;
    public string $KLineDataUrl = '';
    public int|string|float $pollInterval = 5;

    /**
     * Summary of __construct
     * @param \React\EventLoop\LoopInterface $loop
     * @param array $options {
     *     pair: string,
     *     contractType: string
     *     interval: int,
     *     limit: int
     * }
     */
    public function __construct(public LoopInterface $loop, public array $options)
    {
        $this->boot();
    }

    /**
     * Boot the ContractKLineData class
     * 
     * @return void
     */
    public function boot(): void
    {   
        $pair = $this->options['pair'];
        $contractType = $this->options['contractType'];
        $interval = $this->options['interval'];
        $limit = $this->options['limit'];

        $this->KLineDataUrl = $_ENV['BINANCE_API_URL'] . "/fapi/v1/continuousKlines?" . "pair=$pair&contractType=$contractType&interval=$interval&limit=$limit";
        $this->http = http();
        $this->pollInterval = $_ENV['PRICE_FETCH_INTERVAL'];
    }

    /**
     * Get the KLine details of a specific
     * contract type ['PERPETUAL', 
     * CURRENT_QUARTER, NEXT_QUARTER]
     * 
     * @param callable $callback
     * @return void
     */
    public function details($callback)
    {
        $this->loop->addPeriodicTimer($this->pollInterval, function () use($callback)  {
            $this->http->get($this->KLineDataUrl)->then(function (ResponseInterface $response) use($callback) {
                $KLineData = json_decode($response->getBody(), true);

                $parsedKlineData = array_map(function ($kline) {
                    return [
                        'open_time' => $kline[0],             // Kline open time
                        'open_price' => $kline[1],            // Open price
                        'high_price' => $kline[2],            // High price
                        'low_price' => $kline[3],             // Low price
                        'close_price' => $kline[4],           // Close price
                        'volume' => $kline[5],                // Volume
                        'close_time' => $kline[6],            // Kline close time
                        'quote_asset_volume' => $kline[7],    // Quote asset volume
                        'number_of_trades' => $kline[8],      // Number of trades
                        'taker_buy_base_volume' => $kline[9], // Taker buy volume
                        'taker_buy_quote_volume' => $kline[10], // Taker buy quote asset volume
                        'unused_field' => $kline[11],         // Unused field
                    ];
                }, $KLineData);
                
                $callback($parsedKlineData);
            })
            ->catch(function ($exception) use($callback) {

                //Log The Exception
                Logger::create()->info("Error fetching Contact KLine Data: " . $exception->getMessage());
                
                // Schedule the fetch method to run again after a delay (retry logic)
                $this->loop->addTimer($this->pollInterval * 2, function () use($callback) {
                    $this->details($callback);
                });
            });
        } );
    }
}
