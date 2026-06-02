<?php

declare(strict_types=1);

namespace NeneVault\Organization;

use Closure;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use NeneVault\Audit\AuditAction;
use NeneVault\Audit\AuditRecorderInterface;

final readonly class DeleteOrganizationUseCase implements DeleteOrganizationUseCaseInterface
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): OrganizationRepositoryInterface $organizationRepository
     * @param Closure(DatabaseQueryExecutorInterface): AuditRecorderInterface          $auditRecorder
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $transactionManager,
        private Closure $organizationRepository,
        private Closure $auditRecorder,
    ) {
    }

    public function execute(int $id, ?int $actorUserId = null): void
    {
        $this->transactionManager->transactional(
            function (DatabaseQueryExecutorInterface $executor) use ($id, $actorUserId): void {
                $organizations = ($this->organizationRepository)($executor);
                $audit = ($this->auditRecorder)($executor);

                $org = $organizations->findById($id);

                if ($org === null) {
                    throw new OrganizationNotFoundException($id);
                }

                // Capture full before state before deletion
                $beforeJson = [
                    'id'            => $org->id,
                    'name'          => $org->name,
                    'slug'          => $org->slug,
                    'plan'          => $org->plan,
                    'is_active'     => $org->isActive,
                    'external_id'   => $org->externalId,
                    'custom_domain' => $org->customDomain,
                ];

                $organizations->delete($id);

                $audit->record(
                    action: AuditAction::ORGANIZATION_DELETED,
                    entityType: 'organization',
                    entityId: (string) $id,
                    actorUserId: $actorUserId,
                    organizationId: null,
                    beforeJson: $beforeJson,
                    afterJson: null,
                );
            },
        );
    }
}
