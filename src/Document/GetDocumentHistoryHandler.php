<?php

declare(strict_types=1);

namespace NeneVault\Document;

use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use NeneVault\Audit\AuditEvent;
use NeneVault\Auth\RequestContext;
use NeneVault\DocumentVersion\DocumentVersion;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class GetDocumentHistoryHandler
{
    public function __construct(
        private GetDocumentHistoryUseCaseInterface $useCase,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $orgId = RequestContext::organizationId($request);

        $params = $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $documentId = (string) ($params['id'] ?? '');

        $history = $this->useCase->execute($documentId, $orgId);

        return $this->response->create([
            'versions'     => array_map($this->versionToArray(...), $history['versions']),
            'audit_events' => array_map($this->auditToArray(...), $history['audit_events']),
        ]);
    }

    /** @return array<string, mixed> */
    private function versionToArray(DocumentVersion $v): array
    {
        return [
            'id'                => $v->id,
            'version_number'    => $v->versionNumber,
            'file_sha256'       => $v->fileSha256,
            'mime_type'         => $v->mimeType,
            'original_filename' => $v->originalFilename,
            'file_size_bytes'   => $v->fileSizeBytes,
            'source'            => $v->source,
            'uploaded_at'       => $v->uploadedAt,
            'uploaded_by'       => $v->uploadedBy,
        ];
    }

    /** @return array<string, mixed> */
    private function auditToArray(AuditEvent $e): array
    {
        return [
            'id'            => $e->id,
            'action'        => $e->action,
            'entity_type'   => $e->entityType,
            'entity_id'     => $e->entityId,
            'actor_user_id' => $e->actorUserId,
            'before_json'   => $e->beforeJson,
            'after_json'    => $e->afterJson,
            'metadata_json' => $e->metadataJson,
            'created_at'    => $e->createdAt,
        ];
    }
}
