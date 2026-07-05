<?php

declare(strict_types=1);

namespace NeneVault\User;

use Closure;
use Nene2\Audit\AuditEvent;
use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use NeneVault\Audit\AuditAction;
use NeneVault\Auth\UserRepositoryInterface;

final readonly class DeleteUserUseCase implements DeleteUserUseCaseInterface
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

    public function execute(int $id, int $organizationId, ?int $actorUserId): void
    {
        if ($actorUserId !== null && $actorUserId === $id) {
            throw new CannotDeleteSelfException();
        }

        $this->transactionManager->transactional(
            function (DatabaseQueryExecutorInterface $executor) use ($id, $organizationId, $actorUserId): void {
                $users = ($this->userRepository)($executor);
                $audit = $this->auditRecorderFactory->forExecutor($executor);

                $user = $users->findById($id);

                if ($user === null || $user->organizationId !== $organizationId) {
                    throw new UserNotFoundException($id);
                }

                $before = UserPresenter::present($user);

                $users->delete($id);

                $audit->record(new AuditEvent(
                    action: AuditAction::USER_DELETED,
                    entityType: 'user',
                    entityId: (string) $id,
                    actorId: $actorUserId,
                    organizationId: $organizationId,
                    before: $before,
                    after: null,
                ));
            },
        );
    }
}
