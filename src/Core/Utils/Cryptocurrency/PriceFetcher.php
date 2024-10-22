<?php

namespace Merchant\TradingBot\Core\Utils\Cryptocurrency;

use Merchant\TradingBot\Core\Utils\Logger;
use Psr\Http\Message\ResponseInterface;
use Ratchet\Client\Connector as RatchetConnector;
use Ratchet\Client\WebSocket;
use React\EventLoop\LoopInterface;
use React\Socket\Connector as ReactConnector;
use Psr\Http\Message\RequestInterface;
use React\Http\Browser;

class PriceFetcher
{   
    private string $marketPriceUrl = '';
    private Browser $http;
    private int|float|string $pollInterval = 5;

    /**
     * Instantiate the class
     */
    public function __construct(
        private LoopInterface $loop, 
        private string $symbol
    ) {
        $this->boot();
    }

    /**
     * Boot the PriceFetcher class
     * @return void
     */
    public function boot(): void
    {
        $this->marketPriceUrl = $_ENV['BINANCE_API_URL'] . "/api/v3/ticker/price?symbol=" . $this->symbol;
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
            $this->http->get($this->marketPriceUrl)->then(function (ResponseInterface $response)  use($callable) {
                $priceData = json_decode($response->getBody());

                    $priceDataArray = [
                        'ticker' => $priceData->symbol,
                        'price' => $priceData->price,
                    ];
                    
                    $callable($priceDataArray);

            }, function (\Exception $exception) use ($callable) {
                Logger::create()->info("Error fetching price: " . $exception->getMessage() . "\n");
                $this->fetch($callable);
            });
        });
    }

    // public function fetch()
    // {
    //     $binanceStreamUrl = $_ENV['BINANCE_STREAM_URL'] . "/{$this->symbol}@miniTicker";

    //     $reactConnector = new ReactConnector($this->loop);
    //     $connector = new RatchetConnector($this->loop, $reactConnector);

    //     echo "Connecting to Binance WebSocket...\n";

    //     // Create a connection to the WebSocket
    //     $connector($binanceStreamUrl)->then(
    //         function (WebSocket $conn) {
    //             echo "Connected! Listening for price updates...\n";

    //             // Listen for incoming messages
    //             $conn->on('message', function ($message) {
    //                 $priceData = json_decode($message);

    //                 $priceDataArray = [
    //                     'ticker' => $priceData->s,
    //                     'price' => $priceData->c,
    //                 ];

    //                 echo json_encode($priceDataArray, JSON_PRETTY_PRINT);

    //             });

    //             // Handle ping/pong from the server
    //             $this->loop->addPeriodicTimer(180, function () use ($conn) {
    //                 echo "Sending ping\n";
    //                 $conn->send("\x89\x00"); // Sending a ping frame
    //             });

    //             // Handle connection close
    //             $conn->on('close', function ($code = null, $reason = null) {
    //                 echo "Connection closed ({$code} - {$reason})\n";
    //             });

    //             // Reconnect after 24 hours
    //             $this->loop->addTimer(86400, function () use ($conn) {
    //                 echo "Disconnecting after 24 hours...\n";
    //                 $conn->close();
    //                 $this->fetch(); // Reconnect
    //             });
    //         },
    //         function (\Exception $e) {
    //             echo "Could not connect: {$e->getMessage()}\n";
    //         }
    //     );
    // }
}
