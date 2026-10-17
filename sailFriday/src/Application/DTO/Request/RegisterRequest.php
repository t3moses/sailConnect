<?php

declare(strict_types=1);

namespace App\Application\DTO\Request;

/**
 * Register Request DTO
 *
 * Data Transfer Object for user registration requests.
 * Handles both crew and boat_owner account types with their respective profiles.
 */
final readonly class RegisterRequest
{
    public function __construct(
        public string $email,
        public string $password,
        public string $accountType,
        public ?array $profile = null,
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
            email: $data['email'] ?? '',
            password: $data['password'] ?? '',
            accountType: $data['accountType'] ?? '',
            profile: $data['profile'] ?? null,
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

        // Validate email
        if (empty($this->email)) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }

        // Validate password
        if (empty($this->password)) {
            $errors['password'] = 'Password is required';
        } elseif (strlen($this->password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters';
        }

        // Validate account type
        if (empty($this->accountType)) {
            $errors['accountType'] = 'Account type is required';
        } elseif (!in_array($this->accountType, ['crew', 'boat_owner'], true)) {
            $errors['accountType'] = 'Account type must be "crew" or "boat_owner"';
        }

        // Validate profile
        if (!is_array($this->profile) || empty($this->profile)) {
            $errors['profile'] = 'Profile data is required';
        } else {
            // Validate based on account type
            if ($this->accountType === 'crew') {
                $errors = array_merge($errors, $this->validateCrewProfile($this->profile));
            } elseif ($this->accountType === 'boat_owner') {
                $errors = array_merge($errors, $this->validateBoatProfile($this->profile));
            }
        }

        return $errors;
    }

    /**
     * Validate crew profile data
     *
     * @param array $profile Profile data
     * @return array Validation errors
     */
    private function validateCrewProfile(array $profile): array
    {
        $errors = [];

        if (empty($profile['firstName'])) {
            $errors['profile.firstName'] = 'First name is required';
        }

        if (empty($profile['lastName'])) {
            $errors['profile.lastName'] = 'Last name is required';
        }

        // Optional fields: displayName, mobile, skill, membershipNumber, socialPreference, experience, partnerKey

        return $errors;
    }

    /**
     * Validate boat profile data
     *
     * @param array $profile Profile data
     * @return array Validation errors
     */
    private function validateBoatProfile(array $profile): array
    {
        $errors = [];

        if (empty($profile['ownerFirstName'])) {
            $errors['profile.ownerFirstName'] = 'Owner first name is required';
        }

        if (empty($profile['ownerLastName'])) {
            $errors['profile.ownerLastName'] = 'Owner last name is required';
        }

        if (!isset($profile['minBerths']) || !is_numeric($profile['minBerths'])) {
            $errors['profile.minBerths'] = 'Minimum berths is required and must be a number';
        } elseif ((int)$profile['minBerths'] < 1) {
            $errors['profile.minBerths'] = 'Minimum berths must be at least 1';
        }

        if (!isset($profile['maxBerths']) || !is_numeric($profile['maxBerths'])) {
            $errors['profile.maxBerths'] = 'Maximum berths is required and must be a number';
        } elseif ((int)$profile['maxBerths'] < 1) {
            $errors['profile.maxBerths'] = 'Maximum berths must be at least 1';
        }

        if (isset($profile['minBerths'], $profile['maxBerths']) && (int)$profile['maxBerths'] < (int)$profile['minBerths']) {
            $errors['profile.maxBerths'] = 'Maximum berths cannot be less than minimum berths';
        }

        // Optional fields: ownerMobile, assistanceRequired, socialPreference

        return $errors;
    }
}
