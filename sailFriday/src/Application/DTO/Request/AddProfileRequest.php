<?php

declare(strict_types=1);

namespace App\Application\DTO\Request;

/**
 * Add Profile Request DTO
 *
 * Data Transfer Object for adding crew or boat profile to existing user account.
 */
final readonly class AddProfileRequest
{
    public function __construct(
        public string $profileType,
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
            profileType: $data['profileType'] ?? '',
            crewProfile: $data['crewProfile'] ?? null,
            boatProfile: $data['boatProfile'] ?? null,
        );
    }

    /**
     * Copy with names, mobile number and membership taken from the sailConnect identity
     *
     * @param array $identity first_name, last_name, mobile_number, is_member, membership_number
     * @return self
     */
    public function withIdentity(array $identity): self
    {
        $crew = $this->crewProfile;
        $boat = $this->boatProfile;
        if (is_array($crew)) {
            $crew['firstName'] = $identity['first_name'];
            $crew['lastName'] = $identity['last_name'];
            $crew['mobile'] = $identity['mobile_number'] ?? null;
            $crew['membershipNumber'] = !empty($identity['is_member']) ? $identity['membership_number'] : null;
        }
        if (is_array($boat)) {
            $boat['ownerFirstName'] = $identity['first_name'];
            $boat['ownerLastName'] = $identity['last_name'];
            $boat['ownerMobile'] = $identity['mobile_number'] ?? null;
        }

        return new self($this->profileType, $crew, $boat);
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

        // Validate profile type
        if (empty($this->profileType)) {
            $errors['profileType'] = 'Profile type is required';
        } elseif (!in_array($this->profileType, ['crew', 'boat'], true)) {
            $errors['profileType'] = 'Profile type must be "crew" or "boat"';
        }

        // Validate that the corresponding profile data is provided
        if ($this->profileType === 'crew') {
            if (!is_array($this->crewProfile) || empty($this->crewProfile)) {
                $errors['crewProfile'] = 'Crew profile data is required';
            } else {
                $errors = array_merge($errors, $this->validateCrewProfile($this->crewProfile));
            }
        } elseif ($this->profileType === 'boat') {
            if (!is_array($this->boatProfile) || empty($this->boatProfile)) {
                $errors['boatProfile'] = 'Boat profile data is required';
            } else {
                $errors = array_merge($errors, $this->validateBoatProfile($this->boatProfile));
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

        // Optional fields: displayName, skill, socialPreference, experience, partnerKey

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
            $errors['boatProfile.minBerths'] = 'Minimum berths is required and must be a number';
        }

        if (!isset($profile['maxBerths']) || !is_numeric($profile['maxBerths'])) {
            $errors['boatProfile.maxBerths'] = 'Maximum berths is required and must be a number';
        }

        return $errors;
    }
}
