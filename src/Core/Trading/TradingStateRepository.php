<?php

namespace Merchant\TradingBot\Core\Trading;

final class TradingStateRepository
{
    public function __construct(private ?string $path = null)
    {
        $this->path ??= Config::value('trading', 'state_file', dirname(__DIR__, 3) . '/storage/trading-state.json');
    }

    public function all(): array
    {
        if (!is_file($this->path)) {
            return $this->defaultState();
        }

        $data = json_decode((string)file_get_contents($this->path), true);

        return is_array($data) ? array_replace_recursive($this->defaultState(), $data) : $this->defaultState();
    }

    public function update(array $changes): array
    {
        $state = array_replace_recursive($this->all(), $changes);
        $this->write($state);

        return $state;
    }

    public function recordEvent(string $type, array $payload): array
    {
        $state = $this->all();
        array_unshift($state['events'], [
            'type' => $type,
            'payload' => $payload,
            'created_at' => date(DATE_ATOM),
        ]);
        $state['events'] = array_slice($state['events'], 0, 100);
        $state['updated_at'] = date(DATE_ATOM);
        $this->write($state);

        return $state;
    }

    private function write(array $state): void
    {
        $directory = dirname($this->path);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($this->path, json_encode($state, JSON_PRETTY_PRINT));
    }

    private function defaultState(): array
    {
        return [
            'running' => false,
            'paper_trading' => Config::value('trading', 'paper_trading', true),
            'strategy' => Config::value('strategies', 'default', 'bollinger-rsi'),
            'symbol' => Config::value('trading', 'default_symbol', 'BTCUSDT'),
            'interval' => Config::value('trading', 'default_interval', '5m'),
            'positions' => [],
            'orders' => [],
            'events' => [],
            'updated_at' => date(DATE_ATOM),
        ];
    }
}
