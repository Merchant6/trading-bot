<?php

namespace Merchant\TradingBot\Core\Interfaces;

interface StrategyInterface
{
    /**
     * Execute the trade for a given cryptocurrency
     * 
     * @return void
     */
    public function execute(): void;

    /**
     * Processes trade logic when conditions are met
     * 
     * @param float $currentPrice
     * @return void
     */
    public function processTrade(float $currentPrice): void;

    /**
     * Checks for open orders and places a new order
     * if conditions are met.
     * 
     * @param float $currentPrice
     * @param float $quantityWithLeverage
     * @return void
     */
    public function checkAndPlaceOrder(float $currentPrice, float $quantityWithLeverage): void;
    
}
