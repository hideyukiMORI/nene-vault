<?php

declare(strict_types=1);

namespace NeneVault\User;

use Closure;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use NeneVault\Audit\AuditAction;
use NeneVault\Audit\AuditRecorderInterface;
use NeneVault\Auth\Role;
use NeneVault\Auth\User;
use NeneVault\Auth\UserRepositoryInterface;

final readonly class CreateUserUseCase implements CreateUserUseCaseInterface
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): UserRepositoryInterface $userRepository
     * @param Closure(DatabaseQueryExecutorInterface): AuditRecorderInterface  $auditRecorder
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $transactionManager,
        private Closure $userRepository,
        private Closure $auditRecorder,
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

        return $this->transactionManager->transactional(
            function (DatabaseQueryExecutorInterface $executor) use ($email, $password, $role, $organizationId, $actorUserId): User {
                $users = ($this->userRepository)($executor);
                $audit = ($this->auditRecorder)($executor);

                if ($users->findByEmail($email) !== null) {
                    throw new UserEmailConflictException($email);
                }

                $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                $user = $users->create($email, $passwordHash, $role, $organizationId);

                $audit->record(
                    action: AuditAction::USER_CREATED,
                    entityType: 'user',
                    entityId: (string) $user->id,
                    actorUserId: $actorUserId,
                    organizationId: $organizationId,
                    beforeJson: null,
                    afterJson: UserPresenter::present($user),
                );

                return $user;
            },
        );
    }
}
