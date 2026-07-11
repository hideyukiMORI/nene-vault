<?php

declare(strict_types=1);

namespace NeneVault\Organization;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Http\ClockInterface;
use PDOException;

final readonly class PdoOrganizationRepository implements OrganizationRepositoryInterface
{
    private const COLUMNS = 'id, name, slug, external_id, custom_domain, plan, is_active, created_at, updated_at';

    public function __construct(
        private DatabaseQueryExecutorInterface $query,
        private ClockInterface $clock,
    ) {
    }

    public function findById(int $id): ?Organization
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM organizations WHERE id = ?',
            [$id],
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    public function findBySlug(string $slug): ?Organization
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM organizations WHERE slug = ?',
            [$slug],
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    public function findByCustomDomain(string $domain): ?Organization
    {
        $row = $this->query->fetchOne(
            'SELECT ' . self::COLUMNS . ' FROM organizations WHERE custom_domain = ?',
            [$domain],
        );

        return $row !== null ? $this->mapRow($row) : null;
    }

    /** @return list<Organization> */
    public function findAll(int $limit, int $offset): array
    {
        $rows = $this->query->fetchAll(
            'SELECT ' . self::COLUMNS . ' FROM organizations ORDER BY id ASC LIMIT ? OFFSET ?',
            [$limit, $offset],
        );

        return array_map(fn (array $row) => $this->mapRow($row), $rows);
    }

    public function count(): int
    {
        $row = $this->query->fetchOne('SELECT COUNT(*) AS cnt FROM organizations', []);

        return $row !== null ? (int) $row['cnt'] : 0;
    }

    public function save(Organization $organization): int
    {
        // UTC via the injected clock (#161): the sweep parses created_at as
        // UTC, and all fleet products write UTC (clear #280 / deal #72 shape).
        $now = $this->clock->now()->format('Y-m-d H:i:s');

        try {
            $this->query->execute(
                'INSERT INTO organizations (name, slug, external_id, custom_domain, plan, is_active, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $organization->name,
                    $organization->slug,
                    $organization->externalId,
                    $organization->customDomain,
                    $organization->plan,
                    $organization->isActive ? 1 : 0,
                    $now,
                    $now,
                ],
            );
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry') || str_contains($e->getMessage(), 'UNIQUE constraint failed')) {
                throw new OrganizationSlugConflictException($organization->slug);
            }

            throw $e;
        }

        return $this->query->lastInsertId();
    }

    public function update(Organization $organization): void
    {
        if ($organization->id === null) {
            throw new OrganizationNotFoundException(0);
        }

        $this->query->execute(
            'UPDATE organizations
             SET name = ?, slug = ?, external_id = ?, custom_domain = ?, plan = ?, is_active = ?, updated_at = ?
             WHERE id = ?',
            [
                $organization->name,
                $organization->slug,
                $organization->externalId,
                $organization->customDomain,
                $organization->plan,
                $organization->isActive ? 1 : 0,
                $this->clock->now()->format('Y-m-d H:i:s'),
                $organization->id,
            ],
        );
    }

    public function delete(int $id): void
    {
        $org = $this->findById($id);

        if ($org === null) {
            throw new OrganizationNotFoundException($id);
        }

        $this->query->execute('DELETE FROM organizations WHERE id = ?', [$id]);
    }

    /** @param array<string, mixed> $row */
    private function mapRow(array $row): Organization
    {
        return new Organization(
            name: (string) $row['name'],
            slug: (string) $row['slug'],
            plan: (string) $row['plan'],
            isActive: (bool) $row['is_active'],
            id: (int) $row['id'],
            externalId: isset($row['external_id']) && $row['external_id'] !== '' ? (string) $row['external_id'] : null,
            customDomain: isset($row['custom_domain']) && $row['custom_domain'] !== '' ? (string) $row['custom_domain'] : null,
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }
}
