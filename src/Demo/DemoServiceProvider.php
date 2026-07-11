<?php

declare(strict_types=1);

namespace NeneVault\Demo;

use LogicException;
use Nene2\Auth\TokenIssuerInterface;
use Nene2\Config\AppConfig;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Demo\CountingDemoCapacityGuard;
use Nene2\Demo\DemoConfig;
use Nene2\Demo\DemoRouteRegistrar;
use Nene2\Demo\StartDisposableDemoHandler;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\UtcClock;
use NeneVault\Document\RestoreDocumentUseCaseInterface;
use NeneVault\Document\UploadDocumentUseCaseInterface;
use NeneVault\Document\VoidDocumentUseCaseInterface;
use NeneVault\Http\RuntimeServiceProvider;
use NeneVault\Organization\CreateOrganizationUseCaseInterface;
use NeneVault\User\CreateUserUseCaseInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerInterface;

/**
 * Wires the disposable-demo domain as a `Nene2\Demo` consumer (#141): the
 * product concretes (provisioner over the real create-org/create-user use
 * cases, the #118 seeder behind the framework contract, the sessionStorage
 * seat page), the creation-time capacity guard (per-IP file-backed throttle +
 * instance-wide org ceiling), the branded browser error page, and the
 * framework handler + route registrar. No auth code is added — the seater
 * mints a token through the same {@see TokenIssuerInterface} a login uses,
 * and the claim-based tenant resolution (#141) scopes every subsequent API
 * call to the disposable org.
 *
 * The registrar is registered unconditionally: {@see StartDisposableDemoHandler}
 * answers 404 while {@see DemoConfig::$demoMode} is off (fail-close).
 */
