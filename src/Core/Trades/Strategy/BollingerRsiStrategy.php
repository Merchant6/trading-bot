<?php

namespace Merchant\TradingBot\Core\Trades\Strategy;

use Merchant\TradingBot\Core\Utils\Cryptocurrency\Indicators\BollingerBands;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\MarketData\ContractKLineData;

/**
 * Using Bollinger Bands And Rsi to
 * trade given cryptocurrency pair
 */
class BollingerRsiStrategy
{   
    public int $leverage = 10;
    public int $period = 20;
    public int $stdDev = 2;

    /**
     * Constructor for initializing trading parameters.
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
     * @param ContractKLineData $contractKLineData
     * An instance of ContractKLineData for polling 
     * and processing K-line (candlestick) data, 
     * providing parsed historical data for trading 
     * strategies.
     * 
     */
    public function __construct(
        public ContractKLineData $contractKLineData,
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
        $this->leverage = $this->options['leverage'] ?? $this->leverage;
        $this->period = $this->options['period'] ?? $this->period;
        $this->stdDev = $this->options['stdDev'] ?? $this->stdDev;
    }   

    public function execute()
    {
        $this->contractKLineData->details(function (array $data) {
            $closePrices = array_column($data, 'close_price');

            //Set Bollinger Bands Options
            $bbOptions = [
                'prices' => $closePrices,
                'period' => $this->period,  // 20-period moving average for Bollinger Bands
                'stdDev' => $this->stdDev,   // 2 standard deviations for the bands
            ];

            $bb = new BollingerBands();
            $bands = $bb->calculate($bbOptions, TRADER_MA_TYPE_SMA);

            $upperBand = round(end($bands['UpperBand']), 3); // The most recent upper band value
            $lowerBand = round(end($bands['LowerBand']), 3); // The most recent lower band value
            $middleBand = round(end($bands['MiddleBand']), 3); // The most recent middle band value
            $currentPrice = round(end($closePrices), 3); // Current price

            if ($currentPrice < $lowerBand) {
                echo "Upper Band: $upperBand\n";
                echo "Buy signal detected. Current Price: $currentPrice\n";
                echo "Lower Band: $$lowerBand\n\n";
                // Place buy order logic here
            } elseif ($currentPrice > $upperBand) {
                echo "Upper Band: $upperBand\n";
                echo "Sell signal detected. Current Price: $currentPrice\n";
                echo "Lower Band: $$lowerBand\n\n";
                // Place sell order logic here
            } else {
                echo "Upper Band: $upperBand\n";
                echo "No trade signal. Current Price: $currentPrice\n";
                echo "Lower Band: $lowerBand\n\n";
            }
        });
    }
}
