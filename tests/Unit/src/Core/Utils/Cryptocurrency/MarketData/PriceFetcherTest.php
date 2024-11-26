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
    private LoopInterface $loop;
    private Browser $browser;
    private ResponseInterface $response;
    private Logger $logger;

    protected function setUp(): void
    {
        // Mock the LoopInterface
        $this->loop = Loop::get();

        // Mock the Browser (ReactPHP HTTP Client)
        $this->browser = $this->createMock(Browser::class);

        // Mock the ResponseInterface
        $this->response = $this->createMock(ResponseInterface::class);

        // Mock the Logger
        $this->logger = $this->getMockBuilder(Logger::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['info'])
            ->getMock();

        //Setup ENV vars
        $_ENV['PRICE_FETCH_INTERVAL'] = 0.01;
        $_ENV['BINANCE_API_URL'] = 'https://testnet.binancefuture.com';
    }

    public function testFetchCallsCallbackOnPromiseFulfilled()
    {
        // Mocked data to simulate Binance API response
        $priceData = json_encode(['symbol' => 'BTCUSDT', 'price' => '30000.00']);
        $this->response->method('getBody')->willReturn($priceData);

        // Create a resolved promise for the mock browser
        $deferred = new Deferred();
        $deferred->resolve($this->response);
        $this->browser->method('get')->willReturn($deferred->promise());

        $priceFetcher = new PriceFetcher($this->loop, 'BTCUSDT');
        $priceFetcher->http = $this->browser;

        // Counter to limit the number of invocations
        $callbackExecuted = 0;
        $maxExecutions = 3;

        $priceFetcher->fetch(function ($data, $exception) use (&$callbackExecuted, $maxExecutions) {
            $this->assertEquals('BTCUSDT', $data['ticker']);
            $this->assertEquals('30000.00', $data['price']);
            $callbackExecuted++;

            // Stop the loop after a fixed number of executions
            if ($callbackExecuted >= $maxExecutions) {
                $this->loop->stop();
            }
        });

        // Run the event loop
        $this->loop->run();

        $this->assertEquals($maxExecutions, $callbackExecuted, "Callback was not executed the expected number of times.");
    }

    // public function testFetchCallsCallbackOnPromiseRejected()
    // {
    //     // Simulate a rejected promise with an exception
    //     $exception = new \Exception('Network error');
    //     $deferred = new Deferred();
    //     $deferred->reject($exception);

    //     $this->browser->method('get')->willReturn($deferred->promise());

    //     $priceFetcher = new PriceFetcher($this->loop, 'BTCUSDT');
    //     $priceFetcher->http = $this->browser;

    //     // Counter to track callback executions
    //     $callbackExecuted = 0;
    //     $maxExecutions = 3;

    //     $priceFetcher->fetch(function ($data, $exception) use (&$callbackExecuted, $maxExecutions) {
    //         $this->assertNull($data, "Data should be null on promise rejection.");
    //         $callbackExecuted++;

    //         // Stop the loop after a fixed number of executions
    //         if ($callbackExecuted >= $maxExecutions) {
    //             $this->loop->stop();
    //         }
    //     });

    //     // Run the event loop
    //     $this->loop->run();

    //     $this->assertEquals($maxExecutions, $callbackExecuted, "Callback was not executed the expected number of times.");
    // }

}
