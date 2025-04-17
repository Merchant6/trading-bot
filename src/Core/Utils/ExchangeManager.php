<?php

namespace Merchant\TradingBot\Core\Utils;

use ccxt\Exchange;
use Exception;
use Merchant\TradingBot\Core\Factory\ExchangeFactory;
use React\EventLoop\Loop;
use React\Promise\PromiseInterface;
use Throwable;

use function React\Async\async;
use function React\Async\await;
use function React\Promise\Timer\sleep;
use function React\Promise\all;

class ExchangeManager
{
    public ?Exchange $exchange = null;

    /**
     * @param string $exchangeName
     * @param array $config
     */
    public function __construct(string $exchangeName, array $config = [])
    {
        $this->exchange = ExchangeFactory::create($exchangeName, $config);
    }

    /**
     * @return Exchange|null
     */
    public function getExchange(): Exchange
    {
        return $this->exchange;
    }

    /**
     * Fetch user account balance for
     * multiple currency pairs
     * 
     * @return PromiseInterface
     */
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

    /**
     * Fetch user account balance for a specific asset
     * 
     * @param string $asset
     * @return PromiseInterface
     */
    public function fetchAccountBalance(string $asset = 'USDT'): PromiseInterface
    {
        return async(function () use ($asset) {
            try {
                $balances = await($this->fetchBalance());
                
                if (!is_array($balances)) {
                    throw new Exception("Invalid balance response from API");
                }

                $balance = $balances[$asset] ?? 0;
                return $balance['free'] ?? 0;
            } catch (Throwable $e) {
                logger()->error("Error fetching account balance: " . $e->getMessage());
            }
        })();
    }

    /**
     * Set leverage for a specified symbol
     * 
     * @param string $symbol
     * @param int $leverage
     * @return PromiseInterface
     */
    public function setLeverage(string $symbol, int $leverage = 10): PromiseInterface
    {
        return async(function () use($symbol, $leverage) {
            try {
                return await($this->exchange->set_leverage($leverage, $symbol));
            } catch (Throwable $e) {
                logger()->error("Error setting leverage: " . $e->getMessage());
            }
        })();
    }

    /**
     * Fetch max leverage for a specified symbol
     * 
     * @param string $symbol
     * @return PromiseInterface
     */
    public function fetchMaxLeverage(string $symbol): PromiseInterface
    {
        return async(function () use($symbol) {
            try {
                $info = await($this->exchange->fetchMarketLeverageTiers($symbol));
                return (int)max(array_map(
                    fn($subArray) => $subArray['maxLeverage'] ?? 0, 
                    $info
                ));
            } catch (Throwable $e) {
                logger()->error("Error fetching max leverage: " . $e->getMessage());
            }
        })();
    }

    /**
     * Fetch market leverage tiers for a specified symbol
     * 
     * @param string $symbol
     * @return PromiseInterface
     */
    public function fetchMarketLeverageTiers(string $symbol): PromiseInterface
    {
        return async(function () use($symbol) {
            try {
                $info = await($this->exchange->fetchMarketLeverageTiers($symbol));
                return array_map(
                    fn($subArray) => $subArray['maxLeverage'] ?? 0, 
                    $info
                );
            } catch (Throwable $e) {
                logger()->error("Error fetching market leverage tiers: " . $e->getMessage());
            }
        })();
    }

    /**
     * Place an order
     * 
     * @param string $symbol
     * @param string $type
     * @param string $side
     * @param float $amount
     * @param mixed $price
     * @return PromiseInterface
     */
    public function placeOrder(string $symbol, string $type, string $side, float $amount, ?float $price = null, ?array $params = []): PromiseInterface
    {
        return async(function () use ($symbol, $type, $side, $amount, $price, $params) {
            try{
                return await($this->exchange->create_order(
                    $symbol, 
                    $type, 
                    $side, 
                    $amount, 
                    $price,
                    $params
                ));
            } catch (Throwable $e) {
                logger()->error("Error placing order for {$symbol}: " . $e->getMessage());
            }
        })();
    }

