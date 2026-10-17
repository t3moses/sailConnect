<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\BoatKey;
use PHPUnit\Framework\TestCase;

class BoatKeyTest extends TestCase
{
    public function testFromBoatNameCreatesValidKey(): void
    {
        // Arrange
        $key = BoatKey::fromBoatName('Sail Away');

        // Assert
        $this->assertEquals('sailaway', $key->toString());
    }

    public function testFromBoatNameHandlesMultipleSpaces(): void
    {
        // Arrange
        $key = BoatKey::fromBoatName('My  Boat  Name');

        // Assert
        $this->assertEquals('myboatname', $key->toString());
    }

    public function testFromBoatNameTrimsWhitespace(): void
    {
        // Arrange
        $key = BoatKey::fromBoatName('  Trimmed  ');

        // Assert
        $this->assertEquals('trimmed', $key->toString());
    }

    public function testFromBoatNameConvertsToLowercase(): void
    {
        // Arrange
        $key = BoatKey::fromBoatName('UPPERCASE');

        // Assert
        $this->assertEquals('uppercase', $key->toString());
    }

    public function testFromStringCreatesValidKey(): void
    {
        // Arrange
        $key = BoatKey::fromString('customkey');

        // Assert
        $this->assertEquals('customkey', $key->toString());
    }

    public function testFromStringThrowsExceptionForEmptyString(): void
    {
        // Arrange
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Boat key cannot be empty');

        BoatKey::fromString('');
    }

    public function testFromBoatNameThrowsExceptionForEmptyName(): void
    {
        // Arrange
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Boat key cannot be empty');

        BoatKey::fromBoatName('   ');
    }

    public function testEqualsReturnsTrueForSameKey(): void
    {
        // Arrange
        $key1 = BoatKey::fromString('sailaway');
        $key2 = BoatKey::fromString('sailaway');

        // Assert
        $this->assertTrue($key1->equals($key2));
    }

    public function testEqualsReturnsFalseForDifferentKeys(): void
    {
        // Arrange
        $key1 = BoatKey::fromString('sailaway');
        $key2 = BoatKey::fromString('otherboat');

        // Assert
        $this->assertFalse($key1->equals($key2));
    }

    public function testToStringReturnsKeyValue(): void
    {
        // Arrange
        $key = BoatKey::fromString('testboat');

        // Assert
        $this->assertEquals('testboat', (string) $key);
    }

    public function testImmutability(): void
    {
        // Arrange
        $key1 = BoatKey::fromBoatName('Test Boat');
        $key2 = BoatKey::fromBoatName('Test Boat');

        // Both should have the same value but be different instances
        // Assert
        $this->assertEquals($key1->toString(), $key2->toString());
        $this->assertTrue($key1->equals($key2));
    }

    public function testSpecialCharactersInBoatName(): void
    {
        // Arrange
        $key = BoatKey::fromBoatName("O'Reilly's Boat");

        // Assert
        $this->assertEquals("o'reilly'sboat", $key->toString());
    }
}
