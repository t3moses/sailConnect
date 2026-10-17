<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Enum;

use App\Domain\Enum\SkillLevel;
use PHPUnit\Framework\TestCase;

class SkillLevelTest extends TestCase
{
    public function testEnumValuesAreCorrect(): void
    {
        // Arrange
        // Assert
        $this->assertEquals(0, SkillLevel::NOVICE->value);
        $this->assertEquals(1, SkillLevel::INTERMEDIATE->value);
        $this->assertEquals(2, SkillLevel::ADVANCED->value);
    }

    public function testIsHighReturnsTrueForAdvanced(): void
    {
        // Arrange
        $skill = SkillLevel::ADVANCED;

        // Assert
        $this->assertTrue($skill->isHigh());
    }

    public function testIsHighReturnsFalseForIntermediate(): void
    {
        // Arrange
        $skill = SkillLevel::INTERMEDIATE;

        // Assert
        $this->assertFalse($skill->isHigh());
    }

    public function testIsHighReturnsFalseForNovice(): void
    {
        // Arrange
        $skill = SkillLevel::NOVICE;

        // Assert
        $this->assertFalse($skill->isHigh());
    }

    public function testIsLowReturnsTrueForNovice(): void
    {
        // Arrange
        $skill = SkillLevel::NOVICE;

        // Assert
        $this->assertTrue($skill->isLow());
    }

    public function testIsLowReturnsFalseForIntermediate(): void
    {
        // Arrange
        $skill = SkillLevel::INTERMEDIATE;

        // Assert
        $this->assertFalse($skill->isLow());
    }

    public function testIsLowReturnsFalseForAdvanced(): void
    {
        // Arrange
        $skill = SkillLevel::ADVANCED;

        // Assert
        $this->assertFalse($skill->isLow());
    }

    public function testFromIntCreatesNovice(): void
    {
        // Arrange
        $skill = SkillLevel::fromInt(0);

        // Assert
        $this->assertEquals(SkillLevel::NOVICE, $skill);
    }

    public function testFromIntCreatesIntermediate(): void
    {
        // Arrange
        $skill = SkillLevel::fromInt(1);

        // Assert
        $this->assertEquals(SkillLevel::INTERMEDIATE, $skill);
    }

    public function testFromIntCreatesAdvanced(): void
    {
        // Arrange
        $skill = SkillLevel::fromInt(2);

        // Assert
        $this->assertEquals(SkillLevel::ADVANCED, $skill);
    }

    public function testFromIntThrowsExceptionForInvalidValue(): void
    {
        // Arrange
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid skill level: 99');

        SkillLevel::fromInt(99);
    }

    public function testFromIntThrowsExceptionForNegativeValue(): void
    {
        // Arrange
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid skill level: -1');

        SkillLevel::fromInt(-1);
    }

    public function testEnumFromInt(): void
    {
        // Arrange
        // Assert
        $this->assertEquals(SkillLevel::NOVICE, SkillLevel::from(0));
        $this->assertEquals(SkillLevel::INTERMEDIATE, SkillLevel::from(1));
        $this->assertEquals(SkillLevel::ADVANCED, SkillLevel::from(2));
    }

    public function testEnumFromIntThrowsExceptionForInvalidValue(): void
    {
        // Arrange
        // Assert
        $this->expectException(\ValueError::class);

        SkillLevel::from(99);
    }

    public function testAllEnumCasesExist(): void
    {
        // Arrange
        $cases = SkillLevel::cases();

        // Assert
        $this->assertCount(3, $cases);
        $this->assertContains(SkillLevel::NOVICE, $cases);
        $this->assertContains(SkillLevel::INTERMEDIATE, $cases);
        $this->assertContains(SkillLevel::ADVANCED, $cases);
    }
}
