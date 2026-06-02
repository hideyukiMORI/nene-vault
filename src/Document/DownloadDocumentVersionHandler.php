<?php

declare(strict_types=1);

namespace NeneVault\Document;

use Nene2\Routing\Router;
use NeneVault\Auth\RequestContext;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

final readonly class DownloadDocumentVersionHandler
{
    public function __construct(
        private DownloadDocumentVersionUseCaseInterface $useCase,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $orgId = RequestContext::organizationId($request);

        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $documentId = (string) ($params['id'] ?? '');
        $versionId = (string) ($params['versionId'] ?? '');

        $result = $this->useCase->execute($documentId, $versionId, $orgId);

        $stream = $this->streamFactory->createStream($result['file_contents']);

        return $this->responseFactory->createResponse(200)
            ->withHeader('Content-Type', $result['mime_type'])
            ->withHeader('Content-Disposition', 'attachment; filename="' . $this->sanitizeFilename($result['filename']) . '"')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withBody($stream);
    }

    private function sanitizeFilename(string $filename): string
    {
        // Strip characters that could break the header; keep it simple and safe.
        return str_replace(['"', "\r", "\n"], '', $filename);
    }
}
