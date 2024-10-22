<?php

namespace Merchant\TradingBot\Core\Utils;

use Monolog\Level;
use Monolog\Logger as MonoLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\FirePHPHandler;

class Logger
{   
    private MonoLogger $logger;

    public function __construct()
    {
        $this->logger = new MonoLogger('logger');
        $this->createLogFile();
        $this->logger->pushHandler(new StreamHandler(__DIR__ . '/../../../storage/logs/app.log', Level::Debug));
    }

    public static function create()
    {
        return (new self())->logger;
    }

    public function createLogFile()
    {   
        $file = __DIR__ . '/../../../storage/logs/app.log';
        
        if(!file_exists($file)){
            return touch($file, time());
        }
    }
}
