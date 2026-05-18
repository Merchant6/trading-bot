<?php

namespace Merchant\TradingBot\Core\Trading;

use InvalidArgumentException;
use Merchant\TradingBot\Core\Interfaces\StrategyInterface;
use Merchant\TradingBot\Core\Utils\Cryptocurrency\MarketData\ContractKLineData;

final class StrategyRegistry
{
    public function __construct(private array $config = [])
    {
        $this->config = array_replace_recursive(Config::get('strategies'), $this->config);
    }

    public function names(): array
    {
        return array_keys($this->config['available'] ?? []);
    }

    public function settings(string $name): array
    {
        return $this->config['settings'][$name] ?? [];
    }

    public function create(string $name, ContractKLineData $marketData, array $options = []): StrategyInterface
    {
        $class = $this->config['available'][$name] ?? null;

        if (!$class || !class_exists($class)) {
            throw new InvalidArgumentException("Unknown strategy [{$name}].");
        }

        $strategyOptions = array_replace($this->settings($name), $options, ['strategy' => $name]);
        $strategy = new $class($marketData, $strategyOptions);

        if (!$strategy instanceof StrategyInterface) {
            throw new InvalidArgumentException("Strategy [{$name}] must implement StrategyInterface.");
        }

        return $strategy;
    }

    public function metadata(): array
    {
        return array_map(
            fn (string $name) => [
                'name' => $name,
                'settings' => $this->settings($name),
            ],
            $this->names()
        );
    }
}
