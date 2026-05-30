<?php

declare(strict_types=1);

namespace NeneVault\Organization;

use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class GetOrganizationByIdHandler
{
    public function __construct(
        private GetOrganizationByIdUseCaseInterface $useCase,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id = (int) ($params['id'] ?? 0);

        $output = $this->useCase->execute(new GetOrganizationByIdInput($id));

        return $this->response->create([
            'id'            => $output->id,
            'name'          => $output->name,
            'slug'          => $output->slug,
            'plan'          => $output->plan,
            'is_active'     => $output->isActive,
            'external_id'   => $output->externalId,
            'custom_domain' => $output->customDomain,
            'created_at'    => $output->createdAt,
            'updated_at'    => $output->updatedAt,
        ]);
    }
}
