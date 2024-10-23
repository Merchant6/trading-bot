<?php

namespace Merchant\TradingBot\Core\Utils;

use Monolog\Level;
use Monolog\Logger as MonoLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\FirePHPHandler;

class Logger
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

    public function info(string $message)
    {
        $stackTrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        $formattedTrace = array_map(function ($trace) {
            return "{$trace['file']}:{$trace['line']} - {$trace['function']}";
        }, $stackTrace);

        $this->logger->info($message, ['stack_trace' => $formattedTrace]);
    }

    /**
     * Create a new log file if not already created
     * 
     * @return void
     */
    public function createLogFile(): void
    {   
        $file = __DIR__ . '/../../../storage/logs/app.log';
        
        if(!file_exists($file)){
            touch($file, time());
        }
    }
}
