<?php

declare(strict_types=1);

namespace NeneVault\Audit;

final readonly class ListAuditEventsUseCase implements ListAuditEventsUseCaseInterface
{
    public function __construct(
        private AuditEventRepositoryInterface $repository,
    ) {
    }

    public function execute(ListAuditEventsInput $input): ListAuditEventsOutput
    {
        $filters = [];

        if ($input->organizationId !== null) {
            $filters['organization_id'] = $input->organizationId;
        }

        if ($input->entityType !== null) {
            $filters['entity_type'] = $input->entityType;
        }

        if ($input->entityId !== null) {
            $filters['entity_id'] = $input->entityId;
        }

        if ($input->action !== null) {
            $filters['action'] = $input->action;
        }

        if ($input->actorUserId !== null) {
            $filters['actor_user_id'] = $input->actorUserId;
        }

        return new ListAuditEventsOutput(
            items: $this->repository->findByCriteria($filters, $input->limit, $input->offset),
            total: $this->repository->countByCriteria($filters),
            limit: $input->limit,
            offset: $input->offset,
        );
    }
}
