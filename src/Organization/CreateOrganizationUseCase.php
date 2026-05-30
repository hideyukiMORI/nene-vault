<?php

declare(strict_types=1);

namespace NeneVault\Organization;

use NeneVault\Audit\AuditAction;
use NeneVault\Audit\AuditRecorderInterface;
use NeneVault\VaultSettings\VaultSettingsSeederInterface;

final readonly class CreateOrganizationUseCase implements CreateOrganizationUseCaseInterface
{
    public function __construct(
        private OrganizationRepositoryInterface $organizations,
        private VaultSettingsSeederInterface $settingsSeeder,
        private AuditRecorderInterface $audit,
    ) {
    }

    public function execute(CreateOrganizationInput $input): CreateOrganizationOutput
    {
        $existing = $this->organizations->findBySlug($input->slug);

        if ($existing !== null) {
            throw new OrganizationSlugConflictException($input->slug);
        }

        $id = $this->organizations->save(new Organization(
            name: $input->name,
            slug: $input->slug,
            plan: $input->plan,
            isActive: $input->isActive,
            externalId: $input->externalId,
            customDomain: $input->customDomain,
        ));

        $this->settingsSeeder->seed($id);

        $org = $this->organizations->findById($id);
        assert($org !== null);

        $this->audit->record(
            action: AuditAction::ORGANIZATION_CREATED,
            entityType: 'organization',
            entityId: (string) $id,
            actorUserId: $input->actorUserId,
            organizationId: null,
            beforeJson: null,
            afterJson: $this->toAuditArray($org),
        );

        return new CreateOrganizationOutput(
            id: $org->id ?? $id,
            name: $org->name,
            slug: $org->slug,
            plan: $org->plan,
            isActive: $org->isActive,
            externalId: $org->externalId,
            customDomain: $org->customDomain,
            createdAt: $org->createdAt ?? '',
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
