<?php

declare(strict_types=1);

namespace App\Application\DTO\Response;

/**
 * Auth Response DTO
 *
 * Data Transfer Object for authentication responses (register, login).
 */
final readonly class AuthResponse
{
    public function __construct(
        public string $token,
        public UserResponse $user,
        public int $expiresIn,
    ) {
    }

    /**
     * Convert to array representation
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'user' => $this->user->toArray(),
            'expiresIn' => $this->expiresIn,
        ];
    }
}
