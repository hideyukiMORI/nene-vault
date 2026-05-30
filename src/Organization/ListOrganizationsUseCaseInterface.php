<?php

declare(strict_types=1);

namespace NeneVault\Organization;

interface ListOrganizationsUseCaseInterface
{
    public function execute(ListOrganizationsInput $input): ListOrganizationsOutput;
}
