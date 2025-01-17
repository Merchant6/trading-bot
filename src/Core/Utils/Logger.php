<?php

namespace Merchant\TradingBot\Core\Utils;

use Monolog\Level;
use Monolog\Logger as MonoLogger;
use Monolog\Handler\StreamHandler;
use Psr\Log\LoggerInterface;

class Logger implements LoggerInterface
{
    private MonoLogger $logger;

    /**
     * Instantiate the Logger class
     */
    public function __construct()
    {
        $this->logger = new MonoLogger('logger');
        $this->createLogFile();
        $this->logger->pushHandler(new StreamHandler(__DIR__ . '/../../../storage/logs/app.log', Level::Debug));
    }

    /**
     * Create a new instance of the the Logger class
     * and return the MonoLogger
     *
     * @return self
     */
    public static function create()
    {
        return new self();
    }

    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->logger->emergency($message, $context);
    }

    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->logger->alert($message, $context);
    }

    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->logger->critical($message, $context);
    }

    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }

    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }

    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->logger->notice($message, $context);
    }

    public function info(string|\Stringable $message, array $context = []): void
    {
        $stackTrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        $formattedTrace = array_map(function ($trace) {
            return "{$trace['file']}:{$trace['line']} - {$trace['function']}";
        }, $stackTrace);

        $this->logger->info($message, ['stack_trace' => $formattedTrace] + $context);
    }

    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->logger->log($level, $message, $context);
    }

    /**
     * Create a new log file if not already created
     *
     * @return void
     */
    public function createLogFile(): void
    {
        $file = __DIR__ . '/../../../storage/logs/app.log';

        if (!file_exists($file)) {
            touch($file, time());
        }
    }
}