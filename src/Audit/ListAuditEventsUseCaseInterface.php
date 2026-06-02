<?php

declare(strict_types=1);

namespace NeneVault\Audit;

interface ListAuditEventsUseCaseInterface
{
    public function execute(ListAuditEventsInput $input): ListAuditEventsOutput;
}
