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

final readonly class UpdateUserUseCase implements UpdateUserUseCaseInterface
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
        int $id,
        int $organizationId,
        ?string $email,
        ?string $role,
        ?string $status,
        ?int $actorUserId,
    ): User {
        if ($role !== null) {
            $roleEnum = Role::tryFrom($role);

            if ($roleEnum === null || $roleEnum === Role::Superadmin) {
                throw new InvalidUserRoleException($role);
            }
        }

        return $this->transactionManager->transactional(
            function (DatabaseQueryExecutorInterface $executor) use ($id, $organizationId, $email, $role, $status, $actorUserId): User {
                $users = ($this->userRepository)($executor);
                $audit = $this->auditRecorderFactory->forExecutor($executor);

                $user = $users->findById($id);

                // Org-scoped: only users in the resolved organization are visible
                if ($user === null || $user->organizationId !== $organizationId) {
                    throw new UserNotFoundException($id);
                }

                $before = UserPresenter::present($user);

                if ($role !== null) {
                    $users->updateRole($id, $role);
                }

                if ($email !== null && $email !== $user->email) {
                    $existing = $users->findByEmail($email);

                    if ($existing !== null && $existing->id !== $id) {
                        throw new UserEmailConflictException($email);
                    }

                    $users->updateEmail($id, $email);
                }

                if ($status !== null) {
                    $users->updateStatus($id, $status);
                }

                $updated = $users->findById($id);
                assert($updated !== null);

                $audit->record(new AuditEvent(
                    action: AuditAction::USER_UPDATED,
                    entityType: 'user',
                    entityId: (string) $id,
                    actorId: $actorUserId,
                    organizationId: $organizationId,
                    before: $before,
                    after: UserPresenter::present($updated),
                ));

                return $updated;
            },
        );
    }
}
