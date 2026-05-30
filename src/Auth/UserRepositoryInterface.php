<?php

declare(strict_types=1);

namespace NeneVault\Auth;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    public function findByEmail(string $email): ?User;

    /** @return list<User> */
    public function listByOrganizationId(int $organizationId): array;

    public function create(string $email, string $passwordHash, string $role, ?int $organizationId): User;

    public function updatePassword(int $id, string $passwordHash): void;

    public function updateStatus(int $id, string $status): void;

    public function storeInviteToken(int $id, string $tokenHash, int $expiresAt): void;

    public function findByInviteToken(string $tokenHash): ?User;

    public function clearInviteToken(int $id): void;

    public function storePasswordResetToken(int $id, string $tokenHash, int $expiresAt): void;

    public function findByPasswordResetToken(string $tokenHash): ?User;

    public function clearPasswordResetToken(int $id): void;

    public function delete(int $id): void;
}
