<?php

namespace Merchant\TradingBot\Core\Interfaces;

use React\Promise\PromiseInterface;

interface StrategyInterface
{   
    /**
     * Bootstraps the strategy with configuration options.
     */
    public function boot(): void;

    /**
     * Execute the trade for a given cryptocurrency
     * 
     * @return void
     */
    public function execute(): PromiseInterface;

    /**
     * Processes trade logic when conditions are met
     * 
     * @return void
     */
    public function processTrade(): void;
    
}
