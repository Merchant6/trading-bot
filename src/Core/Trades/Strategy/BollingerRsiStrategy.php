<?php

namespace Merchant\TradingBot\Core\Trades\Strategy;

use Exception;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\Futures\AccountBalance;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\Futures\PlaceOrder;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\Indicators\BollingerBands;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\MarketData\ContractKLineData;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\MarketData\OrderBook;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Loop;

use function React\Async\await;

/**
 * Using Bollinger Bands And Rsi to
 * trade given cryptocurrency pair
 */
class BollingerRsiStrategy
{   
    /**
     * Period for Bollinger Bands 
     * @var int
     */
    public int $period = 20;

    /**
     * Standard Deviation for Bollinger Bands
     * @var int
     */
    public int $stdDev = 2;

    /**
     * Constructor for initializing trading parameters.
     * 
     * @param ContractKLineData $contractKLineData
     * An instance of ContractKLineData for polling 
     * and processing K-line (candlestick) data, 
     * providing parsed historical data for trading 
     * strategies.
     * 
     * @param array $options {
     *     The `$options` array allows fine-tuned control over the trading setup,
     *     setup leverage and bollinger bands options.
     *
     *     @type int    $leverage The leverage to use for trading, defaults to 10.
     *     @type int    $period Define period for Bollinger Bands
     *     @type int    $stdDev Define standard Deviation for Bollinger Bands
     * }
     * 
     */
    public function __construct(
        public ContractKLineData $contractKLineData,
        public PlaceOrder $placeOrder,
        public OrderBook $orderBook,
        public array $options = []
    )
    {
        $this->boot();
    }

    /**
     * Boot the BollingerRsiStrategy class
     * @return void
     */
    public function boot()
    {   
        $this->period = $this->options['period'] ?? $this->period;
        $this->stdDev = $this->options['stdDev'] ?? $this->stdDev;
    }   

    /**
     * Execute the Bollinger Bands and
     * RSI strategy
     * 
     * @return void
     */
    public function execute()
    {
        $this->contractKLineData->details(function (array $data) {
            
            $closePrices = array_column($data, 'close_price');

            //Set Bollinger Bands Options
            $bbOptions = [
                'prices' => $closePrices,
                'period' => $this->period,
                'stdDev' => $this->stdDev,
            ];

            $bb = new BollingerBands();
            $bands = $bb->calculate($bbOptions, TRADER_MA_TYPE_SMA);

            $upperBand = round(end($bands['UpperBand']), 3); // The most recent upper band value
            $lowerBand = round(end($bands['LowerBand']), 3); // The most recent lower band value
            $middleBand = round(end($bands['MiddleBand']), 3); // The most recent middle band value
            $currentPrice = round(end($closePrices), 3); // Current price

            $lastTwoCandlePrices = array_slice($closePrices, -2); 

            if (
                count($lastTwoCandlePrices) === 2 && 
                $lastTwoCandlePrices[0] > $lowerBand && 
                $lastTwoCandlePrices[1] > $lowerBand
            ) { 

                $accountBalance = new AccountBalance();
                $balancePromise = $accountBalance->getBalance();

                $balancePromise->then(
                    function (?float $userAccountBalance) use ($currentPrice) {
                       
                        if (!$userAccountBalance) {
                            echo "Not enough balance in the wallet\n";
                            return;
                        }
    
                        $this->orderBook->details(function (array $orderBookData) use ($currentPrice, $userAccountBalance) {
                            
                            if (empty($orderBookData)) {
                                echo "Not enough bids in the order book!\n";
                                return;
                            }

                            $bestAsk = $orderBookData['asks'][0]['price'];
                            $bestBid = $orderBookData['bids'][0]['price'];
            
                            /**
                             * Price Difference Between Highest Bid Price And Market Price
                             */
                            $priceDiff = abs($bestBid - $currentPrice);
            
                            // Calculate the quantity
                            $balancePercentage = 50 / 100;
                            $quantityWithLeverage = round(
                                ($userAccountBalance * $balancePercentage * $this->placeOrder->leverage) / $currentPrice,
                                3
                            );
            
                            // Place Order
                            $price = $this->placeOrder->price = $currentPrice;
                            $quantity = $this->placeOrder->quantity = $quantityWithLeverage;
                            $this->placeOrder->execute()
                                ->then(function (ResponseInterface $response) {
                                    var_dump($response);
                                })
                                ->catch(function (Exception $e) {
                                    echo $e->getMessage();
                                });
                        });
                    },
                    function (Exception $e) {
                        echo "Error fetching account balance: " . $e->getMessage() . "\n";
                    }
                );

            } else {
                echo "No trade signal. Current Price: $currentPrice\n";
            }
        });
    }
}
