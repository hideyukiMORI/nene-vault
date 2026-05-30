<?php

declare(strict_types=1);

namespace NeneVault\Organization;

use Nene2\Http\JsonResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListOrganizationsHandler
{
    public function __construct(
        private ListOrganizationsUseCaseInterface $useCase,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $limit = min(100, max(1, (int) ($params['limit'] ?? 20)));
        $offset = max(0, (int) ($params['offset'] ?? 0));

        $output = $this->useCase->execute(new ListOrganizationsInput($limit, $offset));

        return $this->response->create([
            'items'  => array_map(fn (Organization $o) => $this->toArray($o), $output->items),
            'total'  => $output->total,
            'limit'  => $output->limit,
            'offset' => $output->offset,
        ]);
    }

    /** @return array<string, mixed> */
    private function toArray(Organization $o): array
    {
        return [
            'id'            => $o->id,
            'name'          => $o->name,
            'slug'          => $o->slug,
            'plan'          => $o->plan,
            'is_active'     => $o->isActive,
            'external_id'   => $o->externalId,
            'custom_domain' => $o->customDomain,
            'created_at'    => $o->createdAt,
            'updated_at'    => $o->updatedAt,
        ];
    }
}
