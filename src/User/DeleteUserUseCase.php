<?php

declare(strict_types=1);

namespace NeneVault\User;

use NeneVault\Audit\AuditAction;
use NeneVault\Audit\AuditRecorderInterface;
use NeneVault\Auth\UserRepositoryInterface;

final readonly class DeleteUserUseCase implements DeleteUserUseCaseInterface
{
    public function __construct(
        private UserRepositoryInterface $users,
        private AuditRecorderInterface $audit,
    ) {
    }

    public function execute(int $id, int $organizationId, ?int $actorUserId): void
    {
        if ($actorUserId !== null && $actorUserId === $id) {
            throw new CannotDeleteSelfException();
        }

        $user = $this->users->findById($id);

        if ($user === null || $user->organizationId !== $organizationId) {
            throw new UserNotFoundException($id);
        }

        $before = UserPresenter::present($user);

        $this->users->delete($id);

        $this->audit->record(
            action: AuditAction::USER_DELETED,
            entityType: 'user',
            entityId: (string) $id,
            actorUserId: $actorUserId,
            organizationId: $organizationId,
            beforeJson: $before,
            afterJson: null,
        );
    }
}
