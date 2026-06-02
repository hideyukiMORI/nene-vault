<?php

declare(strict_types=1);

namespace NeneVault\User;

use Closure;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use NeneVault\Audit\AuditAction;
use NeneVault\Audit\AuditRecorderInterface;
use NeneVault\Auth\UserRepositoryInterface;

final readonly class DeleteUserUseCase implements DeleteUserUseCaseInterface
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

    public function execute(int $id, int $organizationId, ?int $actorUserId): void
    {
        if ($actorUserId !== null && $actorUserId === $id) {
            throw new CannotDeleteSelfException();
        }

        $this->transactionManager->transactional(
            function (DatabaseQueryExecutorInterface $executor) use ($id, $organizationId, $actorUserId): void {
                $users = ($this->userRepository)($executor);
                $audit = ($this->auditRecorder)($executor);

                $user = $users->findById($id);

                if ($user === null || $user->organizationId !== $organizationId) {
                    throw new UserNotFoundException($id);
                }

                $before = UserPresenter::present($user);

                $users->delete($id);

                $audit->record(
                    action: AuditAction::USER_DELETED,
                    entityType: 'user',
                    entityId: (string) $id,
                    actorUserId: $actorUserId,
                    organizationId: $organizationId,
                    beforeJson: $before,
                    afterJson: null,
                );
            },
        );
    }
}
