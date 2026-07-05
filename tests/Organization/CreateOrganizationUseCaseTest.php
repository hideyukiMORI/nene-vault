<?php

declare(strict_types=1);

namespace NeneVault\Tests\Organization;

use Nene2\Database\DatabaseQueryExecutorInterface;
use NeneVault\Organization\CreateOrganizationInput;
use NeneVault\Organization\CreateOrganizationUseCase;
use NeneVault\Organization\Organization;
use NeneVault\Organization\OrganizationRepositoryInterface;
use NeneVault\Organization\OrganizationSlugConflictException;
use NeneVault\Tests\Audit\InMemoryAuditRecorderFactory;
use NeneVault\Tests\Support\SynchronousTransactionManager;
use NeneVault\VaultSettings\VaultSettingsSeederInterface;
use PHPUnit\Framework\TestCase;

final class CreateOrganizationUseCaseTest extends TestCase
{
    public function test_creates_organization_and_seeds_vault_settings(): void
    {
        $repo = new InMemoryOrganizationRepository();
        $seeder = new InMemoryVaultSettingsSeeder();
        $auditFactory = new InMemoryAuditRecorderFactory();
        $useCase = new CreateOrganizationUseCase(
            new SynchronousTransactionManager(),
            static fn (DatabaseQueryExecutorInterface $e): OrganizationRepositoryInterface => $repo,
            static fn (DatabaseQueryExecutorInterface $e): VaultSettingsSeederInterface => $seeder,
            $auditFactory,
        );

        $output = $useCase->execute(new CreateOrganizationInput(
            name: 'ACME Corp',
            slug: 'acme',
        ));

        $this->assertSame('ACME Corp', $output->name);
        $this->assertSame('acme', $output->slug);
        $this->assertGreaterThan(0, $output->id);
        $this->assertTrue($seeder->seededOrgIds[$output->id] ?? false);
    }

    public function test_audit_event_recorded_on_create(): void
    {
        $repo = new InMemoryOrganizationRepository();
        $seeder = new InMemoryVaultSettingsSeeder();
        $auditFactory = new InMemoryAuditRecorderFactory();
        $useCase = new CreateOrganizationUseCase(
            new SynchronousTransactionManager(),
            static fn (DatabaseQueryExecutorInterface $e): OrganizationRepositoryInterface => $repo,
            static fn (DatabaseQueryExecutorInterface $e): VaultSettingsSeederInterface => $seeder,
            $auditFactory,
        );

        $useCase->execute(new CreateOrganizationInput(name: 'Test', slug: 'test', actorUserId: 7));

        $events = $auditFactory->all();
        $this->assertCount(1, $events);
        $this->assertSame('organization.created', $events[0]->action);
        $this->assertSame(7, $events[0]->actorId);
        $this->assertNull($events[0]->before);
        $this->assertNotNull($events[0]->after);
    }

    public function test_throws_when_slug_already_exists(): void
    {
        $repo = new InMemoryOrganizationRepository();
        $seeder = new InMemoryVaultSettingsSeeder();
        $useCase = new CreateOrganizationUseCase(
            new SynchronousTransactionManager(),
            static fn (DatabaseQueryExecutorInterface $e): OrganizationRepositoryInterface => $repo,
            static fn (DatabaseQueryExecutorInterface $e): VaultSettingsSeederInterface => $seeder,
            new InMemoryAuditRecorderFactory(),
        );

        $useCase->execute(new CreateOrganizationInput(name: 'First', slug: 'acme'));

        $this->expectException(OrganizationSlugConflictException::class);

        $useCase->execute(new CreateOrganizationInput(name: 'Second', slug: 'acme'));
    }
}

final class InMemoryOrganizationRepository implements OrganizationRepositoryInterface
{
    /** @var array<int, Organization> */
    private array $orgs = [];
    private int $nextId = 1;

    public function findById(int $id): ?Organization
    {
        return $this->orgs[$id] ?? null;
    }

    public function findBySlug(string $slug): ?Organization
    {
        foreach ($this->orgs as $org) {
            if ($org->slug === $slug) {
                return $org;
            }
        }

        return null;
    }

    public function findByCustomDomain(string $domain): ?Organization
    {
        return null;
    }

    public function findAll(int $limit, int $offset): array
    {
        return array_slice(array_values($this->orgs), $offset, $limit);
    }

    public function count(): int
    {
        return count($this->orgs);
    }

    public function save(Organization $organization): int
    {
        $existing = $this->findBySlug($organization->slug);

        if ($existing !== null) {
            throw new OrganizationSlugConflictException($organization->slug);
        }

        $id = $this->nextId++;
        $now = date('Y-m-d H:i:s');
        $this->orgs[$id] = new Organization(
            name: $organization->name,
            slug: $organization->slug,
            plan: $organization->plan,
            isActive: $organization->isActive,
            id: $id,
            externalId: $organization->externalId,
            customDomain: $organization->customDomain,
            createdAt: $now,
            updatedAt: $now,
        );

        return $id;
    }

    public function update(Organization $organization): void
    {
        if ($organization->id === null || !isset($this->orgs[$organization->id])) {
            throw new \NeneVault\Organization\OrganizationNotFoundException($organization->id ?? 0);
        }

        $this->orgs[$organization->id] = $organization;
    }

    public function delete(int $id): void
    {
        if (!isset($this->orgs[$id])) {
            throw new \NeneVault\Organization\OrganizationNotFoundException($id);
        }

        unset($this->orgs[$id]);
    }
}

final class InMemoryVaultSettingsSeeder implements VaultSettingsSeederInterface
{
    /** @var array<int, bool> */
    public array $seededOrgIds = [];

    public function seed(int $organizationId): void
    {
        $this->seededOrgIds[$organizationId] = true;
    }
}
