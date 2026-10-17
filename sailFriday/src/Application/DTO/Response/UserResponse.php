<?php

declare(strict_types=1);

namespace App\Application\DTO\Response;

use App\Domain\Entity\User;

/**
 * User Response DTO
 *
 * Data Transfer Object for user data in API responses.
 */
final readonly class UserResponse
{
    public function __construct(
        public int $id,
        public string $email,
        public string $accountType,
        public bool $isAdmin,
        public ?string $createdAt = null,
        public ?string $lastLogin = null,
    ) {
    }

    /**
     * Create from User entity
     *
     * @param User $user User entity
     * @return self
     */
    public static function fromEntity(User $user): self
    {
        return new self(
            id: $user->getId(),
            email: $user->getEmail(),
            accountType: $user->getAccountType(),
            isAdmin: $user->isAdmin(),
            createdAt: $user->getCreatedAt()->format('Y-m-d H:i:s'),
            lastLogin: $user->getLastLogin()?->format('Y-m-d H:i:s'),
        );
    }

    /**
     * Convert to array representation
     *
     * @return array
     */
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'email' => $this->email,
            'accountType' => $this->accountType,
            'isAdmin' => $this->isAdmin,
        ];

        if ($this->createdAt !== null) {
            $data['createdAt'] = $this->createdAt;
        }

        if ($this->lastLogin !== null) {
            $data['lastLogin'] = $this->lastLogin;
        }

        return $data;
    }
}
