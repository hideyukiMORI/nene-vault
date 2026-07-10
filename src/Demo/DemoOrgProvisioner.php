<?php

declare(strict_types=1);

namespace NeneVault\Demo;

use Nene2\Demo\DisposableOrgProvisionerInterface;
use Nene2\Demo\ProvisionedDemoOrg;
use Nene2\Demo\SlugConflictException;
use NeneVault\Organization\CreateOrganizationInput;
use NeneVault\Organization\CreateOrganizationUseCaseInterface;
use NeneVault\Organization\OrganizationSlugConflictException;
use NeneVault\User\CreateUserUseCaseInterface;

/**
 * Creates one disposable demo organization (`Nene2\Demo` consumer, #141):
 * a thin wrapper over the product's own use cases, so vault_settings seeding
 * and the audit trail stay authentic — the same path a superadmin would take.
 *
 * The admin's password is random and never disclosed: the demo session is
 * seated by minting a bearer token directly ({@see DemoSessionSeater}), so
 * nobody can (or needs to) log in as a demo admin from the login form. Its
 * email is namespaced by the org slug (`users.email` is globally unique in
 * Vault's schema, so per-org fixed addresses would collide across demos).
 *
 * The admin seat is deliberate (#130 does NOT apply here): unlike the fixed
 * shared org, every visitor gets a private org, so write access endangers
 * nobody — upload → SHA-256 → audit trail becomes the demo's showcase, and
 * the TTL sweep erases whatever the visitor did.
 */
final readonly class DemoOrgProvisioner implements DisposableOrgProvisionerInterface
{
    public function __construct(
        private CreateOrganizationUseCaseInterface $createOrganization,
        private CreateUserUseCaseInterface $createUser,
        private DemoProvisionRegistry $registry,
    ) {
    }

    public function provision(string $slug, string $template): ProvisionedDemoOrg
    {
        try {
            $org = $this->createOrganization->execute(new CreateOrganizationInput(
                name: 'デモ商事株式会社（お試し）',
                slug: $slug,
            ));
        } catch (OrganizationSlugConflictException $exception) {
            throw new SlugConflictException(
                sprintf('Organization slug "%s" is already taken.', $slug),
                previous: $exception,
            );
        }

        $adminEmail = self::adminEmail($slug);
        $admin = $this->createUser->execute(
            $adminEmail,
            bin2hex(random_bytes(16)), // random throwaway; never disclosed
            'admin',
            $org->id,
            null,
        );

        $this->registry->register($org->id, $admin->id, $adminEmail);

        return new ProvisionedDemoOrg(orgId: $org->id, slug: $slug, adminUserId: $admin->id);
    }

    /** The slug-namespaced throwaway admin address for one demo org. */
    public static function adminEmail(string $slug): string
    {
        return sprintf('demo-admin@%s.nene-vault.dev', $slug);
    }
}
