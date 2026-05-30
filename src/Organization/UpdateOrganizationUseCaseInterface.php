<?php

declare(strict_types=1);

namespace NeneVault\Organization;

interface UpdateOrganizationUseCaseInterface
{
    /** @throws OrganizationNotFoundException */
    public function execute(UpdateOrganizationInput $input): UpdateOrganizationOutput;
}
