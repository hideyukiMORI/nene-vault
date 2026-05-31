<?php

declare(strict_types=1);

namespace NeneVault\Email;

final readonly class InboundEmail
{
    /**
     * @param list<EmailAttachment> $attachments
     */
    public function __construct(
        public string $messageId,
        public string $from,
        public string $subject,
        public ?string $date,
        public array $attachments,
    ) {
    }

    /** @return list<EmailAttachment> */
    public function allowedAttachments(): array
    {
        return array_values(array_filter(
            $this->attachments,
            static fn (EmailAttachment $a) => $a->isAllowed(),
        ));
    }
}