    /**
     * Fetch ordebook information
     * 
     * @param string $symbol
     * @param mixed $limit
     * @return PromiseInterface
     */
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

    /**
     * Fetch ticker information
     * 
     * @param string $symbol
     * @return PromiseInterface
     */
    public function fetchTicker(string $symbol): PromiseInterface
    {
        return async(function () use ($symbol) {
            try {
                return await($this->exchange->fetch_ticker($symbol));
            } catch (Throwable $e) {
                logger()->error("Error fetching ticker for {$symbol}: " . $e->getMessage());
            }
        })();
    }

    /**
     * Fetch OHLCV data,
     * OPEN, HIGH, LOW, CLOSE, VOLUME
     * 
     * @param string $symbol
     * @param mixed $timeframe
     * @param mixed $since
     * @param mixed $limit
     * @param array $params
     * @return PromiseInterface
     */
    public function fetchOHLCV(string $symbol, $timeframe = '5m', ?int $since = null, ?int $limit = null, array $params = []): PromiseInterface
    {
        return async(function () use ($symbol, $timeframe, $since, $limit, $params) {
            try {
                return await($this->exchange->fetch_ohlcv(
                    $symbol, 
                    $timeframe, 
                    $since, 
                    $limit, 
                    $params
                ));
            } catch (Throwable $e) {
                logger()->error("Error fetching OHLCV for {$symbol}: " . $e->getMessage());
            }
        })();
    }

    /**
     * Fetch close prices
     * 
     * @param string $symbol
     * @param mixed $timeframe
     * @param mixed $since
     * @param mixed $limit
     * @param array $params
     * @return PromiseInterface
     */
    public function fetchClosePrice(string $symbol, $timeframe = '5m', int|null $since = null, int|null $limit = null, $params = []): PromiseInterface
    {
        return async(function () use ($symbol, $timeframe, $since, $limit, $params) {
            try {
                $ohlcv = await($this->fetchOHLCV(
                    $symbol, 
                    $timeframe, 
                    $since, 
                    $limit, 
                    $params
                ));
                
                // Ensure $ohlcv is not empty before extracting close prices
                return !empty($ohlcv) ? array_column($ohlcv, 4) : [];
            } catch (Throwable $e) {
                logger()->error("Error fetching close prices for {$symbol}: " . $e->getMessage());
                return [];
            }
        })();
    }

    public function fetchContinuousClosePrice(callable $callback, string $symbol, $timeframe = '1m', int|null $since = null, int|null $limit = null, $params = [])
    {   
        static $timer;

        if ($timer) {
            Loop::get()->cancelTimer($timer);
        }

        $interval = getenv('POLLING_INTERVAL');
        $timer = Loop::get()->addPeriodicTimer($interval, async(function () use ($callback, $symbol, $timeframe, $since, $limit, $params, &$timer) {
            try{
                $ohlcv = await($this->fetchClosePrice(
                    $symbol, 
                    $timeframe, 
                    $since, 
                    $limit, 
                    $params
                ));
                
                $callback($ohlcv);
            } catch (Throwable $e) {
                logger()->error("Error fetching close prices for {$symbol}: " . $e->getMessage());

                // Cancel timer and enqueue restart after 5 seconds
                Loop::get()->cancelTimer($timer);
                
                sleep(5)->then(function () use ($callback, $symbol, $timeframe, $since, $limit, $params) {
                    $this->fetchContinuousClosePrice($callback, $symbol, $timeframe, $since, $limit, $params);
                });
            }
        }));
    }

    /**
     * Fetch Bid and Ask prices of a symbol
     * 
     * @param string $symbol
     * @return PromiseInterface
     */
    public function fetchBidAsk(string $symbol): PromiseInterface
    {
        return async(function () use ($symbol) {
            try {
                return await($this->exchange->fetch_bids_asks([$symbol]));
            } catch (Throwable $e) {
                logger()->error("Error fetching bid/ask for {$symbol}: " . $e->getMessage());
            }
        })();
    }

