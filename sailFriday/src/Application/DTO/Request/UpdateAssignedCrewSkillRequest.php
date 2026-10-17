<?php

declare(strict_types=1);

namespace App\Application\DTO\Request;

/**
 * Update Assigned Crew Skill Request DTO
 *
 * Data transfer object for a boat owner correcting the skill level of a crew
 * member who was assigned to their boat for a past event.
 */
final readonly class UpdateAssignedCrewSkillRequest
{
    public function __construct(
        public string $eventId,
        public string $crewKey,
        public int $skill,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            eventId: (string)($data['eventId'] ?? ''),
            crewKey: (string)($data['crewKey'] ?? ''),
            skill: (int)($data['skill'] ?? -1),
        );
    }

    /**
     * @return array<string, string> Validation errors (empty if valid)
     */
    public function validate(): array
    {
        $errors = [];

        if ($this->eventId === '') {
            $errors['eventId'] = 'Event ID is required';
        }

        if ($this->crewKey === '') {
            $errors['crewKey'] = 'Crew key is required';
        }

        if (!in_array($this->skill, [0, 1, 2], true)) {
            $errors['skill'] = 'Skill must be 0 (Novice), 1 (Competent crew), or 2 (Competent first mate)';
        }

        return $errors;
    }
}
