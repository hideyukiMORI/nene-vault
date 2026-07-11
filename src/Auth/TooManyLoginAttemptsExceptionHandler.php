<?php

declare(strict_types=1);

namespace NeneVault\Auth;

use Nene2\Error\DomainExceptionHandlerInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final readonly class TooManyLoginAttemptsExceptionHandler implements DomainExceptionHandlerInterface
{
    public function __construct(
        private ProblemDetailsResponseFactory $problemDetails,
    ) {
    }

    public function supports(Throwable $e): bool
    {
        return $e instanceof TooManyLoginAttemptsException;
    }

    public function handle(Throwable $e, ServerRequestInterface $request): ResponseInterface
    {
        assert($e instanceof TooManyLoginAttemptsException);

        return $this->problemDetails->create(
            $request,
            'too-many-login-attempts',
            'Too Many Login Attempts',
            429,
            $e->getMessage(),
            ['retry_after_seconds' => $e->retryAfterSeconds],
        );
    }
}
