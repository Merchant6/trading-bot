<?php

namespace Merchant\TradingBot\Core\Trading;

final class StrategySignal
{
    public const BUY = 'BUY';
    public const SELL = 'SELL';
    public const HOLD = 'HOLD';

    public function __construct(
        public readonly string $action,
        public readonly string $reason,
        public readonly float $confidence = 0.0,
        public readonly array $context = []
    ) {
    }

    public static function buy(string $reason, float $confidence = 0.0, array $context = []): self
    {
        return new self(self::BUY, $reason, $confidence, $context);
    }

    public static function sell(string $reason, float $confidence = 0.0, array $context = []): self
    {
        return new self(self::SELL, $reason, $confidence, $context);
    }

    public static function hold(string $reason, array $context = []): self
    {
        return new self(self::HOLD, $reason, 0.0, $context);
    }

    public function shouldEnter(): bool
    {
        return $this->action === self::BUY;
    }

    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'reason' => $this->reason,
            'confidence' => $this->confidence,
            'context' => $this->context,
        ];
    }
}
