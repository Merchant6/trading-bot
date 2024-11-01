<?php

namespace Merchant\TradingBot\Core\Utils;

use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

class PeriodicTimer
{   
    private TimerInterface|null $timer;

    public function __construct(
            public LoopInterface $loop, 
            public float|int $interval
        )
    {
        
    }

    public function start(callable $callback): void
    {
        $this->timer = $this->loop->addPeriodicTimer($this->interval, $callback);
    }

    public function stop(): void
    {
        if($this->getTimer()){
            $this->loop->cancelTimer($this->timer);
        }
    }

    public function getTimer(): TimerInterface|Null
    {
        return $this->timer;
    }
}
