<?php

use Merchant\TradingBot\Core\Utils\Logger;
use Merchant\TradingBot\Core\Utils\PeriodicTimer;
use PHPUnit\Framework\TestCase;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\EventLoop\TimerInterface;


class PeriodicTimerTest extends TestCase
{   
    public LoopInterface $loop;
    public int|float $interval = 5;

    public function setUp(): void
    {
        $this->loop = $this->createMock(LoopInterface::class);
    }

    public function testStartMethodSettingTheTimerVariableToTimerInterface()
    {   
        $timerMock = $this->createMock(TimerInterface::class);

        $this->loop->expects($this->once())
            ->method('addPeriodicTimer')
            ->with($this->interval, $this->isType('callable'))
            ->willReturn($timerMock);

        $timer = new PeriodicTimer($this->loop, $this->interval);
        $timer->start(function () {});

        $this->assertSame($timerMock, $timer->getTimer());
    }

    public function testStopMethodSettingTheTimerVariableToNull()
    {
        $timerMock = $this->createMock(TimerInterface::class);

        $this->loop->expects($this->once())
            ->method('addPeriodicTimer')
            ->with($this->interval, $this->isType('callable'))
            ->willReturn($timerMock);

        $timer = new PeriodicTimer($this->loop, $this->interval);
        $timer->start(function () {});
        $timer->stop();
        
        $this->assertSame($timerMock, $timer->getTimer());
    }
}
