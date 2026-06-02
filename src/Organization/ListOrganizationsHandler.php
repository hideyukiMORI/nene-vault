<?php

declare(strict_types=1);

namespace NeneVault\Organization;

use Nene2\Http\JsonResponseFactory;
use Nene2\Http\PaginationQueryParser;
use Nene2\Http\PaginationResponse;
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
        $pagination = PaginationQueryParser::parse($request);

        $output = $this->useCase->execute(new ListOrganizationsInput($pagination->limit, $pagination->offset));

        return $this->response->create(
            (new PaginationResponse(
                items: array_map(fn (Organization $o) => $this->toArray($o), $output->items),
                limit: $output->limit,
                offset: $output->offset,
                total: $output->total,
            ))->toArray(),
        );
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
