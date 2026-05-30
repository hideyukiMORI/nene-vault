<?php

declare(strict_types=1);

namespace NeneVault\Tests\Audit;

use NeneVault\Audit\AuditAction;
use NeneVault\Audit\AuditEvent;
use NeneVault\Audit\AuditEventRepositoryInterface;
use NeneVault\Audit\AuditRecorder;
use PHPUnit\Framework\TestCase;

final class AuditRecorderTest extends TestCase
{
    public function test_records_create_with_null_before(): void
    {
        $repo = new InMemoryAuditEventRepository();
        $recorder = new AuditRecorder($repo);

        $recorder->record(
            action: AuditAction::ORGANIZATION_CREATED,
            entityType: 'organization',
            entityId: '42',
            actorUserId: 1,
            organizationId: null,
            beforeJson: null,
            afterJson: ['id' => 42, 'name' => 'ACME', 'slug' => 'acme'],
        );

        $events = $repo->all();
        $this->assertCount(1, $events);
        $this->assertSame(AuditAction::ORGANIZATION_CREATED, $events[0]->action);
        $this->assertSame('organization', $events[0]->entityType);
        $this->assertSame('42', $events[0]->entityId);
        $this->assertSame(1, $events[0]->actorUserId);
        $this->assertNull($events[0]->organizationId);
        $this->assertNull($events[0]->beforeJson);
        $this->assertSame('acme', $events[0]->afterJson['slug'] ?? null);
    }

    public function test_records_update_with_before_and_after(): void
    {
        $repo = new InMemoryAuditEventRepository();
        $recorder = new AuditRecorder($repo);

        $recorder->record(
            action: AuditAction::ORGANIZATION_UPDATED,
            entityType: 'organization',
            entityId: '5',
            actorUserId: 99,
            organizationId: null,
            beforeJson: ['name' => 'Old Name', 'slug' => 'old'],
            afterJson: ['name' => 'New Name', 'slug' => 'new'],
        );

        $events = $repo->all();
        $this->assertCount(1, $events);
        $this->assertSame('Old Name', $events[0]->beforeJson['name'] ?? null);
        $this->assertSame('New Name', $events[0]->afterJson['name'] ?? null);
    }

    public function test_records_delete_with_null_after(): void
    {
        $repo = new InMemoryAuditEventRepository();
        $recorder = new AuditRecorder($repo);

        $recorder->record(
            action: AuditAction::ORGANIZATION_DELETED,
            entityType: 'organization',
            entityId: '7',
            actorUserId: 2,
            organizationId: null,
            beforeJson: ['id' => 7, 'name' => 'Gone Corp'],
            afterJson: null,
        );

        $events = $repo->all();
        $this->assertSame(AuditAction::ORGANIZATION_DELETED, $events[0]->action);
        $this->assertNotNull($events[0]->beforeJson);
        $this->assertNull($events[0]->afterJson);
    }

    public function test_records_vault_settings_change_with_org_context(): void
    {
        $repo = new InMemoryAuditEventRepository();
        $recorder = new AuditRecorder($repo);

        $recorder->record(
            action: AuditAction::VAULT_SETTINGS_CHANGED,
            entityType: 'vault_settings',
            entityId: '3',
            actorUserId: 10,
            organizationId: 3,
            beforeJson: ['retention_years' => 10],
            afterJson: ['retention_years' => 12],
        );

        $events = $repo->all();
        $this->assertSame(3, $events[0]->organizationId);
        $this->assertSame(10, $events[0]->beforeJson['retention_years'] ?? null);
        $this->assertSame(12, $events[0]->afterJson['retention_years'] ?? null);
    }

    public function test_audit_events_are_append_only(): void
    {
        $repo = new InMemoryAuditEventRepository();
        $recorder = new AuditRecorder($repo);

        $recorder->record(AuditAction::ORGANIZATION_CREATED, 'organization', '1', 1, null, null, ['name' => 'A']);
        $recorder->record(AuditAction::ORGANIZATION_UPDATED, 'organization', '1', 1, null, ['name' => 'A'], ['name' => 'B']);

        $this->assertCount(2, $repo->all());
    }
}

final class InMemoryAuditEventRepository implements AuditEventRepositoryInterface
{
    /** @var list<AuditEvent> */
    private array $events = [];

    public function append(AuditEvent $event): void
    {
        $this->events[] = $event;
    }

    /** @return list<AuditEvent> */
    public function all(): array
    {
        return $this->events;
    }

    public function findByCriteria(array $filters, int $limit, int $offset): array
    {
        return array_slice($this->events, $offset, $limit);
    }

    public function countByCriteria(array $filters): int
    {
        return count($this->events);
    }
}
