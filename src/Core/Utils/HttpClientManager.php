<?php

namespace Merchant\TradingBot\Core\Utils;

use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Http\Browser;

/**
 * Centralized HTTP Client Management
 */
class HttpClientManager
{
    private static ?Browser $instance = null;
    private static ?LoopInterface $loop = null;

    /**
     * Ensures the loop is initialized before use
     */
    private static function initialize(): void
    {
        if (self::$loop === null) {
            self::$loop = Loop::get();
        }
    }

    /**
     * Get the shared Browser instance
     */
    public static function getBrowser(): Browser
    {
        if (self::$instance === null) {
            self::initialize();
            self::$instance = new Browser(loop: self::$loop);
        }

        return self::$instance;
    }

    /**
     * Replace or set a custom event loop explicitly
     */
    public static function setLoop(LoopInterface $loop): void
    {
        self::$loop = $loop;
        self::$instance = null; // Reset the Browser instance if the loop changes
    }

    /**
     * Get the event loop
     */
    public static function getLoop(): LoopInterface
    {
        self::initialize();
        return self::$loop;
    }

    /**
     * Reset the state for testing or reinitialization
     */
    public static function reset(): void
    {
        self::$instance = null;
        self::$loop = null;
    }
}
