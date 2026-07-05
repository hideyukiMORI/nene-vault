<?php

declare(strict_types=1);

namespace NeneVault\Organization;

use Closure;
use Nene2\Audit\AuditEvent;
use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use NeneVault\Audit\AuditAction;

final readonly class UpdateOrganizationUseCase implements UpdateOrganizationUseCaseInterface
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

    public function execute(UpdateOrganizationInput $input): UpdateOrganizationOutput
    {
        return $this->transactionManager->transactional(
            function (DatabaseQueryExecutorInterface $executor) use ($input): UpdateOrganizationOutput {
                $organizations = ($this->organizationRepository)($executor);
                $audit = $this->auditRecorderFactory->forExecutor($executor);

                $org = $organizations->findById($input->id);

                if ($org === null) {
                    throw new OrganizationNotFoundException($input->id);
                }

                $beforeJson = $this->toAuditArray($org);

                $organizations->update(new Organization(
                    name: $input->name,
                    slug: $input->slug,
                    plan: $input->plan,
                    isActive: $input->isActive,
                    id: $input->id,
                    externalId: $input->externalId,
                    customDomain: $input->customDomain,
                ));

                $refreshed = $organizations->findById($input->id);
                assert($refreshed !== null);

                $audit->record(new AuditEvent(
                    action: AuditAction::ORGANIZATION_UPDATED,
                    entityType: 'organization',
                    entityId: (string) $input->id,
                    actorId: $input->actorUserId,
                    organizationId: null,
                    before: $beforeJson,
                    after: $this->toAuditArray($refreshed),
                ));

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
