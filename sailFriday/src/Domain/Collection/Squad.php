<?php

declare(strict_types=1);

namespace App\Domain\Collection;

use App\Domain\Entity\Crew;
use App\Domain\ValueObject\CrewKey;
use App\Domain\ValueObject\EventId;

/**
 * Squad Collection
 *
 * Manages a collection of crew members.
 * This is an in-memory collection for domain logic.
 * Persistence is handled by repositories.
 */
class Squad
{
    /** @var array<string, Crew> Crews indexed by key */
    private array $crews = [];

    /**
     * Add a crew member to the squad
     */
    public function add(Crew $crew): void
    {
        $this->crews[$crew->getKey()->toString()] = $crew;
    }

    /**
     * Remove a crew member from the squad
     */
    public function remove(CrewKey $key): void
    {
        unset($this->crews[$key->toString()]);
    }

    /**
     * Get a crew member by key
     */
    public function get(CrewKey $key): ?Crew
    {
        return $this->crews[$key->toString()] ?? null;
    }

    /**
     * Check if a crew member exists
     */
    public function has(CrewKey $key): bool
    {
        return isset($this->crews[$key->toString()]);
    }

    /**
     * Get all crew members
     *
     * @return array<Crew>
     */
    public function all(): array
    {
        return array_values($this->crews);
    }

    /**
     * Get crews available for an event
     *
     * @return array<Crew>
     */
    public function getAvailableFor(EventId $eventId): array
    {
        return array_values(
            array_filter(
                $this->crews,
                fn(Crew $crew) => $crew->isAvailableFor($eventId)
            )
        );
    }

    /**
     * Get crews assigned to an event
     *
     * @return array<Crew>
     */
    public function getAssignedTo(EventId $eventId): array
    {
        return array_values(
            array_filter(
                $this->crews,
                fn(Crew $crew) => $crew->isAssignedTo($eventId)
            )
        );
    }

    /**
     * Get count of crew members
     */
    public function count(): int
    {
        return count($this->crews);
    }

    /**
     * Check if squad is empty
     */
    public function isEmpty(): bool
    {
        return empty($this->crews);
    }

    /**
     * Clear all crew members
     */
    public function clear(): void
    {
        $this->crews = [];
    }

    /**
     * Filter crews by a predicate
     *
     * @param callable(Crew): bool $predicate
     * @return array<Crew>
     */
    public function filter(callable $predicate): array
    {
        return array_values(array_filter($this->crews, $predicate));
    }

    /**
     * Map crews to a new array
     *
     * @template T
     * @param callable(Crew): T $mapper
     * @return array<T>
     */
    public function map(callable $mapper): array
    {
        return array_map($mapper, array_values($this->crews));
    }

    /**
     * Get iterator for foreach
     *
     * @return \ArrayIterator<string, Crew>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->crews);
    }
}
