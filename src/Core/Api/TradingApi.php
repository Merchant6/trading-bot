<?php

namespace Merchant\TradingBot\Core\Api;

use Merchant\TradingBot\Core\Trading\Config;
use Merchant\TradingBot\Core\Trading\RiskManager;
use Merchant\TradingBot\Core\Trading\StrategyRegistry;
use Merchant\TradingBot\Core\Trading\TradingStateRepository;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;

final class TradingApi
{
    public function __construct(
        private TradingStateRepository $state = new TradingStateRepository(),
        private StrategyRegistry $strategies = new StrategyRegistry()
    ) {
    }

    public function handle(ServerRequestInterface $request): Response
    {
        if ($request->getMethod() === 'OPTIONS') {
            return ApiResponse::json([]);
        }

        $path = $request->getUri()->getPath();

        return match ([$request->getMethod(), $path]) {
            ['GET', '/api/status'] => ApiResponse::json($this->state->all()),
            ['GET', '/api/strategies'] => ApiResponse::json(['strategies' => $this->strategies->metadata()]),
            ['GET', '/api/config'] => ApiResponse::json([
                'trading' => Config::get('trading'),
                'risk' => (new RiskManager())->config(),
            ]),
            ['POST', '/api/bot/start'] => ApiResponse::json($this->start($request)),
            ['POST', '/api/bot/stop'] => ApiResponse::json($this->stop()),
            ['GET', '/api/events'] => ApiResponse::json(['events' => $this->state->all()['events'] ?? []]),
            default => ApiResponse::json(['error' => 'Not found'], 404),
        };
    }

    private function start(ServerRequestInterface $request): array
    {
        $payload = $this->payload($request);
        $state = $this->state->update([
            'running' => true,
            'paper_trading' => filter_var($payload['paper_trading'] ?? Config::value('trading', 'paper_trading', true), FILTER_VALIDATE_BOOLEAN),
            'strategy' => $payload['strategy'] ?? Config::value('strategies', 'default', 'bollinger-rsi'),
            'symbol' => strtoupper($payload['symbol'] ?? Config::value('trading', 'default_symbol', 'BTCUSDT')),
            'interval' => $payload['interval'] ?? Config::value('trading', 'default_interval', '5m'),
            'updated_at' => date(DATE_ATOM),
        ]);

        $this->state->recordEvent('bot_started', [
            'strategy' => $state['strategy'],
            'symbol' => $state['symbol'],
            'paper_trading' => $state['paper_trading'],
        ]);

        return $this->state->all();
    }

    private function stop(): array
    {
        $state = $this->state->update([
            'running' => false,
            'updated_at' => date(DATE_ATOM),
        ]);

        $this->state->recordEvent('bot_stopped', [
            'strategy' => $state['strategy'],
            'symbol' => $state['symbol'],
        ]);

        return $this->state->all();
    }

    private function payload(ServerRequestInterface $request): array
    {
        $body = (string)$request->getBody();
        $payload = json_decode($body, true);

        return is_array($payload) ? $payload : [];
    }
}
