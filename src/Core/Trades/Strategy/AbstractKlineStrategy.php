<?php

namespace Merchant\TradingBot\Core\Trades\Strategy;

use Merchant\TradingBot\Core\Interfaces\StrategyInterface;
use Merchant\TradingBot\Core\Traits\OrderPlacement;
use Merchant\TradingBot\Core\Trading\StrategySignal;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\MarketData\ContractKLineData;

abstract class AbstractKlineStrategy implements StrategyInterface
{
    use OrderPlacement;

    public function __construct(
        protected ContractKLineData $contractKLineData,
        public array $options = []
    ) {
        $this->boot();
    }

    public function boot(): void
    {
        $this->init($this->options);
    }

    public function execute(): void
    {
        $this->recoverOpenPositions($this->options);

        if ($this->isOrderInProgress()) {
            return;
        }

        $this->processTrade();
    }

    public function processTrade(): void
    {
        $this->contractKLineData->details(function (array $data) {
            $signal = $this->generateSignal($data);
            $currentPrice = $this->latestClose($data);
            $this->recordSignal($signal, $currentPrice);

            if ($signal->shouldEnter() && $currentPrice > 0) {
                $this->placeOrder($currentPrice);
            }
        });
    }

    abstract public function generateSignal(array $klineData): StrategySignal;

    protected function closePrices(array $klineData): array
    {
        return array_map('floatval', array_column($klineData, 'close_price'));
    }

    protected function volumes(array $klineData): array
    {
        return array_map('floatval', array_column($klineData, 'volume'));
    }

    protected function highs(array $klineData): array
    {
        return array_map('floatval', array_column($klineData, 'high_price'));
    }

    protected function lows(array $klineData): array
    {
        return array_map('floatval', array_column($klineData, 'low_price'));
    }

    protected function latestClose(array $klineData): float
    {
        $prices = $this->closePrices($klineData);

        return $prices === [] ? 0.0 : (float)end($prices);
    }

    protected function option(string $key, mixed $default): mixed
    {
        return $this->options[$key] ?? $default;
    }
}
