<?php

declare(strict_types=1);

namespace App\Application\DTO\Request;

/**
 * Login Request DTO
 *
 * Data Transfer Object for user login requests.
 */
final readonly class LoginRequest
{
    public function __construct(
        public string $email,
        public string $password,
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

        if (empty($this->email)) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }

        if (empty($this->password)) {
            $errors['password'] = 'Password is required';
        }

        return $errors;
    }
}
