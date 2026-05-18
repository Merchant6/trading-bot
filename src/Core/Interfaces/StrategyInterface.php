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
     * Returns a stable strategy identifier.
     */
    public function name(): string;

    /**
     * Processes trade logic when conditions are met
     * 
     * @return void
     */
    public function processTrade(): void;

    /**
     * Checks for open orders and places a new order
     * if conditions are met.
     * 
     * @param float $currentPrice
     * @param float $quantityWithLeverage
     * @return void
     */
    public function placeOrder(float $currentPrice, float $quantityWithLeverage = 0): void;
    
}
