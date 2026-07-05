<?php

declare(strict_types=1);

namespace NeneVault\Organization;

use Closure;
use Nene2\Audit\AuditEvent;
use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use NeneVault\Audit\AuditAction;

final readonly class DeleteOrganizationUseCase implements DeleteOrganizationUseCaseInterface
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): OrganizationRepositoryInterface $organizationRepository
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $transactionManager,
        private Closure $organizationRepository,
        private AuditRecorderFactoryInterface $auditRecorderFactory,
    ) {
    }

    public function execute(int $id, ?int $actorUserId = null): void
    {
        $this->transactionManager->transactional(
            function (DatabaseQueryExecutorInterface $executor) use ($id, $actorUserId): void {
                $organizations = ($this->organizationRepository)($executor);
                $audit = $this->auditRecorderFactory->forExecutor($executor);

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

                $audit->record(new AuditEvent(
                    action: AuditAction::ORGANIZATION_DELETED,
                    entityType: 'organization',
                    entityId: (string) $id,
                    actorId: $actorUserId,
                    organizationId: null,
                    before: $beforeJson,
                    after: null,
                ));
            },
        );
    }
}
