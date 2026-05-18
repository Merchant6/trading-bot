<?php

namespace Merchant\TradingBot\Core\Trading;

final class RiskManager
{
    public function __construct(private array $config = [])
    {
        $this->config = array_replace(Config::get('risk'), $this->config);
    }

    public function normalizeLeverage(int $leverage): int
    {
        $max = max(1, (int)($this->config['max_leverage'] ?? 20));

        return max(1, min($leverage, $max));
    }

    public function calculateQuantity(float $balance, float $currentPrice, int $leverage, int $precision = 3): float
    {
        if ($balance <= 0 || $currentPrice <= 0) {
            return 0.0;
        }

        $riskPercentage = max(0.0, (float)($this->config['risk_percentage'] ?? 2.0));
        $positionPercentage = max(0.0, (float)($this->config['max_position_percentage'] ?? 10.0));
        $capitalAtRisk = $balance * ($riskPercentage / 100);
        $maxCapital = $balance * ($positionPercentage / 100);
        $capital = min($capitalAtRisk, $maxCapital);

        return round(($capital * $this->normalizeLeverage($leverage)) / $currentPrice, $precision);
    }

    public function profitPercentage(float $entryPrice, float $currentPrice, int $leverage): float
    {
        if ($entryPrice <= 0) {
            return 0.0;
        }

        return round((($currentPrice - $entryPrice) / $entryPrice) * 100 * $this->normalizeLeverage($leverage), 3);
    }

    public function shouldTakeProfit(float $profitPercentage): bool
    {
        $target = (float)($this->config['take_profit_percentage'] ?? 15.0);
        $ceiling = (float)($this->config['take_profit_ceiling_percentage'] ?? 20.0);

        return $profitPercentage >= $target && $profitPercentage <= $ceiling;
    }

    public function shouldStopLoss(float $profitPercentage): bool
    {
        $limit = abs((float)($this->config['stop_loss_percentage'] ?? 15.0));

        return $profitPercentage <= -$limit;
    }

    public function stopLossPrice(float $entryPrice): float
    {
        $stopLoss = abs((float)($this->config['stop_loss_percentage'] ?? 15.0));

        return $entryPrice * (1 - ($stopLoss / 100));
    }

    public function config(): array
    {
        return $this->config;
    }
}
