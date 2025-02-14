<?php

namespace Merchant\TradingBot\Core\Utils;

use ccxt\Exchange;
use Merchant\TradingBot\Core\Factory\ExchangeFactory;
use React\Promise\PromiseInterface;
use Throwable;

use function React\Async\async;
use function React\Async\await;

class ExchangeManager
{
    public ?Exchange $exchange = null;

    public function __construct(string $exchangeName, array $config = [])
    {
        $this->exchange = ExchangeFactory::create($exchangeName, $config);
    }

    public function getExchange(): Exchange
    {
        return $this->exchange;
    }

    public function fetchBalance(): PromiseInterface
    {
        return async(function () {
            try{
                return await($this->exchange->fetch_balance());
            } catch (Throwable $e) {
                logger()->error("Error fetching balance: " . $e->getMessage());
            }
        })();
    }

    public function placeOrder(string $symbol, string $side, float $amount, ?float $price = null): PromiseInterface
    {
        return async(function () use ($symbol, $side, $amount, $price) {
            $params = ['type' => $price ? 'limit' : 'market'];
            return await($this->exchange->create_order($symbol, $params['type'], $side, $amount, $price, $params));
        })();
    }

    public function fetchOrderBook(string $symbol, ?int $limit = null): PromiseInterface
    {
        return async(function () use ($symbol, $limit) {
            try {
                return await($this->exchange->fetch_order_book($symbol, $limit));
            } catch (Throwable $e) {
                logger()->error("Error fetching order book for {$symbol}: " . $e->getMessage());
            }
        })();
    }

    public function fetchTicker(string $symbol): PromiseInterface
    {
        return async(function () use ($symbol) {
            return await($this->exchange->fetch_ticker($symbol));
        })();
    }

    public function fetchOHLCV(string $symbol, $timeframe = '5m', ?int $since = null, ?int $limit = null, array $params = []): PromiseInterface
    {
        return async(function () use ($symbol, $timeframe, $since, $limit, $params) {
            return await($this->exchange->fetch_ohlcv($symbol, $timeframe, $since, $limit, $params));
        })();
    }

    public function fetchClosePrice(string $symbol, $timeframe = '5m', int|null $since = null, int|null $limit = null, $params = [])
    {
        return async(function () use ($symbol, $timeframe, $since, $limit, $params) {
            try {
                $ohlcv = await($this->fetchOHLCV($symbol, $timeframe, $since, $limit, $params));
                
                // Ensure $ohlcv is not empty before extracting close prices
                return !empty($ohlcv) ? array_column($ohlcv, 4) : [];
            } catch (Throwable $e) {
                logger()->error("Error fetching close prices for {$symbol}: " . $e->getMessage());
                return [];
            }
        })();
    }
}
