<?php

declare(strict_types=1);

namespace NeneVault\Organization;

use NeneVault\Audit\AuditAction;
use NeneVault\Audit\AuditRecorderInterface;

final readonly class UpdateOrganizationUseCase implements UpdateOrganizationUseCaseInterface
{
    public function __construct(
        private OrganizationRepositoryInterface $organizations,
        private AuditRecorderInterface $audit,
    ) {
    }

    public function execute(UpdateOrganizationInput $input): UpdateOrganizationOutput
    {
        $org = $this->organizations->findById($input->id);

        if ($org === null) {
            throw new OrganizationNotFoundException($input->id);
        }

        $beforeJson = $this->toAuditArray($org);

        $this->organizations->update(new Organization(
            name: $input->name,
            slug: $input->slug,
            plan: $input->plan,
            isActive: $input->isActive,
            id: $input->id,
            externalId: $input->externalId,
            customDomain: $input->customDomain,
        ));

        $refreshed = $this->organizations->findById($input->id);
        assert($refreshed !== null);

        $this->audit->record(
            action: AuditAction::ORGANIZATION_UPDATED,
            entityType: 'organization',
            entityId: (string) $input->id,
            actorUserId: $input->actorUserId,
            organizationId: null,
            beforeJson: $beforeJson,
            afterJson: $this->toAuditArray($refreshed),
        );

        return new UpdateOrganizationOutput(
            id: $refreshed->id ?? $input->id,
            name: $refreshed->name,
            slug: $refreshed->slug,
            plan: $refreshed->plan,
            isActive: $refreshed->isActive,
            externalId: $refreshed->externalId,
            customDomain: $refreshed->customDomain,
            updatedAt: $refreshed->updatedAt ?? '',
        );
    }

    /** @return array<string, mixed> */
    private function toAuditArray(Organization $org): array
    {
        return [
            'id'            => $org->id,
            'name'          => $org->name,
            'slug'          => $org->slug,
            'plan'          => $org->plan,
            'is_active'     => $org->isActive,
            'external_id'   => $org->externalId,
            'custom_domain' => $org->customDomain,
        ];
    }
}
