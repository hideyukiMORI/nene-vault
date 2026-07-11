<?php

declare(strict_types=1);

namespace NeneVault\Tests\Organization\Resolution;

use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\RequestScopedHolder;
use NeneVault\Organization\Organization;
use NeneVault\Organization\OrganizationRepositoryInterface;
use NeneVault\Organization\Resolution\EnvResolutionStrategy;
use NeneVault\Organization\Resolution\OrgResolverMiddleware;
use NeneVault\Tests\Support\SpyRequestHandler;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Claim-based tenant resolution (#141): a verified bearer's integer `org_id`
 * claim wins over the host/env strategy; superadmin (`org_id: null`) and
 * unauthenticated requests fall back to the strategy unchanged; a claim
 * naming a missing org fails closed with 404.
 */
final class OrgResolverMiddlewareTest extends TestCase
{
    private Psr17Factory $psr17;

    protected function setUp(): void
    {
        $this->psr17 = new Psr17Factory();
    }

    /**
     * @param array<int, Organization>    $orgsById
     * @param array<string, Organization> $orgsBySlug
     */
    private function repository(array $orgsById = [], array $orgsBySlug = []): OrganizationRepositoryInterface
    {
        return new class ($orgsById, $orgsBySlug) implements OrganizationRepositoryInterface {
            /**
             * @param array<int, Organization>    $byId
             * @param array<string, Organization> $bySlug
             */
            public function __construct(private readonly array $byId, private readonly array $bySlug)
            {
            }

            public function findById(int $id): ?Organization
            {
                return $this->byId[$id] ?? null;
            }

            public function findBySlug(string $slug): ?Organization
            {
                return $this->bySlug[$slug] ?? null;
            }

            public function findByCustomDomain(string $domain): ?Organization
            {
                return null;
            }

            public function findAll(int $limit, int $offset): array
            {
                return [];
            }

            public function count(): int
            {
                return 0;
            }

            public function save(Organization $organization): int
            {
                throw new \LogicException('unused');
            }

            public function update(Organization $organization): void
            {
            }

            public function delete(int $id): void
            {
            }
        };
    }

    /**
     * @param RequestScopedHolder<int> $holder
     */
    private function middleware(
        OrganizationRepositoryInterface $repository,
        RequestScopedHolder $holder,
        ?string $envSlug = 'env-org',
    ): OrgResolverMiddleware {
        return new OrgResolverMiddleware(
            $holder,
            $repository,
            new ProblemDetailsResponseFactory($this->psr17, $this->psr17, 'https://nene-vault.dev/problems/'),
            new EnvResolutionStrategy($envSlug),
        );
    }

    private function spyHandler(): SpyRequestHandler
    {
        return new SpyRequestHandler($this->psr17);
    }

    /** @param array<string, mixed>|null $claims */
    private function request(string $path, ?array $claims = null): ServerRequestInterface
    {
        $request = $this->psr17->createServerRequest('GET', $path);

        return $claims === null ? $request : $request->withAttribute('nene2.auth.claims', $claims);
    }

    private static function org(int $id, string $slug, bool $active = true): Organization
    {
        return new Organization(name: 'Org ' . $id, slug: $slug, plan: 'free', isActive: $active, id: $id);
    }

    public function test_org_id_claim_wins_over_the_env_strategy(): void
    {
        $claimOrg = self::org(7, 'demo-abc123');
        $envOrg = self::org(1, 'env-org');
        /** @var RequestScopedHolder<int> $holder */
        $holder = new RequestScopedHolder();
        $handler = $this->spyHandler();

        $response = $this->middleware(
            $this->repository(orgsById: [7 => $claimOrg], orgsBySlug: ['env-org' => $envOrg]),
            $holder,
        )->process(
            $this->request('/admin/vault/documents', ['org' => 7, 'role' => 'admin']),
            $handler,
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertNotNull($handler->captured);
        self::assertSame(7, $handler->captured->getAttribute('nene2.org.id'));
        self::assertSame('demo-abc123', $handler->captured->getAttribute('nene2.org.slug'));
        self::assertSame(7, $holder->get());
    }

    public function test_claim_naming_a_missing_org_fails_closed_with_404(): void
    {
        $envOrg = self::org(1, 'env-org');
        /** @var RequestScopedHolder<int> $holder */
        $holder = new RequestScopedHolder();
        $handler = $this->spyHandler();

        $response = $this->middleware(
            $this->repository(orgsBySlug: ['env-org' => $envOrg]),
            $holder,
        )->process(
            $this->request('/admin/vault/documents', ['org' => 424242, 'role' => 'admin']),
            $handler,
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertNull($handler->captured, 'the request must not reach the handler');
        $body = json_decode((string) $response->getBody(), true);
        self::assertIsArray($body);
        self::assertSame('org-not-found', basename((string) $body['type']));
    }

    public function test_inactive_claim_org_is_refused_with_403(): void
    {
        $inactive = self::org(7, 'demo-abc123', active: false);
        /** @var RequestScopedHolder<int> $holder */
        $holder = new RequestScopedHolder();
        $handler = $this->spyHandler();

        $response = $this->middleware($this->repository(orgsById: [7 => $inactive]), $holder)->process(
            $this->request('/admin/vault/documents', ['org' => 7, 'role' => 'admin']),
            $handler,
        );

        self::assertSame(403, $response->getStatusCode());
        self::assertNull($handler->captured);
    }

    public function test_superadmin_null_org_claim_falls_back_to_the_strategy(): void
    {
        $envOrg = self::org(1, 'env-org');
        /** @var RequestScopedHolder<int> $holder */
        $holder = new RequestScopedHolder();
        $handler = $this->spyHandler();

        $response = $this->middleware($this->repository(orgsBySlug: ['env-org' => $envOrg]), $holder)->process(
            $this->request('/admin/vault/documents', ['org' => null, 'role' => 'superadmin']),
            $handler,
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertNotNull($handler->captured);
        self::assertSame(1, $handler->captured->getAttribute('nene2.org.id'));
    }

    public function test_unauthenticated_request_resolves_via_the_strategy(): void
    {
        $envOrg = self::org(1, 'env-org');
        /** @var RequestScopedHolder<int> $holder */
        $holder = new RequestScopedHolder();
        $handler = $this->spyHandler();

        $response = $this->middleware($this->repository(orgsBySlug: ['env-org' => $envOrg]), $holder)->process(
            $this->request('/admin/vault/documents'),
            $handler,
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertNotNull($handler->captured);
        self::assertSame(1, $handler->captured->getAttribute('nene2.org.id'));
        self::assertSame(1, $holder->get());
    }

    public function test_demo_paths_bypass_resolution_entirely(): void
    {
        /** @var RequestScopedHolder<int> $holder */
        $holder = new RequestScopedHolder();
        $handler = $this->spyHandler();

        $response = $this->middleware($this->repository(), $holder, envSlug: null)->process(
            $this->request('/demo/standard'),
            $handler,
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertNotNull($handler->captured);
        self::assertNull($handler->captured->getAttribute('nene2.org.id'));
    }

    public function test_unresolvable_unauthenticated_request_is_404(): void
    {
        /** @var RequestScopedHolder<int> $holder */
        $holder = new RequestScopedHolder();
        $handler = $this->spyHandler();

        $response = $this->middleware($this->repository(), $holder, envSlug: null)->process(
            $this->request('/admin/vault/documents'),
            $handler,
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertNull($handler->captured);
    }
}
