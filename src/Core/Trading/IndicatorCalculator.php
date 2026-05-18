<?php

namespace Merchant\TradingBot\Core\Trading;

final class IndicatorCalculator
{
    public static function sma(array $values, int $period): array
    {
        $result = [];
        $count = count($values);

        for ($i = 0; $i < $count; $i++) {
            if ($i + 1 < $period) {
                $result[] = null;
                continue;
            }

            $window = array_slice($values, $i + 1 - $period, $period);
            $result[] = array_sum($window) / $period;
        }

        return $result;
    }

    public static function ema(array $values, int $period): array
    {
        if ($values === []) {
            return [];
        }

        $multiplier = 2 / ($period + 1);
        $ema = [];
        $previous = (float)$values[0];

        foreach ($values as $value) {
            $previous = (((float)$value - $previous) * $multiplier) + $previous;
            $ema[] = $previous;
        }

        return $ema;
    }

    public static function rsi(array $values, int $period = 14): array
    {
        $count = count($values);
        if ($count <= $period) {
            return [];
        }

        $gains = [];
        $losses = [];

        for ($i = 1; $i < $count; $i++) {
            $change = (float)$values[$i] - (float)$values[$i - 1];
            $gains[] = max($change, 0);
            $losses[] = abs(min($change, 0));
        }

        $rsi = array_fill(0, $period, null);
        $avgGain = array_sum(array_slice($gains, 0, $period)) / $period;
        $avgLoss = array_sum(array_slice($losses, 0, $period)) / $period;

        for ($i = $period; $i < count($gains); $i++) {
            $avgGain = (($avgGain * ($period - 1)) + $gains[$i]) / $period;
            $avgLoss = (($avgLoss * ($period - 1)) + $losses[$i]) / $period;
            $rsi[] = $avgLoss == 0.0 ? 100.0 : 100 - (100 / (1 + ($avgGain / $avgLoss)));
        }

        return $rsi;
    }

    public static function bollinger(array $values, int $period = 20, float $stdDev = 2.0): array
    {
        $middle = self::sma($values, $period);
        $upper = [];
        $lower = [];

        foreach ($values as $index => $value) {
            if ($index + 1 < $period || $middle[$index] === null) {
                $upper[] = null;
                $lower[] = null;
                continue;
            }

            $window = array_slice($values, $index + 1 - $period, $period);
            $variance = array_sum(array_map(
                fn ($price) => ((float)$price - $middle[$index]) ** 2,
                $window
            )) / $period;
            $deviation = sqrt($variance);
            $upper[] = $middle[$index] + ($stdDev * $deviation);
            $lower[] = $middle[$index] - ($stdDev * $deviation);
        }

        return [
            'upper' => $upper,
            'middle' => $middle,
            'lower' => $lower,
        ];
    }

    public static function macd(array $values, int $fast = 12, int $slow = 26, int $signal = 9): array
    {
        $fastEma = self::ema($values, $fast);
        $slowEma = self::ema($values, $slow);
        $macd = [];

        foreach ($values as $index => $_) {
            $macd[] = ($fastEma[$index] ?? 0.0) - ($slowEma[$index] ?? 0.0);
        }

        $signalLine = self::ema($macd, $signal);
        $histogram = [];

        foreach ($macd as $index => $value) {
            $histogram[] = $value - ($signalLine[$index] ?? 0.0);
        }

        return [
            'macd' => $macd,
            'signal' => $signalLine,
            'histogram' => $histogram,
        ];
    }
}
