<?php

declare(strict_types=1);

namespace NeneVault\Auth;

use Nene2\Database\DatabaseQueryExecutorInterface;

final readonly class PdoUserRepository implements UserRepositoryInterface
{
    private const SELECT_COLUMNS = '
        id, email, password_hash, role, organization_id, status,
        invite_token_hash, invite_expires_at,
        password_reset_token_hash, password_reset_expires_at,
        created_at,
        updated_at
    ';

    public function __construct(
        private DatabaseQueryExecutorInterface $query,
    ) {
    }

    public function findById(int $id): ?User
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::SELECT_COLUMNS . ' FROM users WHERE id = ?',
            [$id],
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::SELECT_COLUMNS . ' FROM users WHERE email = ?',
            [$email],
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    /** @return list<User> */
    public function listByOrganizationId(int $organizationId): array
    {
        $rows = $this->query->fetchAll(
            'SELECT ' . self::SELECT_COLUMNS . ' FROM users WHERE organization_id = ? ORDER BY id ASC',
            [$organizationId],
        );

        return array_map($this->mapRow(...), $rows);
    }

    public function create(
        string $email,
        string $passwordHash,
        string $role,
        ?int $organizationId,
    ): User {
        $now = date('Y-m-d H:i:s');
        $this->query->execute(
            'INSERT INTO users (email, password_hash, role, organization_id, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$email, $passwordHash, $role, $organizationId, 'active', $now, $now],
        );

        $user = $this->findByEmail($email);
        assert($user !== null);

        return $user;
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $this->query->execute(
            'UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?',
            [$passwordHash, date('Y-m-d H:i:s'), $id],
        );
    }

    public function updateStatus(int $id, string $status): void
    {
        $this->query->execute(
            'UPDATE users SET status = ?, updated_at = ? WHERE id = ?',
            [$status, date('Y-m-d H:i:s'), $id],
        );
    }

    public function updateRole(int $id, string $role): void
    {
        $this->query->execute(
            'UPDATE users SET role = ?, updated_at = ? WHERE id = ?',
            [$role, date('Y-m-d H:i:s'), $id],
        );
    }

    public function updateEmail(int $id, string $email): void
    {
        $this->query->execute(
            'UPDATE users SET email = ?, updated_at = ? WHERE id = ?',
            [$email, date('Y-m-d H:i:s'), $id],
        );
    }

    public function storeInviteToken(int $id, string $tokenHash, int $expiresAt): void
    {
        $this->query->execute(
            'UPDATE users SET invite_token_hash = ?, invite_expires_at = ?, status = ?, updated_at = NOW() WHERE id = ?',
            [$tokenHash, date('Y-m-d H:i:s', $expiresAt), 'invited', $id],
        );
    }

    public function findByInviteToken(string $tokenHash): ?User
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::SELECT_COLUMNS . ' FROM users WHERE invite_token_hash = ?',
            [$tokenHash],
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    public function clearInviteToken(int $id): void
    {
        $this->query->execute(
            'UPDATE users SET invite_token_hash = NULL, invite_expires_at = NULL, status = ?, updated_at = NOW() WHERE id = ?',
            ['active', $id],
        );
    }

    public function storePasswordResetToken(int $id, string $tokenHash, int $expiresAt): void
    {
        $this->query->execute(
            'UPDATE users SET password_reset_token_hash = ?, password_reset_expires_at = ?, updated_at = NOW() WHERE id = ?',
            [$tokenHash, date('Y-m-d H:i:s', $expiresAt), $id],
        );
    }

    public function findByPasswordResetToken(string $tokenHash): ?User
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::SELECT_COLUMNS . ' FROM users WHERE password_reset_token_hash = ?',
            [$tokenHash],
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    public function clearPasswordResetToken(int $id): void
    {
        $this->query->execute(
            'UPDATE users SET password_reset_token_hash = NULL, password_reset_expires_at = NULL, updated_at = NOW() WHERE id = ?',
            [$id],
        );
    }

    public function delete(int $id): void
    {
        $this->query->execute('DELETE FROM users WHERE id = ?', [$id]);
    }

    /** @param array<string, mixed> $row */
    private function mapRow(array $row): User
    {
        return new User(
            id: (int) $row['id'],
            email: (string) $row['email'],
            passwordHash: (string) $row['password_hash'],
            role: (string) $row['role'],
            organizationId: isset($row['organization_id']) ? (int) $row['organization_id'] : null,
            status: (string) ($row['status'] ?? 'active'),
            inviteTokenHash: isset($row['invite_token_hash']) ? (string) $row['invite_token_hash'] : null,
            inviteExpiresAt: isset($row['invite_expires_at']) ? (string) $row['invite_expires_at'] : null,
            passwordResetTokenHash: isset($row['password_reset_token_hash']) ? (string) $row['password_reset_token_hash'] : null,
            passwordResetExpiresAt: isset($row['password_reset_expires_at']) ? (string) $row['password_reset_expires_at'] : null,
            createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
            updatedAt: isset($row['updated_at']) ? (string) $row['updated_at'] : null,
        );
    }
}
