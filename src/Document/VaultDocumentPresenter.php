<?php

declare(strict_types=1);

namespace NeneVault\Document;

use NeneVault\DocumentVersion\DocumentVersion;

/**
 * Builds the public VaultDocumentResponse shape (docs/terms.md §17, OpenAPI).
 *
 * The storage path is never included. Combines the logical document with its
 * current version's file metadata.
 */
final class VaultDocumentPresenter
{
    /** @return array<string, mixed> */
    public static function present(VaultDocument $doc, DocumentVersion $currentVersion): array
    {
        return [
            'id'                    => $doc->id,
            'organization_id'       => $doc->organizationId,
            'status'                => $doc->status,
            'transaction_date'      => $doc->transactionDate,
            'amount_cents'          => $doc->amountCents,
            'counterparty_name'     => $doc->counterpartyName,
            'category'              => $doc->category,
            'tags'                  => $doc->tags,
            'file_sha256'           => $currentVersion->fileSha256,
            'mime_type'             => $currentVersion->mimeType,
            'original_filename'     => $currentVersion->originalFilename,
            'file_size_bytes'       => $currentVersion->fileSizeBytes,
            'version_number'        => $currentVersion->versionNumber,
            'source'                => $currentVersion->source,
            'uploaded_at'           => $doc->uploadedAt,
            'uploaded_by'           => $doc->uploadedBy,
            'voided_at'             => $doc->voidedAt,
            'voided_by'             => $doc->voidedBy,
            'void_reason'           => $doc->voidReason,
            'date_uncertain'        => $doc->dateUncertain,
            'is_metadata_confirmed' => $doc->isMetadataConfirmed,
            'retention_years'       => $doc->retentionYears,
            'retention_expires_at'  => $doc->retentionExpiresAt,
        ];
    }
}
