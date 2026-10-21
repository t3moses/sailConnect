<?php

declare(strict_types=1);

namespace App\Domain\Entity;

/**
 * User Entity
 *
 * Represents a Sail Friday account for a sailConnect identity (auth service). Its ID is the sailConnect user ID.
 * Users can be crew members, boat owners, or both (via separate profile records).
 *
 * The email address is owned by sailConnect and is read through, never stored here; credentials
 * are not known to Sail Friday at all.
 *
 * Note: This entity is separate from Crew and Boat entities.
 */
class User
{
    private ?string $id;

    public function __construct(
        private string $email,
        private string $accountType,
        private bool $isAdmin = false,
        private ?\DateTimeImmutable $createdAt = null,
        private ?\DateTimeImmutable $updatedAt = null,
        ?string $id = null,
    ) {
        $this->id = $id;
        $this->validateEmail($email);
        $this->validateAccountType($accountType);

        if ($this->createdAt === null) {
            $this->createdAt = new \DateTimeImmutable();
        }
        if ($this->updatedAt === null) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    /**
     * Get user ID
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Set user ID (called by repository after insert)
     */
    public function setId(string $id): void
    {
        if ($this->id !== null) {
            throw new \RuntimeException('User ID is already set');
        }
        $this->id = $id;
    }

    /**
     * Get email address (from the sailConnect identity)
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * Get account type
     */
    public function getAccountType(): string
    {
        return $this->accountType;
    }

    /**
     * Set account type
     */
    public function setAccountType(string $accountType): void
    {
        $this->validateAccountType($accountType);
        $this->accountType = $accountType;
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->isAdmin;
    }

    /**
     * Set admin status
     */
    public function setIsAdmin(bool $isAdmin): void
    {
        $this->isAdmin = $isAdmin;
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Check if user can manage crew profile
     */
    public function canManageCrew(): bool
    {
        return $this->accountType === 'crew';
    }

    /**
     * Check if user can manage boat profile
     */
    public function canManageBoat(): bool
    {
        return $this->accountType === 'boat_owner';
    }

    /**
     * Get created at timestamp
     */
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Get updated at timestamp
     */
    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Convert to array representation
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'account_type' => $this->accountType,
            'is_admin' => $this->isAdmin,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Validate email format
     */
    private function validateEmail(string $email): void
    {
        if (empty($email)) {
            throw new \InvalidArgumentException('Email cannot be empty');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email format');
        }
    }

    /**
     * Validate account type
     */
    private function validateAccountType(string $accountType): void
    {
        $validTypes = ['crew', 'boat_owner'];

        if (!in_array($accountType, $validTypes, true)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid account type: %s. Must be one of: %s', $accountType, implode(', ', $validTypes))
            );
        }
    }
}
