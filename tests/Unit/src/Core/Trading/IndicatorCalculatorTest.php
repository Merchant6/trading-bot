<?php

use Merchant\TradingBot\Core\Trading\IndicatorCalculator;
use PHPUnit\Framework\TestCase;

class IndicatorCalculatorTest extends TestCase
{
    public function testSmaReturnsNullUntilPeriodIsAvailable(): void
    {
        $sma = IndicatorCalculator::sma([1, 2, 3, 4], 3);

        $this->assertSame([null, null, 2.0, 3.0], $sma);
    }

    public function testBollingerBandsReturnExpectedKeys(): void
    {
        $bands = IndicatorCalculator::bollinger(range(1, 30), 20, 2);

        $this->assertArrayHasKey('upper', $bands);
        $this->assertArrayHasKey('middle', $bands);
        $this->assertArrayHasKey('lower', $bands);
        $this->assertCount(30, $bands['middle']);
    }
}
