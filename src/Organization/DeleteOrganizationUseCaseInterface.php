<?php

declare(strict_types=1);

namespace NeneVault\Organization;

interface DeleteOrganizationUseCaseInterface
{
    /** @throws OrganizationNotFoundException */
    public function execute(int $id, ?int $actorUserId = null): void;
}
