<?php

declare(strict_types=1);

namespace NeneVault\Document;

use RuntimeException;

/**
 * Thrown when a lifecycle transition is attempted from an incompatible state —
 * e.g. voiding an already-voided document, or restoring an active one.
 *
 * Re-voiding would overwrite the original voided_at / void_reason and corrupt
 * the audit trail, so the operation is rejected (409 Conflict).
 */
final class InvalidDocumentStateException extends RuntimeException
{
    public function __construct(
        public readonly string $documentId,
        public readonly string $currentStatus,
        public readonly string $attemptedTransition,
    ) {
        parent::__construct(sprintf(
            'Document %s cannot be %s from status "%s".',
            $documentId,
            $attemptedTransition,
            $currentStatus,
        ));
    }
}
