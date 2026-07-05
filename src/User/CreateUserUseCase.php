<?php

declare(strict_types=1);

namespace NeneVault\User;

use Closure;
use Nene2\Audit\AuditEvent;
use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use NeneVault\Audit\AuditAction;
use NeneVault\Auth\Role;
use NeneVault\Auth\User;
use NeneVault\Auth\UserRepositoryInterface;

final readonly class CreateUserUseCase implements CreateUserUseCaseInterface
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): UserRepositoryInterface $userRepository
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $transactionManager,
        private Closure $userRepository,
        private AuditRecorderFactoryInterface $auditRecorderFactory,
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
                $audit = $this->auditRecorderFactory->forExecutor($executor);

                if ($users->findByEmail($email) !== null) {
                    throw new UserEmailConflictException($email);
                }

                $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                $user = $users->create($email, $passwordHash, $role, $organizationId);

                $audit->record(new AuditEvent(
                    action: AuditAction::USER_CREATED,
                    entityType: 'user',
                    entityId: (string) $user->id,
                    actorId: $actorUserId,
                    organizationId: $organizationId,
                    before: null,
                    after: UserPresenter::present($user),
                ));

                return $user;
            },
        );
    }
}
