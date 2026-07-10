<?php

declare(strict_types=1);

namespace NeneVault\Demo;

/**
 * Process-local registry mapping a freshly provisioned demo org to its
 * throwaway admin (`Nene2\Demo` consumer, #141).
 *
 * {@see \Nene2\Demo\ProvisionedDemoOrg} carries the admin's id through the
 * framework orchestration, but the seeder contract
 * ({@see \Nene2\Demo\DemoDataSeederInterface}) only receives the org id — and
 * looking the admin up again "by role literal" is exactly what the framework
 * removed from the flow. The provisioner records the pair here at creation
 * time; {@see DisposableDemoSeeder} and {@see DemoSessionSeater} read it back
 * within the same request. Never persisted; a fresh process starts empty.
 */
final class DemoProvisionRegistry
{
    /** @var array<int, array{userId: int, email: string}> keyed by org id */
    private array $admins = [];

    public function register(int $orgId, int $adminUserId, string $adminEmail): void
    {
        $this->admins[$orgId] = ['userId' => $adminUserId, 'email' => $adminEmail];
    }

    public function adminUserId(int $orgId): ?int
    {
        return $this->admins[$orgId]['userId'] ?? null;
    }

    public function adminEmail(int $orgId): ?string
    {
        return $this->admins[$orgId]['email'] ?? null;
    }
}
