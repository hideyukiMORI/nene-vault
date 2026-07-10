<?php

declare(strict_types=1);

namespace NeneVault\Tests\Demo;

use Nene2\Demo\SlugConflictException;
use NeneVault\Demo\DemoOrgProvisioner;
use NeneVault\Demo\DemoProvisionRegistry;
use NeneVault\Organization\CreateOrganizationUseCaseInterface;
use NeneVault\Tests\Support\ApiTestCase;
use NeneVault\User\CreateUserUseCaseInterface;

/**
 * The disposable-org provisioner (#141): a thin wrapper over the product's
 * real create-org/create-user use cases, so vault_settings seeding and the
 * audit trail stay authentic.
 */
final class DemoOrgProvisionerTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        self::bootContainer();
    }

    private function provisioner(DemoProvisionRegistry $registry): DemoOrgProvisioner
    {
        $container = self::$container;
        assert($container !== null);

        $createOrg = $container->get(CreateOrganizationUseCaseInterface::class);
        assert($createOrg instanceof CreateOrganizationUseCaseInterface);
        $createUser = $container->get(CreateUserUseCaseInterface::class);
        assert($createUser instanceof CreateUserUseCaseInterface);

        return new DemoOrgProvisioner($createOrg, $createUser, $registry);
    }

    public function test_provision_creates_org_settings_and_a_namespaced_admin(): void
    {
        $registry = new DemoProvisionRegistry();
        $slug = 'demo-prov' . substr(uniqid(), -6);

        $org = $this->provisioner($registry)->provision($slug, 'standard');

        $this->assertSame($slug, $org->slug);
        $this->assertGreaterThan(0, $org->orgId);
        $this->assertGreaterThan(0, $org->adminUserId);

        $row = $this->fetchRow("SELECT name, is_active FROM organizations WHERE id = {$org->orgId}");
        $this->assertSame(1, (int) $row['is_active']);

        // vault_settings seeded by the real use case.
        $settings = $this->fetchRow("SELECT COUNT(*) AS n FROM vault_settings WHERE organization_id = {$org->orgId}");
        $this->assertSame(1, (int) $settings['n']);

        // Admin user, slug-namespaced email, registry populated.
        $admin = $this->fetchRow("SELECT email, role FROM users WHERE id = {$org->adminUserId}");
        $this->assertSame('admin', $admin['role']);
        $this->assertSame(DemoOrgProvisioner::adminEmail($slug), $admin['email']);
        $this->assertSame($org->adminUserId, $registry->adminUserId($org->orgId));
        $this->assertSame($admin['email'], $registry->adminEmail($org->orgId));
    }

    public function test_a_taken_slug_raises_the_retryable_conflict(): void
    {
        $registry = new DemoProvisionRegistry();
        $slug = 'demo-dup' . substr(uniqid(), -6);
        $this->provisioner($registry)->provision($slug, 'standard');

        $this->expectException(SlugConflictException::class);
        $this->provisioner($registry)->provision($slug, 'standard');
    }

    /** @return array<string, mixed> */
    private function fetchRow(string $sql): array
    {
        $stmt = self::pdo()->query($sql);
        $this->assertNotFalse($stmt);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertIsArray($row);

        return $row;
    }
}
