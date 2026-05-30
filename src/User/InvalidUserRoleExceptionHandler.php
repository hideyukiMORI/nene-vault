<?php

declare(strict_types=1);

namespace NeneVault\User;

use Nene2\Error\DomainExceptionHandlerInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final readonly class InvalidUserRoleExceptionHandler implements DomainExceptionHandlerInterface
{
    public function __construct(
        private ProblemDetailsResponseFactory $problemDetails,
    ) {
    }

    public function supports(Throwable $e): bool
    {
        return $e instanceof InvalidUserRoleException;
    }

    public function handle(Throwable $e, ServerRequestInterface $request): ResponseInterface
    {
        return $this->problemDetails->create($request, 'invalid-user-role', 'Invalid User Role', 422, $e->getMessage());
    }
}
