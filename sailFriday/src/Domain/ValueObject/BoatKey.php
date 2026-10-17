<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

/**
 * Boat Key Value Object
 *
 * Immutable identifier for boats.
 * Keys are generated from boat name: lowercase, no spaces.
 */
final readonly class BoatKey
{
    private function __construct(
        private string $value
    ) {
        if (empty($value)) {
            throw new \InvalidArgumentException('Boat key cannot be empty');
        }
    }

    /**
     * Create from boat name
     */
    public static function fromBoatName(string $boatName): self
    {
        $key = strtolower(str_replace(' ', '', trim($boatName)));
        return new self($key);
    }

    /**
     * Create from existing key value
     */
    public static function fromString(string $key): self
    {
        return new self($key);
    }

    /**
     * Get the key value
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Check if equal to another BoatKey
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * String representation
     */
    public function __toString(): string
    {
        return $this->value;
    }
}
