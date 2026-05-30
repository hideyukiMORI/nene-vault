<?php

declare(strict_types=1);

namespace NeneVault\User;

use Nene2\Error\DomainExceptionHandlerInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final readonly class UserNotFoundExceptionHandler implements DomainExceptionHandlerInterface
{
    public function __construct(
        private ProblemDetailsResponseFactory $problemDetails,
    ) {
    }

    public function supports(Throwable $e): bool
    {
        return $e instanceof UserNotFoundException;
    }

    public function handle(Throwable $e, ServerRequestInterface $request): ResponseInterface
    {
        return $this->problemDetails->create($request, 'user-not-found', 'User Not Found', 404, $e->getMessage());
    }
}