final readonly class DemoServiceProvider implements ServiceProviderInterface
{
    /**
     * Demo starts allowed per client network per window (framework default,
     * NENE2 ADR 0018). Deliberately generous — a "client" is really one
     * office/carrier NAT, and runaway abuse stays bounded by the
     * instance-wide org ceiling plus the sweep cron.
     */
    public const int THROTTLE_LIMIT = 30;
    public const int THROTTLE_WINDOW_SECONDS = 3600;

    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                DemoConfig::class,
                static function (ContainerInterface $c): DemoConfig {
                    $config = $c->get(AppConfig::class);
                    if (!$config instanceof AppConfig) {
                        throw new LogicException('Application config service is invalid.');
                    }

                    return $config->demo;
                },
            )
            ->set(
                DemoProvisionRegistry::class,
                static fn (): DemoProvisionRegistry => new DemoProvisionRegistry(),
            )
            ->set(
                DemoOrgProvisioner::class,
                static function (ContainerInterface $c): DemoOrgProvisioner {
                    $createOrg = $c->get(CreateOrganizationUseCaseInterface::class);
                    if (!$createOrg instanceof CreateOrganizationUseCaseInterface) {
                        throw new LogicException('CreateOrganizationUseCaseInterface service is invalid.');
                    }

                    $createUser = $c->get(CreateUserUseCaseInterface::class);
                    if (!$createUser instanceof CreateUserUseCaseInterface) {
                        throw new LogicException('CreateUserUseCaseInterface service is invalid.');
                    }

                    return new DemoOrgProvisioner($createOrg, $createUser, self::registry($c));
                },
            )
            ->set(
                DisposableDemoSeeder::class,
                static function (ContainerInterface $c): DisposableDemoSeeder {
                    $upload = $c->get(UploadDocumentUseCaseInterface::class);
                    if (!$upload instanceof UploadDocumentUseCaseInterface) {
                        throw new LogicException('UploadDocumentUseCaseInterface service is invalid.');
                    }

                    $void = $c->get(VoidDocumentUseCaseInterface::class);
                    if (!$void instanceof VoidDocumentUseCaseInterface) {
                        throw new LogicException('VoidDocumentUseCaseInterface service is invalid.');
                    }

                    $restore = $c->get(RestoreDocumentUseCaseInterface::class);
                    if (!$restore instanceof RestoreDocumentUseCaseInterface) {
                        throw new LogicException('RestoreDocumentUseCaseInterface service is invalid.');
                    }

                    return new DisposableDemoSeeder(
                        new DemoDataSeeder($upload, $void, $restore, self::query($c), new UtcClock()),
                        self::registry($c),
                    );
                },
            )
            ->set(
                DemoSessionSeater::class,
                static function (ContainerInterface $c): DemoSessionSeater {
                    $tokenIssuer = $c->get(TokenIssuerInterface::class);
                    if (!$tokenIssuer instanceof TokenIssuerInterface) {
                        throw new LogicException('Token issuer service is invalid.');
                    }

                    return new DemoSessionSeater(
                        self::demoConfig($c),
                        self::registry($c),
                        $tokenIssuer,
                        self::psr17($c),
                        new UtcClock(),
                    );
                },
            )
            ->set(
                CountingDemoCapacityGuard::class,
                static function (ContainerInterface $c): CountingDemoCapacityGuard {
                    $config = self::demoConfig($c);
                    $query = self::query($c);

                    $projectRoot = $c->get(RuntimeServiceProvider::PROJECT_ROOT);
                    if (!is_string($projectRoot) || $projectRoot === '') {
                        throw new LogicException('Project root service is invalid.');
                    }

                    return new CountingDemoCapacityGuard(
                        demoOrgCount: static function () use ($query, $config): int {
                            // ESCAPE '|' — a backslash escape char is itself escaped
                            // differently by MySQL string literals vs SQLite (clear #277).
                            $row = $query->fetchOne(
                                "SELECT COUNT(*) AS n FROM organizations WHERE slug LIKE ? ESCAPE '|'",
                                [str_replace(['|', '%', '_'], ['||', '|%', '|_'], $config->slugPrefix) . '%'],
                            );

                            return is_array($row) ? (int) $row['n'] : 0;
                        },
                        config: $config,
                        throttleStorage: new FileRateLimitStorage($projectRoot . '/var', new UtcClock()),
                        throttleLimit: self::THROTTLE_LIMIT,
                        throttleWindowSeconds: self::THROTTLE_WINDOW_SECONDS,
                        clock: new UtcClock(),
                    );
                },
            )
            ->set(
                DemoBrowserErrorPage::class,
                static fn (ContainerInterface $c): DemoBrowserErrorPage => new DemoBrowserErrorPage(
                    self::psr17($c),
                    self::THROTTLE_LIMIT,
                    self::THROTTLE_WINDOW_SECONDS,
                ),
            )
            ->set(
                StartDisposableDemoHandler::class,
                static function (ContainerInterface $c): StartDisposableDemoHandler {
                    $problemDetails = $c->get(ProblemDetailsResponseFactory::class);
                    if (!$problemDetails instanceof ProblemDetailsResponseFactory) {
                        throw new LogicException('Problem details response factory service is invalid.');
                    }

                    $capacityGuard = $c->get(CountingDemoCapacityGuard::class);
                    if (!$capacityGuard instanceof CountingDemoCapacityGuard) {
                        throw new LogicException('Demo capacity guard service is invalid.');
                    }

                    $provisioner = $c->get(DemoOrgProvisioner::class);
                    if (!$provisioner instanceof DemoOrgProvisioner) {
                        throw new LogicException('Demo org provisioner service is invalid.');
                    }

                    $seeder = $c->get(DisposableDemoSeeder::class);
                    if (!$seeder instanceof DisposableDemoSeeder) {
                        throw new LogicException('Demo data seeder service is invalid.');
                    }

                    $seater = $c->get(DemoSessionSeater::class);
                    if (!$seater instanceof DemoSessionSeater) {
                        throw new LogicException('Demo session seater service is invalid.');
                    }

                    $errorPage = $c->get(DemoBrowserErrorPage::class);
                    if (!$errorPage instanceof DemoBrowserErrorPage) {
                        throw new LogicException('Demo error page renderer service is invalid.');
                    }

                    return new StartDisposableDemoHandler(
                        config: self::demoConfig($c),
                        capacityGuard: $capacityGuard,
                        provisioner: $provisioner,
                        seeder: $seeder,
                        seater: $seater,
                        problemDetails: $problemDetails,
                        templateKeyClass: DemoTemplate::class,
                        errorPageRenderer: $errorPage,
                    );
                },
            )
            ->set(
                DemoRouteRegistrar::class,
                static function (ContainerInterface $c): DemoRouteRegistrar {
                    $handler = $c->get(StartDisposableDemoHandler::class);
                    if (!$handler instanceof StartDisposableDemoHandler) {
                        throw new LogicException('Demo start handler service is invalid.');
                    }

                    return new DemoRouteRegistrar($handler);
                },
            );
    }

    private static function registry(ContainerInterface $c): DemoProvisionRegistry
    {
        $registry = $c->get(DemoProvisionRegistry::class);
        if (!$registry instanceof DemoProvisionRegistry) {
            throw new LogicException('Demo provision registry service is invalid.');
        }

        return $registry;
    }

    private static function query(ContainerInterface $c): DatabaseQueryExecutorInterface
    {
        $query = $c->get(DatabaseQueryExecutorInterface::class);
        if (!$query instanceof DatabaseQueryExecutorInterface) {
            throw new LogicException('Database query executor service is invalid.');
        }

        return $query;
    }

    private static function psr17(ContainerInterface $c): Psr17Factory
    {
        $psr17 = $c->get(Psr17Factory::class);
        if (!$psr17 instanceof Psr17Factory) {
            throw new LogicException('PSR-17 factory service is invalid.');
        }

        return $psr17;
    }

    private static function demoConfig(ContainerInterface $c): DemoConfig
    {
        $config = $c->get(DemoConfig::class);
        if (!$config instanceof DemoConfig) {
            throw new LogicException('Demo config service is invalid.');
        }

        return $config;
    }
}
