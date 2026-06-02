<?php

declare(strict_types=1);

namespace NeneVault\Tests\Auth;

use NeneVault\Auth\InvalidCredentialsException;
use NeneVault\Auth\LoginInput;
use NeneVault\Auth\LoginUseCase;
use NeneVault\Auth\Role;
use NeneVault\Auth\User;
use NeneVault\Auth\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class LoginUseCaseTest extends TestCase
{
    public function test_issues_token_with_org_id_for_admin(): void
    {
        $passwordHash = password_hash('secret', PASSWORD_BCRYPT);
        $user = new User(
            id: 1,
            email: 'admin@example.com',
            passwordHash: $passwordHash,
            role: Role::Admin->value,
            organizationId: 42,
        );

        $repo = $this->createMockRepo(['admin@example.com' => $user]);
        $issuer = new InMemoryTokenIssuer();
        $useCase = new LoginUseCase($repo, $issuer);

        $output = $useCase->execute(new LoginInput('admin@example.com', 'secret'));

        $this->assertSame('admin@example.com', $output->email);
        $this->assertSame(Role::Admin->value, $output->role);
        $this->assertSame(42, $output->orgId);
        $this->assertNotEmpty($output->token);
    }

    public function test_issues_token_with_null_org_id_for_superadmin(): void
    {
        $passwordHash = password_hash('s3cr3t', PASSWORD_BCRYPT);
        $user = new User(
            id: 99,
            email: 'super@example.com',
            passwordHash: $passwordHash,
            role: Role::Superadmin->value,
            organizationId: null,
        );

        $repo = $this->createMockRepo(['super@example.com' => $user]);
        $issuer = new InMemoryTokenIssuer();
        $useCase = new LoginUseCase($repo, $issuer);

        $output = $useCase->execute(new LoginInput('super@example.com', 's3cr3t'));

        $this->assertSame(Role::Superadmin->value, $output->role);
        $this->assertNull($output->orgId);
    }

    public function test_throws_on_wrong_password(): void
    {
        $user = new User(
            id: 1,
            email: 'admin@example.com',
            passwordHash: password_hash('correct', PASSWORD_BCRYPT),
            role: Role::Admin->value,
            organizationId: 1,
        );

        $repo = $this->createMockRepo(['admin@example.com' => $user]);
        $useCase = new LoginUseCase($repo, new InMemoryTokenIssuer());

        $this->expectException(InvalidCredentialsException::class);

        $useCase->execute(new LoginInput('admin@example.com', 'wrong'));
    }

    public function test_throws_on_unknown_email(): void
    {
        $repo = $this->createMockRepo([]);
        $useCase = new LoginUseCase($repo, new InMemoryTokenIssuer());

        $this->expectException(InvalidCredentialsException::class);

        $useCase->execute(new LoginInput('nobody@example.com', 'password'));
    }

    /** @param array<string, User> $users */
    private function createMockRepo(array $users): UserRepositoryInterface
    {
        return new class ($users) implements UserRepositoryInterface {
            /** @param array<string, User> $users */
            public function __construct(private array $users)
            {
            }

            public function findByEmail(string $email): ?User
            {
                return $this->users[$email] ?? null;
            }

            public function findById(int $id): ?User
            {
                return null;
            }
            public function listByOrganizationId(int $organizationId, int $limit, int $offset): array
            {
                return [];
            }
            public function countByOrganizationId(int $organizationId): int
            {
                return 0;
            }
            public function create(string $email, string $passwordHash, string $role, ?int $organizationId): User
            {
                throw new \LogicException('not implemented');
            }
            public function updatePassword(int $id, string $passwordHash): void
            {
            }
            public function updateStatus(int $id, string $status): void
            {
            }
            public function updateRole(int $id, string $role): void
            {
            }
            public function updateEmail(int $id, string $email): void
            {
            }
            public function storeInviteToken(int $id, string $tokenHash, int $expiresAt): void
            {
            }
            public function findByInviteToken(string $tokenHash): ?User
            {
                return null;
            }
            public function clearInviteToken(int $id): void
            {
            }
            public function storePasswordResetToken(int $id, string $tokenHash, int $expiresAt): void
            {
            }
            public function findByPasswordResetToken(string $tokenHash): ?User
            {
                return null;
            }
            public function clearPasswordResetToken(int $id): void
            {
            }
            public function delete(int $id): void
            {
            }
        };
    }
}
