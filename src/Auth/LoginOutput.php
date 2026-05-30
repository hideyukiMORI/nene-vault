<?php

declare(strict_types=1);

namespace NeneVault\Auth;

final readonly class LoginOutput
{
    public function __construct(
        public string $token,
        public int $expiresAt,
        public string $email,
        public string $role,
        public ?int $orgId,
        public int $userId,
    ) {
    }
}
