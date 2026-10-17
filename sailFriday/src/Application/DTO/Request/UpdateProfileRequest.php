<?php

declare(strict_types=1);

namespace App\Application\DTO\Request;

/**
 * Update Profile Request DTO
 *
 * Data Transfer Object for updating user profile (email, password, crew profile, boat profile).
 */
final readonly class UpdateProfileRequest
{
    public function __construct(
        public ?string $email = null,
        public ?string $password = null,
        public ?array $crewProfile = null,
        public ?array $boatProfile = null,
    ) {
    }

    /**
     * Create from array (e.g., HTTP request data)
     *
     * @param array $data Request data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            email: $data['email'] ?? null,
            password: $data['password'] ?? null,
            crewProfile: $data['crewProfile'] ?? null,
            boatProfile: $data['boatProfile'] ?? null,
        );
    }

    /**
     * Validate the request
     *
     * @return array Validation errors (empty if valid)
     */
    public function validate(): array
    {
        $errors = [];

        // Validate email if provided
        if ($this->email !== null && !empty($this->email)) {
            if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Invalid email format';
            }
        }

        // Validate password if provided
        if ($this->password !== null && !empty($this->password)) {
            if (strlen($this->password) < 8) {
                $errors['password'] = 'Password must be at least 8 characters';
            }
        }

        // Crew and boat profile validation is handled by respective use cases
        // since they need to check if user has permission to update those profiles

        return $errors;
    }

    /**
     * Check if request has any updates
     *
     * @return bool True if at least one field is provided for update
     */
    public function hasUpdates(): bool
    {
        return $this->email !== null
            || $this->password !== null
            || $this->crewProfile !== null
            || $this->boatProfile !== null;
    }
}
