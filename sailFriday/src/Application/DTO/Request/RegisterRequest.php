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
            accountType: $data['accountType'] ?? '',
            profile: $data['profile'] ?? null,
        );
    }

    /**
     * Copy with names, mobile number and membership taken from the sailConnect identity
     *
     * These are never accepted from the request body and never stored by Sail Friday.
     *
     * @param array $identity first_name, last_name, mobile_number, is_member, membership_number
     * @return self
     */
    public function withIdentity(array $identity): self
    {
        $profile = $this->profile ?? [];
        if ($this->accountType === 'crew') {
            $profile['firstName'] = $identity['first_name'];
            $profile['lastName'] = $identity['last_name'];
            $profile['membershipNumber'] = !empty($identity['is_member']) ? $identity['membership_number'] : null;
            $profile['mobile'] = $identity['mobile_number'] ?? null;
        } else {
            $profile['ownerFirstName'] = $identity['first_name'];
            $profile['ownerLastName'] = $identity['last_name'];
            $profile['ownerMobile'] = $identity['mobile_number'] ?? null;
        }

        return new self($this->accountType, $profile);
    }

    /**
     * Validate the request
     *
     * Names, mobile number and membership are not validated here: they come from the sailConnect identity.
     *
     * @return array Validation errors (empty if valid)
     */
    public function validate(): array
    {
        $errors = [];

        // Validate account type
        if (empty($this->accountType)) {
            $errors['accountType'] = 'Account type is required';
        } elseif (!in_array($this->accountType, ['crew', 'boat_owner'], true)) {
            $errors['accountType'] = 'Account type must be "crew" or "boat_owner"';
        }

        // Validate profile
        if (!is_array($this->profile)) {
            $errors['profile'] = 'Profile data is required';
        } elseif ($this->accountType === 'boat_owner') {
            $errors = array_merge($errors, $this->validateBoatProfile($this->profile));
        }

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

        // Optional fields: assistanceRequired, socialPreference

        return $errors;
    }
}
