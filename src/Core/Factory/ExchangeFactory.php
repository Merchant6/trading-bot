<?php

namespace Merchant\TradingBot\Core\Factory;

use ccxt\async\Exchange;
use Exception;

class ExchangeFactory
{
    public static function create(string $exchange, array $config): Exchange
    {
        $exchange = strtolower(strtolower($exchange));

        $className = "\\ccxt\\async\\{$exchange}";
        if (!class_exists($className)) {
            throw new Exception("Exchange {$exchange} is not supported.");
        }

        return new $className($config);
    }
}
