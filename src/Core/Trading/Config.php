<?php

namespace Merchant\TradingBot\Core\Trading;

final class Config
{
    public static function get(string $name): array
    {
        $path = dirname(__DIR__, 3) . "/config/{$name}.php";

        if (!is_file($path)) {
            return [];
        }

        $config = require $path;

        return is_array($config) ? $config : [];
    }

    public static function value(string $name, string $key, mixed $default = null): mixed
    {
        $config = self::get($name);

        return $config[$key] ?? $default;
    }
}
