<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Enum;

use App\Domain\Enum\BoatRankDimension;
use PHPUnit\Framework\TestCase;

class BoatRankDimensionTest extends TestCase
{
    public function testEnumValuesAreCorrect(): void
    {
        // Arrange
        // Assert
        $this->assertEquals(0, BoatRankDimension::FLEXIBILITY->value);
        $this->assertEquals(1, BoatRankDimension::ABSENCE->value);
    }

    public function testAllReturnsAllDimensionsInOrder(): void
    {
        // Arrange
        $dimensions = BoatRankDimension::all();

        // Assert
        $this->assertCount(2, $dimensions);
        $this->assertEquals(BoatRankDimension::FLEXIBILITY, $dimensions[0]);
        $this->assertEquals(BoatRankDimension::ABSENCE, $dimensions[1]);
    }

    public function testAllEnumCasesExist(): void
    {
        // Arrange
        $cases = BoatRankDimension::cases();

        // Assert
        $this->assertCount(2, $cases);
        $this->assertContains(BoatRankDimension::FLEXIBILITY, $cases);
        $this->assertContains(BoatRankDimension::ABSENCE, $cases);
    }

    public function testEnumFromInt(): void
    {
        // Arrange
        // Assert
        $this->assertEquals(BoatRankDimension::FLEXIBILITY, BoatRankDimension::from(0));
        $this->assertEquals(BoatRankDimension::ABSENCE, BoatRankDimension::from(1));
    }

    public function testEnumFromIntThrowsExceptionForInvalidValue(): void
    {
        // Arrange
        // Assert
        $this->expectException(\ValueError::class);

        BoatRankDimension::from(99);
    }
}
