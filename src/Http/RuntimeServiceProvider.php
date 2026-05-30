<?php

declare(strict_types=1);

namespace NeneVault\Http;

use LogicException;
use Nene2\Auth\LocalBearerTokenVerifier;
use Nene2\Auth\TokenIssuerInterface;
use Nene2\Auth\TokenVerifierInterface;
use Nene2\Config\AppConfig;
use Nene2\Config\ConfigLoader;
use Nene2\Database\DatabaseConnectionFactoryInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Database\PdoDatabaseTransactionManager;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Error\DomainExceptionHandlerInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use Nene2\Http\RequestScopedHolder;
use Nene2\Http\ResponseEmitter;
use Nene2\Http\RuntimeApplicationFactory;
use Nene2\Log\MonologLoggerFactory;
use Nene2\Log\RequestIdHolder;
use NeneVault\ApplicationServiceProvider;
use NeneVault\Auth\AdminApiAuthMiddleware;
use NeneVault\Auth\AuthServiceProvider;
use NeneVault\Auth\CapabilityMiddleware;
use NeneVault\Organization\OrganizationRepositoryInterface;
use NeneVault\Organization\Resolution\CustomDomainResolutionStrategy;
use NeneVault\Organization\Resolution\EnvResolutionStrategy;
use NeneVault\Organization\Resolution\OrgResolverMiddleware;
use NeneVault\Organization\Resolution\SubdomainResolutionStrategy;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

