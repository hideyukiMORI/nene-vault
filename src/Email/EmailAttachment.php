<?php

declare(strict_types=1);

namespace NeneVault\Email;

final readonly class EmailAttachment
{
    /** @var list<string> */
    private const ALLOWED_MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

    public function __construct(
        public string $filename,
        public string $mimeType,
        public string $bytes,
    ) {
    }

    public function isAllowed(): bool
    {
        return in_array($this->mimeType, self::ALLOWED_MIME_TYPES, true);
    }
}
