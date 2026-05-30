<?php

declare(strict_types=1);

namespace NeneVault\Auth;

final readonly class User
{
    public function __construct(
        public int $id,
        public string $email,
        public string $passwordHash,
        public string $role,
        public ?int $organizationId = null,
        public string $status = 'active',
        public ?string $inviteTokenHash = null,
        public ?string $inviteExpiresAt = null,
        public ?string $passwordResetTokenHash = null,
        public ?string $passwordResetExpiresAt = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {
    }
}
