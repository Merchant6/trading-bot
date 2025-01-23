<?php

namespace Merchant\TradingBot\Core\Traits;

trait Lock
{
    public static array $lockRegistry = [];

    /**
     * Checks if a lock exists for the given key.
     */
    public function isLocked(string $key): bool
    {
        return !empty(self::$lockRegistry[$key]);
    }

    /**
     * Locks the given key.
     */
    public function lock(string $key): void
    {
        self::$lockRegistry[$key] = true;
    }

    /**
     * Unlocks the given key.
     */
    public function unlock(string $key): void
    {
        unset(self::$lockRegistry[$key]);
    }
}
