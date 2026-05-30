<?php

declare(strict_types=1);

namespace NeneVault\Organization;

interface GetOrganizationByIdUseCaseInterface
{
    /** @throws OrganizationNotFoundException */
    public function execute(GetOrganizationByIdInput $input): GetOrganizationByIdOutput;
}
