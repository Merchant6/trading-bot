<?php

use Merchant\TradingBot\Core\Utils\Cryptocurrency\MarketData\ContractKLineData;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Loop;
use React\Http\Browser;

class ContractKLineDataTest extends TestCase
{   
    private Browser $browser;
    private ResponseInterface $response;

    public function setUp(): void
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
    public function testDetailsCallsCallbackOnPromiseFulfilled()
    {
        $loop = Loop::get();

        // Initialize ContractKLineData inside the test
        $contractKLineData = new ContractKLineData($loop, [
            'pair' => 'BTCUSDT',
            'contractType' => 'PERPETUAL',
            'interval' => '1m',
            'limit' => 5
        ]);

        // Mock the successful response from Binance API
        $priceData = json_encode([
            ['1626878400000', '30000.00', '30500.00', '29500.00', '30050.00', '1000.0', '1626878450000', '30000000.00', '50', '200', '50000.0', '0']
        ]);

        $this->response->method('getBody')->willReturn($priceData);

        // Mock the get method to return a successful promise
        $this->browser->method('get')->willReturn(\React\Promise\resolve($this->response));

        // Inject the mocked browser into the class
        $contractKLineData->http = $this->browser;

        // Flag to check if callback is executed
        $callbackExecuted = false;

        // Run the details method and pass a callback
        $contractKLineData->details(function ($data) use (&$callbackExecuted, $loop) {
            // Validate that the data is correct
            $this->assertEquals('30000.00', $data['open_price']);
            $this->assertEquals('30500.00', $data['high_price']);
            $callbackExecuted = true;
            $loop->stop();
        });

        // Run the event loop
        $loop->run();

        // Ensure the callback was executed
        $this->assertTrue($callbackExecuted, 'Callback was not executed after the API call.');

        unset($loop);
    }

    public function tearDown(): void
    {
        unset($this->browser);
        unset($this->response);
    }
}
