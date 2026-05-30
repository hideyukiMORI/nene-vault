<?php

declare(strict_types=1);

namespace NeneVault\Organization;

interface OrganizationRepositoryInterface
{
    public function findById(int $id): ?Organization;

    public function findBySlug(string $slug): ?Organization;

    public function findByCustomDomain(string $domain): ?Organization;

    /** @return list<Organization> */
    public function findAll(int $limit, int $offset): array;

    public function count(): int;

    /** @throws OrganizationSlugConflictException */
    public function save(Organization $organization): int;

    /** @throws OrganizationNotFoundException */
    public function update(Organization $organization): void;

    /** @throws OrganizationNotFoundException */
    public function delete(int $id): void;
}
