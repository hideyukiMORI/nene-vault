<?php

declare(strict_types=1);

namespace NeneVault\Document;

use Nene2\Error\DomainExceptionHandlerInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final readonly class FileIntegrityExceptionHandler implements DomainExceptionHandlerInterface
{
    public function __construct(
        private ProblemDetailsResponseFactory $problemDetails,
    ) {
    }

    public function supports(Throwable $e): bool
    {
        return $e instanceof FileIntegrityException;
    }

    public function handle(Throwable $e, ServerRequestInterface $request): ResponseInterface
    {
        // Do not leak which file or hash; integrity failure is a server-side P0.
        return $this->problemDetails->create(
            $request,
            'internal-server-error',
            'Internal Server Error',
            500,
            'The requested file could not be served due to an integrity check failure.',
        );
    }
}
