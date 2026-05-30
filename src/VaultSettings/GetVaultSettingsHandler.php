<?php

declare(strict_types=1);

namespace NeneVault\VaultSettings;

use Nene2\Http\JsonResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class GetVaultSettingsHandler
{
    public function __construct(
        private VaultSettingsRepositoryInterface $settings,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $orgId = $request->getAttribute('nene2.org.id');
        assert(is_int($orgId));

        $settings = $this->settings->findByOrganizationId($orgId);

        if ($settings === null) {
            $settings = new VaultSettings(organizationId: $orgId);
        }

        return $this->response->create($this->toArray($settings));
    }

    /** @return array<string, mixed> */
    private function toArray(VaultSettings $s): array
    {
        return [
            'organization_id'       => $s->organizationId,
            'retention_years'       => $s->retentionYears,
            'storage_path_override' => $s->storagePathOverride,
            'invoice_api_base_url'  => $s->invoiceApiBaseUrl,
            'clear_api_base_url'    => $s->clearApiBaseUrl,
            'updated_at'            => $s->updatedAt,
        ];
    }
}
