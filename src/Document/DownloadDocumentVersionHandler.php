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
            ->withHeader('Content-Disposition', $this->contentDisposition($result['filename']))
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withBody($stream);
    }

    /**
     * Build a Content-Disposition value carrying the original filename safely for
     * both legacy and modern clients (RFC 6266): a quoted ASCII `filename=` for
     * old agents plus an RFC 5987 `filename*=UTF-8''…` that preserves non-ASCII
     * names (e.g. Japanese) instead of emitting raw bytes that garble (QA VLT-B7-03).
     */
    private function contentDisposition(string $filename): string
    {
        // Header-injection guard: never let quotes/CR/LF into the header.
        $clean = str_replace(['"', "\r", "\n"], '', $filename);

        // ASCII fallback for clients that ignore filename*: non-ASCII → '_'.
        $ascii = preg_replace('/[^\x20-\x7E]/', '_', $clean);
        if ($ascii === null || trim($ascii, '_ ') === '') {
            $ascii = 'download';
        }

        return sprintf(
            "attachment; filename=\"%s\"; filename*=UTF-8''%s",
            $ascii,
            $this->rfc5987Encode($clean),
        );
    }

    /**
     * Percent-encode a UTF-8 string per RFC 5987 (attr-char stays literal, every
     * other byte becomes %HH).
     */
    private function rfc5987Encode(string $value): string
    {
        $out = '';
        $length = strlen($value);
        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            $ord = ord($char);
            $isAlnum = ($ord >= 0x30 && $ord <= 0x39)
                || ($ord >= 0x41 && $ord <= 0x5A)
                || ($ord >= 0x61 && $ord <= 0x7A);
            if ($isAlnum || str_contains('!#$&+-.^_`|~', $char)) {
                $out .= $char;
            } else {
                $out .= '%' . strtoupper(str_pad(dechex($ord), 2, '0', STR_PAD_LEFT));
            }
        }

        return $out;
    }
}
