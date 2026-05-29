<?php

declare(strict_types=1);

namespace Eleload\Tests\Unit;

use Eleload\Metrics\PercentileCalculator;
use PHPUnit\Framework\TestCase;

final class PercentileCalculatorTest extends TestCase
{
    private PercentileCalculator $calc;

    protected function setUp(): void
    {
        $this->calc = new PercentileCalculator();
    }

    public function testReturnsZeroForEmptyArray(): void
    {
        self::assertSame(0.0, $this->calc->calculate([], 50));
        self::assertSame(0.0, $this->calc->calculate([], 95));
        self::assertSame(0.0, $this->calc->calculate([], 99));
    }

    public function testReturnsSingleValueForOneElementArray(): void
    {
        self::assertSame(42.0, $this->calc->calculate([42.0], 50));
        self::assertSame(42.0, $this->calc->calculate([42.0], 95));
        self::assertSame(42.0, $this->calc->calculate([42.0], 99));
    }

    public function testCorrectPercentilesForTenElementDataset(): void
    {
        // [10, 20, 30, 40, 50, 60, 70, 80, 90, 100]
        // p50: ceil(0.50 * 10) - 1 = 4 → 50
        // p95: ceil(0.95 * 10) - 1 = 9 → 100
        // p99: ceil(0.99 * 10) - 1 = 9 → 100
        $values = [10.0, 20.0, 30.0, 40.0, 50.0, 60.0, 70.0, 80.0, 90.0, 100.0];

        self::assertSame(50.0, $this->calc->calculate($values, 50));
        self::assertSame(100.0, $this->calc->calculate($values, 95));
        self::assertSame(100.0, $this->calc->calculate($values, 99));
    }

    public function testCorrectPercentilesForHundredElementDataset(): void
    {
        // values 1..100; p50=50, p95=95, p99=99
        $values = array_map('floatval', range(1, 100));

        self::assertSame(50.0, $this->calc->calculate($values, 50));
        self::assertSame(95.0, $this->calc->calculate($values, 95));
        self::assertSame(99.0, $this->calc->calculate($values, 99));
    }

    public function testSortsInputBeforeComputing(): void
    {
        $values = array_map('floatval', range(100, 1, -1));

        self::assertSame(50.0, $this->calc->calculate($values, 50));
        self::assertSame(95.0, $this->calc->calculate($values, 95));
        self::assertSame(99.0, $this->calc->calculate($values, 99));
    }

    public function testP100ReturnsMaximum(): void
    {
        $values = [5.0, 1.0, 3.0, 9.0, 7.0];

        self::assertSame(9.0, $this->calc->calculate($values, 100));
    }

    public function testP1ReturnsFirstSortedElement(): void
    {
        $values = [10.0, 20.0, 30.0, 40.0, 50.0, 60.0, 70.0, 80.0, 90.0, 100.0];

        self::assertSame(10.0, $this->calc->calculate($values, 1));
    }
}
