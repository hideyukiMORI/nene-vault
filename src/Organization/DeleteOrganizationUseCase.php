<?php

declare(strict_types=1);

namespace NeneVault\Organization;

use NeneVault\Audit\AuditAction;
use NeneVault\Audit\AuditRecorderInterface;

final readonly class DeleteOrganizationUseCase implements DeleteOrganizationUseCaseInterface
{
    public function __construct(
        private OrganizationRepositoryInterface $organizations,
        private AuditRecorderInterface $audit,
    ) {
    }

    public function execute(int $id, ?int $actorUserId = null): void
    {
        $org = $this->organizations->findById($id);

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

        $this->organizations->delete($id);

        $this->audit->record(
            action: AuditAction::ORGANIZATION_DELETED,
            entityType: 'organization',
            entityId: (string) $id,
            actorUserId: $actorUserId,
            organizationId: null,
            beforeJson: $beforeJson,
            afterJson: null,
        );
    }
}
