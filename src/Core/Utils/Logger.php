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
     * @return MonoLogger
     */
    public static function create()
    {
        return (new self())->logger;
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
