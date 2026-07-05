<?php

declare(strict_types=1);

namespace NeneVault\Audit;

use Nene2\Http\JsonResponseFactory;
use Nene2\Http\PaginationQueryParser;
use Nene2\Http\PaginationResponse;
use NeneVault\Auth\RequestContext;
use NeneVault\Auth\Role;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListAuditEventsHandler
{
    public function __construct(
        private ListAuditEventsUseCaseInterface $useCase,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $pagination = PaginationQueryParser::parse($request);

        $role = RequestContext::role($request);
        $resolvedOrgId = RequestContext::organizationIdOrNull($request);

        // Superadmin sees all audit events; admin/member/viewer see their org only
        $organizationId = $role !== Role::Superadmin && is_int($resolvedOrgId) ? $resolvedOrgId : null;

        $output = $this->useCase->execute(new ListAuditEventsInput(
            organizationId: $organizationId,
            entityType: isset($params['entity_type']) && is_string($params['entity_type']) ? $params['entity_type'] : null,
            entityId: isset($params['entity_id']) && is_string($params['entity_id']) ? $params['entity_id'] : null,
            action: isset($params['action']) && is_string($params['action']) ? $params['action'] : null,
            actorUserId: isset($params['actor_user_id']) && is_numeric($params['actor_user_id']) ? (int) $params['actor_user_id'] : null,
            limit: $pagination->limit,
            offset: $pagination->offset,
        ));

        return $this->response->create(
            (new PaginationResponse(
                items: array_map(AuditEventPresenter::toArray(...), $output->items),
                limit: $output->limit,
                offset: $output->offset,
                total: $output->total,
            ))->toArray(),
        );
    }
}
