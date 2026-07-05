<?php

declare(strict_types=1);

namespace NeneVault\Audit;

use Nene2\Audit\AuditEventRepositoryInterface;
use Nene2\Audit\AuditQuery;

final readonly class ListAuditEventsUseCase implements ListAuditEventsUseCaseInterface
{
    public function __construct(
        private AuditEventRepositoryInterface $repository,
    ) {
    }

    public function execute(ListAuditEventsInput $input): ListAuditEventsOutput
    {
        $query = new AuditQuery(
            organizationId: $input->organizationId,
            entityType: $input->entityType,
            entityId: $input->entityId,
            action: $input->action,
            actorId: $input->actorUserId,
        );

        return new ListAuditEventsOutput(
            items: $this->repository->query($query, $input->limit, $input->offset),
            total: $this->repository->count($query),
            limit: $input->limit,
            offset: $input->offset,
        );
    }
}
