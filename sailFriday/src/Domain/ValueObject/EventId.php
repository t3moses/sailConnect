<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

/**
 * Event ID Value Object
 *
 * Immutable identifier for events.
 * Format: "Day Mon DD" (e.g., "Fri May 29")
 */
final readonly class EventId
{
    private function __construct(
        private string $value
    ) {
        if (empty($value)) {
            throw new \InvalidArgumentException('Event ID cannot be empty');
        }
    }

    /**
     * Create from string
     */
    public static function fromString(string $eventId): self
    {
        return new self($eventId);
    }

    /**
     * Create from date
     */
    public static function fromDate(\DateTimeInterface $date): self
    {
        $value = $date->format('D M d');
        return new self($value);
    }

    /**
     * Get the event ID value
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Check if equal to another EventId
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

    /**
     * Get hash for deterministic seeding (used in Selection)
     */
    public function getHash(): int
    {
        return crc32($this->value);
    }
}
