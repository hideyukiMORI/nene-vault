<?php

declare(strict_types=1);

namespace NeneVault\Audit;

use Nene2\Http\JsonResponseFactory;
use NeneVault\Auth\Role;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListAuditEventsHandler
{
    public function __construct(
        private AuditEventRepositoryInterface $repository,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $limit = min(100, max(1, (int) ($params['limit'] ?? 20)));
        $offset = max(0, (int) ($params['offset'] ?? 0));

        $claims = $request->getAttribute('nene2.auth.claims');
        $role = Role::tryFrom((string) ($claims['role'] ?? ''));
        $resolvedOrgId = $request->getAttribute('nene2.org.id');

        $filters = [];

        // Superadmin sees all audit events; admin/member/viewer see their org only
        if ($role !== Role::Superadmin && is_int($resolvedOrgId)) {
            $filters['organization_id'] = $resolvedOrgId;
        }

        // Optional filters from query string
        if (isset($params['entity_type']) && is_string($params['entity_type'])) {
            $filters['entity_type'] = $params['entity_type'];
        }

        if (isset($params['entity_id']) && is_string($params['entity_id'])) {
            $filters['entity_id'] = $params['entity_id'];
        }

        if (isset($params['action']) && is_string($params['action'])) {
            $filters['action'] = $params['action'];
        }

        if (isset($params['actor_user_id']) && is_numeric($params['actor_user_id'])) {
            $filters['actor_user_id'] = (int) $params['actor_user_id'];
        }

        $items = $this->repository->findByCriteria($filters, $limit, $offset);
        $total = $this->repository->countByCriteria($filters);

        return $this->response->create([
            'items'  => array_map($this->toArray(...), $items),
            'total'  => $total,
            'limit'  => $limit,
            'offset' => $offset,
        ]);
    }

    /** @return array<string, mixed> */
    private function toArray(AuditEvent $e): array
    {
        return [
            'id'              => $e->id,
            'action'          => $e->action,
            'entity_type'     => $e->entityType,
            'entity_id'       => $e->entityId,
            'actor_user_id'   => $e->actorUserId,
            'organization_id' => $e->organizationId,
            'before_json'     => $e->beforeJson,
            'after_json'      => $e->afterJson,
            'source'          => $e->source,
            'metadata_json'   => $e->metadataJson,
            'created_at'      => $e->createdAt,
        ];
    }
}
