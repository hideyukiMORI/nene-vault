<?php

declare(strict_types=1);

namespace NeneVault\Organization;

use Closure;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use NeneVault\Audit\AuditAction;
use NeneVault\Audit\AuditRecorderInterface;
use NeneVault\VaultSettings\VaultSettingsSeederInterface;

final readonly class CreateOrganizationUseCase implements CreateOrganizationUseCaseInterface
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): OrganizationRepositoryInterface $organizationRepository
     * @param Closure(DatabaseQueryExecutorInterface): VaultSettingsSeederInterface    $settingsSeeder
     * @param Closure(DatabaseQueryExecutorInterface): AuditRecorderInterface          $auditRecorder
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $transactionManager,
        private Closure $organizationRepository,
        private Closure $settingsSeeder,
        private Closure $auditRecorder,
    ) {
    }

    public function execute(CreateOrganizationInput $input): CreateOrganizationOutput
    {
        return $this->transactionManager->transactional(
            function (DatabaseQueryExecutorInterface $executor) use ($input): CreateOrganizationOutput {
                $organizations = ($this->organizationRepository)($executor);
                $settingsSeeder = ($this->settingsSeeder)($executor);
                $audit = ($this->auditRecorder)($executor);

                $existing = $organizations->findBySlug($input->slug);

                if ($existing !== null) {
                    throw new OrganizationSlugConflictException($input->slug);
                }

                $id = $organizations->save(new Organization(
                    name: $input->name,
                    slug: $input->slug,
                    plan: $input->plan,
                    isActive: $input->isActive,
                    externalId: $input->externalId,
                    customDomain: $input->customDomain,
                ));

                $settingsSeeder->seed($id);

                $org = $organizations->findById($id);
                assert($org !== null);

                $audit->record(
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
            },
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
