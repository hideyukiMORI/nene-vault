<?php

declare(strict_types=1);

namespace NeneVault\User;

use NeneVault\Audit\AuditAction;
use NeneVault\Audit\AuditRecorderInterface;
use NeneVault\Auth\Role;
use NeneVault\Auth\User;
use NeneVault\Auth\UserRepositoryInterface;

final readonly class UpdateUserUseCase implements UpdateUserUseCaseInterface
{
    public function __construct(
        private UserRepositoryInterface $users,
        private AuditRecorderInterface $audit,
    ) {
    }

    public function execute(
        int $id,
        int $organizationId,
        ?string $email,
        ?string $role,
        ?string $status,
        ?int $actorUserId,
    ): User {
        $user = $this->users->findById($id);

        // Org-scoped: only users in the resolved organization are visible
        if ($user === null || $user->organizationId !== $organizationId) {
            throw new UserNotFoundException($id);
        }

        $before = UserPresenter::present($user);

        if ($role !== null) {
            $roleEnum = Role::tryFrom($role);

            if ($roleEnum === null || $roleEnum === Role::Superadmin) {
                throw new InvalidUserRoleException($role);
            }

            $this->users->updateRole($id, $role);
        }

        if ($email !== null && $email !== $user->email) {
            $existing = $this->users->findByEmail($email);

            if ($existing !== null && $existing->id !== $id) {
                throw new UserEmailConflictException($email);
            }

            $this->users->updateEmail($id, $email);
        }

        if ($status !== null) {
            $this->users->updateStatus($id, $status);
        }

        $updated = $this->users->findById($id);
        assert($updated !== null);

        $this->audit->record(
            action: AuditAction::USER_UPDATED,
            entityType: 'user',
            entityId: (string) $id,
            actorUserId: $actorUserId,
            organizationId: $organizationId,
            beforeJson: $before,
            afterJson: UserPresenter::present($updated),
        );

        return $updated;
    }
}
