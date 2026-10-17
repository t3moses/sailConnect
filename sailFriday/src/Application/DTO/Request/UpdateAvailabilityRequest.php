<?php

declare(strict_types=1);

namespace App\Application\DTO\Request;

/**
 * Update Availability Request DTO
 *
 * Data transfer object for updating availability across multiple events.
 */
final readonly class UpdateAvailabilityRequest
{
    /**
     * @param array<int, array{eventId: string, isAvailable: bool, berths?: int}> $availabilities Array of availability objects
     */
    public function __construct(
        public array $availabilities,
    ) {
    }

    /**
     * Create from array (e.g., HTTP request data)
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            availabilities: $data['availabilities'] ?? [],
        );
    }

    /**
     * Validate the request
     *
     * @return array<string, string> Validation errors (empty if valid)
     */
    public function validate(): array
    {
        $errors = [];

        // if (empty($this->availabilities) || !is_array($this->availabilities)) {
        //     $errors['availabilities'] = 'At least one availability entry is required';
        //     return $errors;
        // }

        foreach ($this->availabilities as $index => $availability) {
            if (!is_array($availability)) {
                $errors["availabilities[$index]"] = 'Each availability must be an object';
                continue;
            }

            if (empty($availability['eventId']) || !is_string($availability['eventId'])) {
                $errors["availabilities[$index].eventId"] = 'Event ID is required and must be a string';
            }

            if (!isset($availability['isAvailable']) || !is_bool($availability['isAvailable'])) {
                $errors["availabilities[$index].isAvailable"] = 'isAvailable is required and must be a boolean';
            }

            if (isset($availability['berths']) && (!is_int($availability['berths']) || $availability['berths'] < 0)) {
                $errors["availabilities[$index].berths"] = 'berths must be a non-negative integer';
            }
        }

        return $errors;
    }
}
