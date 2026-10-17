<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\CrewKey;
use PHPUnit\Framework\TestCase;

class CrewKeyTest extends TestCase
{
    public function testFromNameCreatesValidKey(): void
    {
        // Arrange
        $key = CrewKey::fromName('John', 'Doe');

        // Assert
        $this->assertEquals('johndoe', $key->toString());
    }

    public function testFromNameHandlesSpacesInNames(): void
    {
        // Arrange
        $key = CrewKey::fromName('Mary Jane', 'Van Der Berg');

        // Assert
        $this->assertEquals('maryjanevanderberg', $key->toString());
    }

    public function testFromNameTrimsWhitespace(): void
    {
        // Arrange
        $key = CrewKey::fromName('  John  ', '  Doe  ');

        // Assert
        $this->assertEquals('johndoe', $key->toString());
    }

    public function testFromNameConvertsToLowercase(): void
    {
        // Arrange
        $key = CrewKey::fromName('JOHN', 'DOE');

        // Assert
        $this->assertEquals('johndoe', $key->toString());
    }

    public function testFromStringCreatesValidKey(): void
    {
        // Arrange
        $key = CrewKey::fromString('customkey');

        // Assert
        $this->assertEquals('customkey', $key->toString());
    }

    public function testFromStringThrowsExceptionForEmptyString(): void
    {
        // Arrange
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Crew key cannot be empty');

        CrewKey::fromString('');
    }

    public function testFromNameThrowsExceptionForEmptyNames(): void
    {
        // Arrange
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Crew key cannot be empty');

        CrewKey::fromName('   ', '   ');
    }

    public function testEqualsReturnsTrueForSameKey(): void
    {
        // Arrange
        $key1 = CrewKey::fromString('johndoe');
        $key2 = CrewKey::fromString('johndoe');

        // Assert
        $this->assertTrue($key1->equals($key2));
    }

    public function testEqualsReturnsFalseForDifferentKeys(): void
    {
        // Arrange
        $key1 = CrewKey::fromString('johndoe');
        $key2 = CrewKey::fromString('janedoe');

        // Assert
        $this->assertFalse($key1->equals($key2));
    }

    public function testToStringReturnsKeyValue(): void
    {
        // Arrange
        $key = CrewKey::fromString('testcrew');

        // Assert
        $this->assertEquals('testcrew', (string) $key);
    }

    public function testImmutability(): void
    {
        // Arrange
        $key1 = CrewKey::fromName('John', 'Doe');
        $key2 = CrewKey::fromName('John', 'Doe');

        // Both should have the same value but be different instances
        // Assert
        $this->assertEquals($key1->toString(), $key2->toString());
        $this->assertTrue($key1->equals($key2));
    }

    public function testSpecialCharactersInName(): void
    {
        // Arrange
        $key = CrewKey::fromName("O'Brien", "D'Angelo");

        // Assert
        $this->assertEquals("o'briend'angelo", $key->toString());
    }

    public function testHyphensInName(): void
    {
        // Arrange
        $key = CrewKey::fromName('Jean-Pierre', 'Saint-Exupéry');

        // Assert
        $this->assertEquals('jean-pierresaint-exupéry', $key->toString());
    }
}