    /**
     * Fetch open orders
     * 
     * @param string $symbol
     * @param mixed $limit
     * @param mixed $since
     * @param array $params
     * @return PromiseInterface
     */
    public function fetchOpenOrders(string $symbol, ?int $limit = null, ?int $since = null, array $params = []): PromiseInterface
    {
        return async(function () use ($symbol, $limit, $since, $params) {
            try {
                return await($this->exchange->fetch_open_orders(symbol: $symbol, limit: $limit, since: $since, params:$params));
            } catch (Throwable $e) {
                logger()->error("Error fetching open orders for {$symbol}: " . $e->getMessage());
            }
        })();
    }
    
    /**
     * Fetch closed orders
     * 
     * @param string $symbol
     * @param mixed $limit
     * @param mixed $since
     * @param array $params
     * @return PromiseInterface
     */
    public function fetchClosedOrders(string $symbol, ?int $limit = null, ?int $since = null, array $params = []): PromiseInterface
    {
        return async(function () use ($symbol, $limit, $since, $params) {
            try {
                return await($this->exchange->fetch_closed_orders(symbol: $symbol, limit: $limit, since: $since, params:$params));
            } catch (Throwable $e) {
                logger()->error("Error fetching closed orders: " . $e->getMessage());
            }
        })();
    }

    public function fetchSellOrders(string $symbol, ?int $limit = null, ?int $since = null, array $params = []): PromiseInterface
    {
        return async(function () use ($symbol, $limit, $since, $params) {
            try {
                $orders = await($this->fetchClosedOrders(symbol: $symbol, limit: $limit, since: $since, params:$params));
                $sellOrders = array_filter($orders, function ($order) {
                    return $order['status'] === 'closed' && $order['side'] === 'sell';
                });
    
                return array_values($sellOrders);
                
            } catch (Throwable $e) {
                logger()->error("Error fetching sell orders: " . $e->getMessage());
            }
        })();
    }

    /**
     * Fetch open positions for the given symbol
     * 
     * @param string $symbol
     * @return PromiseInterface
     */
    public function fetchOpenPositions(string $symbol): PromiseInterface
    {
        return async(function () use ($symbol) {
            try {
                return await($this->exchange->fetch_positions([$symbol]));
            } catch (Throwable $e) {
                logger()->error("Error fetching open positions for {$symbol}: " . $e->getMessage());
            }
        })();
    }

    /**
     * Fetch open orders and positions for the given symbol
     * 
     * @param string $symbol
     * @return PromiseInterface
     */
    public function fetchOpenOrdersAndPositions(string $symbol): PromiseInterface
    {
        return async(function () use ($symbol) {
            try {
                return await(all([
                    $this->fetchOpenOrders($symbol),
                    $this->fetchOpenPositions($symbol),
                ]));
            } catch (Throwable $e) {
                logger()->error("Error fetching open orders and positions for {$symbol}: " . $e->getMessage());
            }
        })();
    }
    
    public function fetchMarketSymbols(): PromiseInterface
    {
        static $symbols = null;

        return async(function () use (&$symbols) {
            if ($symbols !== null) {
                return $symbols;
            }

            try {
                $symbols = await($this->exchange->load_markets());
                return $symbols;
            } catch (Throwable $e) {
                logger()->error("Error fetching market symbols: " . $e->getMessage());
            }
        })();
    }

    public function hasSymbol(string $symbol)
    {
        return async(function () use ($symbol) {
            try {
                $markets = await($this->fetchMarketSymbols());
                return in_array($symbol, array_keys($markets));
            } catch (Throwable $e) {
                logger()->error("Error checking symbol: " . $e->getMessage());
                return false;
            }
        })();
    }
}
