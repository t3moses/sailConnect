<?php

declare(strict_types=1);

namespace App\Domain\Collection;

use App\Domain\Entity\Boat;
use App\Domain\ValueObject\BoatKey;
use App\Domain\ValueObject\EventId;

/**
 * Fleet Collection
 *
 * Manages a collection of boats.
 * This is an in-memory collection for domain logic.
 * Persistence is handled by repositories.
 */
class Fleet
{
    /** @var array<string, Boat> Boats indexed by key */
    private array $boats = [];

    /**
     * Add a boat to the fleet
     */
    public function add(Boat $boat): void
    {
        $this->boats[$boat->getKey()->toString()] = $boat;
    }

    /**
     * Remove a boat from the fleet
     */
    public function remove(BoatKey $key): void
    {
        unset($this->boats[$key->toString()]);
    }

    /**
     * Get a boat by key
     */
    public function get(BoatKey $key): ?Boat
    {
        return $this->boats[$key->toString()] ?? null;
    }

    /**
     * Check if a boat exists
     */
    public function has(BoatKey $key): bool
    {
        return isset($this->boats[$key->toString()]);
    }

    /**
     * Get all boats
     *
     * @return array<Boat>
     */
    public function all(): array
    {
        return array_values($this->boats);
    }

    /**
     * Get boats available for an event
     *
     * @return array<Boat>
     */
    public function getAvailableFor(EventId $eventId): array
    {
        return array_values(
            array_filter(
                $this->boats,
                fn(Boat $boat) => $boat->isAvailableFor($eventId)
            )
        );
    }

    /**
     * Get count of boats
     */
    public function count(): int
    {
        return count($this->boats);
    }

    /**
     * Check if fleet is empty
     */
    public function isEmpty(): bool
    {
        return empty($this->boats);
    }

    /**
     * Clear all boats
     */
    public function clear(): void
    {
        $this->boats = [];
    }

    /**
     * Filter boats by a predicate
     *
     * @param callable(Boat): bool $predicate
     * @return array<Boat>
     */
    public function filter(callable $predicate): array
    {
        return array_values(array_filter($this->boats, $predicate));
    }

    /**
     * Map boats to a new array
     *
     * @template T
     * @param callable(Boat): T $mapper
     * @return array<T>
     */
    public function map(callable $mapper): array
    {
        return array_map($mapper, array_values($this->boats));
    }

    /**
     * Get iterator for foreach
     *
     * @return \ArrayIterator<string, Boat>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->boats);
    }
}
