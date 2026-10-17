<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

/**
 * Crew Key Value Object
 *
 * Immutable identifier for crew members.
 * Keys are generated from first and last name: lowercase, no spaces.
 */
final readonly class CrewKey
{
    private function __construct(
        private string $value
    ) {
        if (empty($value)) {
            throw new \InvalidArgumentException('Crew key cannot be empty');
        }
    }

    /**
     * Create from first and last name
     */
    public static function fromName(string $firstName, string $lastName): self
    {
        $key = strtolower(
            str_replace(' ', '', trim($firstName)) .
            str_replace(' ', '', trim($lastName))
        );
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
     * Check if equal to another CrewKey
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
