<?php

declare(strict_types=1);

namespace NeneVault\User;

use NeneVault\Audit\AuditAction;
use NeneVault\Audit\AuditRecorderInterface;
use NeneVault\Auth\Role;
use NeneVault\Auth\User;
use NeneVault\Auth\UserRepositoryInterface;

final readonly class CreateUserUseCase implements CreateUserUseCaseInterface
{
    public function __construct(
        private UserRepositoryInterface $users,
        private AuditRecorderInterface $audit,
    ) {
    }

    public function execute(
        string $email,
        string $password,
        string $role,
        int $organizationId,
        ?int $actorUserId,
    ): User {
        $roleEnum = Role::tryFrom($role);

        // superadmin cannot be created via the org user API (platform-level only)
        if ($roleEnum === null || $roleEnum === Role::Superadmin) {
            throw new InvalidUserRoleException($role);
        }

        if ($this->users->findByEmail($email) !== null) {
            throw new UserEmailConflictException($email);
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $user = $this->users->create($email, $passwordHash, $role, $organizationId);

        $this->audit->record(
            action: AuditAction::USER_CREATED,
            entityType: 'user',
            entityId: (string) $user->id,
            actorUserId: $actorUserId,
            organizationId: $organizationId,
            beforeJson: null,
            afterJson: UserPresenter::present($user),
        );

        return $user;
    }
}