final readonly class RuntimeServiceProvider implements ServiceProviderInterface
{
    public const PROJECT_ROOT = 'nene-vault.project_root';

    public function register(ContainerBuilder $builder): void
    {
        $builder->addProvider(new ApplicationServiceProvider());
        $builder->addProvider(new AuthServiceProvider());

        $builder
            ->set(
                ConfigLoader::class,
                static function (ContainerInterface $c): ConfigLoader {
                    $projectRoot = $c->get(self::PROJECT_ROOT);

                    if (!is_string($projectRoot) || $projectRoot === '') {
                        throw new LogicException('Project root service is invalid.');
                    }

                    return new ConfigLoader($projectRoot);
                },
            )
            ->set(
                AppConfig::class,
                static function (ContainerInterface $c): AppConfig {
                    $loader = $c->get(ConfigLoader::class);

                    if (!$loader instanceof ConfigLoader) {
                        throw new LogicException('Config loader service is invalid.');
                    }

                    return $loader->load();
                },
            )
            ->set(
                DatabaseConnectionFactoryInterface::class,
                static function (ContainerInterface $c): DatabaseConnectionFactoryInterface {
                    $config = $c->get(AppConfig::class);

                    if (!$config instanceof AppConfig) {
                        throw new LogicException('Application config service is invalid.');
                    }

                    return new PdoConnectionFactory($config->database);
                },
            )
            ->set(
                DatabaseQueryExecutorInterface::class,
                static function (ContainerInterface $c): DatabaseQueryExecutorInterface {
                    $conn = $c->get(DatabaseConnectionFactoryInterface::class);

                    if (!$conn instanceof DatabaseConnectionFactoryInterface) {
                        throw new LogicException('Database connection factory service is invalid.');
                    }

                    return new PdoDatabaseQueryExecutor($conn);
                },
            )
            ->set(
                DatabaseTransactionManagerInterface::class,
                static function (ContainerInterface $c): DatabaseTransactionManagerInterface {
                    $conn = $c->get(DatabaseConnectionFactoryInterface::class);

                    if (!$conn instanceof DatabaseConnectionFactoryInterface) {
                        throw new LogicException('Database connection factory service is invalid.');
                    }

                    return new PdoDatabaseTransactionManager($conn);
                },
            )
            ->set(Psr17Factory::class, static fn (): Psr17Factory => new Psr17Factory())
            ->set(
                ResponseFactoryInterface::class,
                static function (ContainerInterface $c): ResponseFactoryInterface {
                    $f = $c->get(Psr17Factory::class);

                    if (!$f instanceof ResponseFactoryInterface) {
                        throw new LogicException('PSR-17 response factory service is invalid.');
                    }

                    return $f;
                },
            )
            ->set(
                StreamFactoryInterface::class,
                static function (ContainerInterface $c): StreamFactoryInterface {
                    $f = $c->get(Psr17Factory::class);

                    if (!$f instanceof StreamFactoryInterface) {
                        throw new LogicException('PSR-17 stream factory service is invalid.');
                    }

                    return $f;
                },
            )
            ->set(
                JsonResponseFactory::class,
                static function (ContainerInterface $c): JsonResponseFactory {
                    $rf = $c->get(ResponseFactoryInterface::class);
                    $sf = $c->get(StreamFactoryInterface::class);

                    if (!$rf instanceof ResponseFactoryInterface) {
                        throw new LogicException('Response factory service is invalid.');
                    }

                    if (!$sf instanceof StreamFactoryInterface) {
                        throw new LogicException('Stream factory service is invalid.');
                    }

                    return new JsonResponseFactory($rf, $sf);
                },
            )
            ->set(
                ProblemDetailsResponseFactory::class,
                static function (ContainerInterface $c): ProblemDetailsResponseFactory {
                    $rf = $c->get(ResponseFactoryInterface::class);
                    $sf = $c->get(StreamFactoryInterface::class);
                    $config = $c->get(AppConfig::class);

                    if (!$rf instanceof ResponseFactoryInterface) {
                        throw new LogicException('Response factory service is invalid.');
                    }

                    if (!$sf instanceof StreamFactoryInterface) {
                        throw new LogicException('Stream factory service is invalid.');
                    }

                    if (!$config instanceof AppConfig) {
                        throw new LogicException('Application config service is invalid.');
                    }

                    return new ProblemDetailsResponseFactory($rf, $sf, $config->problemDetailsBaseUrl);
                },
            )
            ->set(RequestIdHolder::class, static fn (): RequestIdHolder => new RequestIdHolder())
            ->set(
                LocalBearerTokenVerifier::class,
                static function (ContainerInterface $c): LocalBearerTokenVerifier {
                    $config = $c->get(AppConfig::class);

                    if (!$config instanceof AppConfig) {
                        throw new LogicException('Application config service is invalid.');
                    }

                    return new LocalBearerTokenVerifier($config->localJwtSecret ?? 'nene-vault-dev-secret');
                },
            )
            ->set(
                TokenVerifierInterface::class,
                static function (ContainerInterface $c): TokenVerifierInterface {
                    $v = $c->get(LocalBearerTokenVerifier::class);

                    if (!$v instanceof TokenVerifierInterface) {
                        throw new LogicException('LocalBearerTokenVerifier service is invalid.');
                    }

                    return $v;
                },
            )
            ->set(
                TokenIssuerInterface::class,
                static function (ContainerInterface $c): TokenIssuerInterface {
                    $v = $c->get(LocalBearerTokenVerifier::class);

                    if (!$v instanceof TokenIssuerInterface) {
                        throw new LogicException('LocalBearerTokenVerifier service is invalid.');
                    }

                    return $v;
                },
            )
            ->set(
                'nene-vault.token_issuer',
                static function (ContainerInterface $c): TokenIssuerInterface {
                    return $c->get(TokenIssuerInterface::class);
                },
            )
            ->set(
                LoggerInterface::class,
                static function (ContainerInterface $c): LoggerInterface {
                    $config = $c->get(AppConfig::class);
                    $debug = $config instanceof AppConfig && $config->debug;
                    $holder = $c->get(RequestIdHolder::class);

                    return (new MonologLoggerFactory())->create(
                        'nene-vault',
                        $debug,
                        $holder instanceof RequestIdHolder ? $holder : null,
                    );
                },
            )
            ->set(
                RuntimeApplicationFactory::class,
                static function (ContainerInterface $c): RuntimeApplicationFactory {
                    $rf = $c->get(ResponseFactoryInterface::class);
                    $sf = $c->get(StreamFactoryInterface::class);
                    $logger = $c->get(LoggerInterface::class);
                    $config = $c->get(AppConfig::class);
                    $exceptionHandlers = $c->get(ApplicationServiceProvider::EXCEPTION_HANDLERS);
                    $routeRegistrars = $c->get(ApplicationServiceProvider::ROUTE_REGISTRARS);
                    $requestIdHolder = $c->get(RequestIdHolder::class);

                    if (!$rf instanceof ResponseFactoryInterface) {
                        throw new LogicException('Response factory service is invalid.');
                    }

                    if (!$sf instanceof StreamFactoryInterface) {
                        throw new LogicException('Stream factory service is invalid.');
                    }

                    if (!$logger instanceof LoggerInterface) {
                        throw new LogicException('Logger service is invalid.');
                    }

                    if (!$config instanceof AppConfig) {
                        throw new LogicException('Application config service is invalid.');
                    }

                    if (!is_array($exceptionHandlers) || !array_is_list($exceptionHandlers)) {
                        throw new LogicException('Exception handlers service is invalid.');
                    }

                    if (!is_array($routeRegistrars) || !array_is_list($routeRegistrars)) {
                        throw new LogicException('Route registrars service is invalid.');
                    }

                    /** @var list<DomainExceptionHandlerInterface> $exceptionHandlers */
                    /** @var list<callable(\Nene2\Routing\Router): void> $routeRegistrars */

                    if (!$requestIdHolder instanceof RequestIdHolder) {
                        throw new LogicException('RequestIdHolder service is invalid.');
                    }

                    // Org resolver strategy from TENANT_RESOLUTION env
                    $orgRepo = $c->get(OrganizationRepositoryInterface::class);
                    if (!$orgRepo instanceof OrganizationRepositoryInterface) {
                        throw new LogicException('OrganizationRepositoryInterface service is invalid.');
                    }

                    $orgIdHolder = $c->get(ApplicationServiceProvider::ORG_ID_HOLDER);
                    if (!$orgIdHolder instanceof RequestScopedHolder) {
                        throw new LogicException('Org ID holder service is invalid.');
                    }
                    /** @var RequestScopedHolder<int> $orgIdHolder */

                    $pd = $c->get(ProblemDetailsResponseFactory::class);
                    if (!$pd instanceof ProblemDetailsResponseFactory) {
                        throw new LogicException('ProblemDetailsResponseFactory service is invalid.');
                    }

                    $tokenVerifier = $c->get(TokenVerifierInterface::class);
                    if (!$tokenVerifier instanceof TokenVerifierInterface) {
                        throw new LogicException('TokenVerifierInterface service is invalid.');
                    }

                    $resolutionMode = (string) (getenv('TENANT_RESOLUTION') ?: 'single');
                    $orgSlug = (string) (getenv('ORG_SLUG') ?: '');
                    $baseDomain = (string) (getenv('BASE_DOMAIN') ?: 'localhost');

                    $strategy = match ($resolutionMode) {
                        'subdomain' => new SubdomainResolutionStrategy($baseDomain),
                        'custom_domain' => new CustomDomainResolutionStrategy(),
                        default => new EnvResolutionStrategy($orgSlug),
                    };

                    $authMiddleware = [
                        new OrgResolverMiddleware($orgIdHolder, $orgRepo, $pd, $strategy),
                        new AdminApiAuthMiddleware($pd, $tokenVerifier),
                        new CapabilityMiddleware($pd),
                    ];

                    return new RuntimeApplicationFactory(
                        responseFactory: $rf,
                        streamFactory: $sf,
                        logger: $logger,
                        machineApiKey: $config->machineApiKey,
                        domainExceptionHandlers: $exceptionHandlers,
                        requestIdHolder: $requestIdHolder,
                        routeRegistrars: $routeRegistrars,
                        authMiddleware: $authMiddleware,
                        debug: $config->debug,
                    );
                },
            )
            ->set(
                RequestHandlerInterface::class,
                static function (ContainerInterface $c): RequestHandlerInterface {
                    $factory = $c->get(RuntimeApplicationFactory::class);

                    if (!$factory instanceof RuntimeApplicationFactory) {
                        throw new LogicException('RuntimeApplicationFactory service is invalid.');
                    }

                    return $factory->create();
                },
            )
            ->set(ResponseEmitter::class, static fn (): ResponseEmitter => new ResponseEmitter());
    }
}
