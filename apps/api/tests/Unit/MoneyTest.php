<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\Money;
use Codeception\Test\Unit;
use InvalidArgumentException;

class MoneyTest extends Unit
{
    public function testFormatPence(): void
    {
        $this->assertSame('£0.00', Money::formatPence(0));
        $this->assertSame('£40.00', Money::formatPence(4000));
        $this->assertSame('£1,250.50', Money::formatPence(125050));
        $this->assertSame('−£10.00', Money::formatPence(-1000));
    }

    public function testPoundsToPenceFromString(): void
    {
        $this->assertSame(4000, Money::poundsToPence('40'));
        $this->assertSame(4050, Money::poundsToPence('40.50'));
        $this->assertSame(125000, Money::poundsToPence('1,250'));
    }

    public function testRejectsFloatInput(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::poundsToPence(40.5);
    }

    public function testRequireNonNegativePenceRejectsFloat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::requireNonNegativePence(40.0);
    }

    public function testLessonPriceFromHourlyRateUsesIntegerMath(): void
    {
        // £36/hour × 90 minutes = £54.00
        $this->assertSame(5400, Money::lessonPriceFromHourlyRate(3600, 90));
        // £35/hour × 60 = £35.00
        $this->assertSame(3500, Money::lessonPriceFromHourlyRate(3500, 60));
    }
}
