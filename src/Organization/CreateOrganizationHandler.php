<?php

declare(strict_types=1);

namespace NeneVault\Organization;

use Nene2\Http\JsonRequestBodyParser;
use Nene2\Http\JsonResponseFactory;
use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;
use NeneVault\Auth\RequestContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class CreateOrganizationHandler
{
    public function __construct(
        private CreateOrganizationUseCaseInterface $useCase,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = JsonRequestBodyParser::parse($request);
        $errors = [];

        $name = isset($body['name']) && is_string($body['name']) ? trim($body['name']) : '';
        $slug = isset($body['slug']) && is_string($body['slug']) ? trim($body['slug']) : '';
        $plan = isset($body['plan']) && is_string($body['plan']) ? $body['plan'] : 'free';
        $isActive = isset($body['is_active']) ? (bool) $body['is_active'] : true;
        $externalId = isset($body['external_id']) && is_string($body['external_id']) && $body['external_id'] !== '' ? $body['external_id'] : null;
        $customDomain = isset($body['custom_domain']) && is_string($body['custom_domain']) && $body['custom_domain'] !== '' ? $body['custom_domain'] : null;

        if ($name === '') {
            $errors[] = new ValidationError('name', 'Name is required.', 'required');
        }

        if ($slug === '') {
            $errors[] = new ValidationError('slug', 'Slug is required.', 'required');
        } elseif (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            $errors[] = new ValidationError('slug', 'Slug must contain only lowercase letters, numbers, and hyphens.', 'invalid_format');
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $actorUserId = RequestContext::actorUserId($request);

        $output = $this->useCase->execute(new CreateOrganizationInput(
            name: $name,
            slug: $slug,
            plan: $plan,
            isActive: $isActive,
            externalId: $externalId,
            customDomain: $customDomain,
            actorUserId: $actorUserId,
        ));

        return $this->response->create([
            'id'            => $output->id,
            'name'          => $output->name,
            'slug'          => $output->slug,
            'plan'          => $output->plan,
            'is_active'     => $output->isActive,
            'external_id'   => $output->externalId,
            'custom_domain' => $output->customDomain,
            'created_at'    => $output->createdAt,
        ], 201);
    }
}
