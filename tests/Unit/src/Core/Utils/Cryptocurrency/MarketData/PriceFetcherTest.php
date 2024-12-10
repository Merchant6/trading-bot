<?php

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use React\Promise\Deferred;
use React\EventLoop\LoopInterface;
use React\Http\Browser;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\MarketData\PriceFetcher;
use Merchant\TradingBot\Core\Utils\Logger;
use React\EventLoop\Loop;

class PriceFetcherTest extends TestCase
{
    private Browser $browser;
    private ResponseInterface $response;
    private Logger $logger;

    protected function setUp(): void
    {   
        // Mock the Browser (ReactPHP HTTP Client)
        $this->browser = $this->createMock(Browser::class);

        // Mock the ResponseInterface
        $this->response = $this->createMock(ResponseInterface::class);

        //Setup ENV vars
        $_ENV['PRICE_FETCH_INTERVAL'] = 0.01;
        $_ENV['BINANCE_API_URL'] = 'https://testnet.binancefuture.com';
    }

    /**
     * @runInSeparateProcess
     */
    public function testFetchCallsCallbackOnPromiseFulfilled()
    {   
        $loop = Loop::get();
    
        // Mocked data to simulate Binance API response
        $priceData = json_encode(['symbol' => 'BTCUSDT', 'price' => '30000.00']);
        $this->response->method('getBody')->willReturn($priceData);

        //Mock Browser
        $browser = $this->browser;

        // Create a resolved promise for the mock browser
        $deferred = new Deferred();
        $deferred->resolve($this->response);
        $browser->method('get')->willReturn($deferred->promise());

        $priceFetcher = new PriceFetcher($loop, 'BTCUSDT');
        $priceFetcher->http = $browser;

        // Counter to limit the number of invocations
        $callbackExecuted = 0;
        $maxExecutions = 3;

        $priceFetcher->fetch(function ($data) use (&$callbackExecuted, $maxExecutions, $loop) {
            $this->assertEquals('BTCUSDT', $data['ticker']);
            $this->assertEquals('30000.00', $data['price']);
            $callbackExecuted++;

            // Stop the loop after a fixed number of executions
            if ($callbackExecuted >= $maxExecutions) {
                $loop->stop();
            }
        });

        // Run the event loop
        $loop->run();

        $this->assertEquals($maxExecutions, $callbackExecuted, "Callback was not executed the expected number of times.");

        unset($loop);
    }   

    public function tearDown(): void
    {
        unset($this->response);
        unset($this->browser);
    }
}
