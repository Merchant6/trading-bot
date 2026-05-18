<?php

use Merchant\TradingBot\Core\Trading\RiskManager;
use PHPUnit\Framework\TestCase;

class RiskManagerTest extends TestCase
{
    public function testCalculateQuantityUsesRiskPercentageAndLeverage(): void
    {
        $manager = new RiskManager([
            'risk_percentage' => 2,
            'max_position_percentage' => 10,
            'max_leverage' => 20,
        ]);

        $quantity = $manager->calculateQuantity(1000, 50000, 10);

        $this->assertSame(0.004, $quantity);
    }

    public function testLeverageIsCappedAtConfiguredMaximum(): void
    {
        $manager = new RiskManager(['max_leverage' => 5]);

        $this->assertSame(5, $manager->normalizeLeverage(20));
    }

    public function testStopLossAndTakeProfitThresholds(): void
    {
        $manager = new RiskManager([
            'take_profit_percentage' => 12,
            'take_profit_ceiling_percentage' => 25,
            'stop_loss_percentage' => 8,
        ]);

        $this->assertTrue($manager->shouldTakeProfit(15));
        $this->assertTrue($manager->shouldStopLoss(-9));
        $this->assertSame(92.0, $manager->stopLossPrice(100));
    }
}
