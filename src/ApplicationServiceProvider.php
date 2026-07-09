<?php

declare(strict_types=1);

namespace NeneVault;

use LogicException;
use Nene2\Auth\TokenIssuerInterface;
use Nene2\Config\AppConfig;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Http\JsonResponseFactory;
use Nene2\Http\RequestScopedHolder;
use Nene2\Http\UtcClock;
use NeneVault\Audit\AuditRouteRegistrar;
use NeneVault\Audit\AuditServiceProvider;
use NeneVault\Auth\AuthRouteRegistrar;
use NeneVault\Auth\InvalidCredentialsExceptionHandler;
use NeneVault\Auth\UserRepositoryInterface;
use NeneVault\Demo\DemoRouteRegistrar;
use NeneVault\Demo\SeatFixedDemoHandler;
use NeneVault\Document\DocumentRouteRegistrar;
use NeneVault\Document\DocumentServiceProvider;
use NeneVault\Document\DuplicateFileExceptionHandler;
use NeneVault\Document\FileIntegrityExceptionHandler;
use NeneVault\Document\FileTooLargeExceptionHandler;
use NeneVault\Document\InvalidDocumentStateExceptionHandler;
use NeneVault\Document\MimeTypeNotAllowedExceptionHandler;
use NeneVault\Document\VaultDocumentNotFoundExceptionHandler;
use NeneVault\Export\ExportRouteRegistrar;
use NeneVault\Export\ExportServiceProvider;
use NeneVault\Http\HealthHandler;
use NeneVault\Ocr\OcrExceptionHandler;
use NeneVault\Ocr\OcrRouteRegistrar;
use NeneVault\Ocr\OcrServiceProvider;
use NeneVault\Organization\OrganizationNotFoundExceptionHandler;
use NeneVault\Organization\OrganizationRouteRegistrar;
use NeneVault\Organization\OrganizationServiceProvider;
use NeneVault\Organization\OrganizationSlugConflictExceptionHandler;
use NeneVault\User\CannotDeleteSelfExceptionHandler;
use NeneVault\User\InvalidUserRoleExceptionHandler;
use NeneVault\User\UserEmailConflictExceptionHandler;
use NeneVault\User\UserNotFoundExceptionHandler;
use NeneVault\User\UserRouteRegistrar;
use NeneVault\User\UserServiceProvider;
use NeneVault\VaultSettings\VaultSettingsRouteRegistrar;
use NeneVault\VaultSettings\VaultSettingsServiceProvider;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerInterface;

final readonly class ApplicationServiceProvider implements ServiceProviderInterface
{
    public const ROUTE_REGISTRARS = 'nene-vault.route_registrars';

    public const EXCEPTION_HANDLERS = 'nene-vault.exception_handlers';

    /** Container key for the shared RequestScopedHolder<int> that carries organization_id. */
    public const ORG_ID_HOLDER = 'nene-vault.org_id_holder';

    public function register(ContainerBuilder $builder): void
    {
        // Shared org_id holder: OrgResolverMiddleware writes, all org-scoped repos read.
        $builder->set(
            self::ORG_ID_HOLDER,
            static function (): RequestScopedHolder {
                /** @var RequestScopedHolder<int> */
                return new RequestScopedHolder();
            },
        );

        $builder
            ->addProvider(new AuditServiceProvider())
            ->addProvider(new OrganizationServiceProvider())
            ->addProvider(new VaultSettingsServiceProvider())
            ->addProvider(new DocumentServiceProvider())
            ->addProvider(new UserServiceProvider())
            ->addProvider(new ExportServiceProvider())
            ->addProvider(new OcrServiceProvider());

        // Health handler
        $builder->set(
            HealthHandler::class,
            static function (ContainerInterface $c): HealthHandler {
                $json = $c->get(JsonResponseFactory::class);

                if (!$json instanceof JsonResponseFactory) {
                    throw new LogicException('JsonResponseFactory service is invalid.');
                }

                return new HealthHandler($json);
            },
        );

        // Fixed-demo seat (#127)
        $builder->set(
            SeatFixedDemoHandler::class,
            static function (ContainerInterface $c): SeatFixedDemoHandler {
                $config = $c->get(AppConfig::class);
                $users = $c->get(UserRepositoryInterface::class);
                $issuer = $c->get(TokenIssuerInterface::class);
                $psr17 = $c->get(Psr17Factory::class);

                if (!$config instanceof AppConfig) {
                    throw new LogicException('AppConfig service is invalid.');
                }

                if (!$users instanceof UserRepositoryInterface) {
                    throw new LogicException('UserRepositoryInterface service is invalid.');
                }

                if (!$issuer instanceof TokenIssuerInterface) {
                    throw new LogicException('TokenIssuerInterface service is invalid.');
                }

                if (!$psr17 instanceof Psr17Factory) {
                    throw new LogicException('Psr17Factory service is invalid.');
                }

                return new SeatFixedDemoHandler($config->demo, $users, $issuer, $psr17, new UtcClock());
            },
        );
        $builder->set(
            DemoRouteRegistrar::class,
            static function (ContainerInterface $c): DemoRouteRegistrar {
                $handler = $c->get(SeatFixedDemoHandler::class);

                if (!$handler instanceof SeatFixedDemoHandler) {
                    throw new LogicException('SeatFixedDemoHandler service is invalid.');
                }

                return new DemoRouteRegistrar($handler);
            },
        );

        // Route registrars
        $builder->set(
            self::ROUTE_REGISTRARS,
            static function (ContainerInterface $c): array {
                $health = $c->get(HealthHandler::class);
                $auth = $c->get('nene-vault.route_registrar.auth');
                $org = $c->get(OrganizationRouteRegistrar::class);
                $settings = $c->get(VaultSettingsRouteRegistrar::class);
                $audit = $c->get(AuditRouteRegistrar::class);
                $document = $c->get(DocumentRouteRegistrar::class);
                $user = $c->get(UserRouteRegistrar::class);
                $export = $c->get(ExportRouteRegistrar::class);
                $ocr = $c->get(OcrRouteRegistrar::class);
                $demo = $c->get(DemoRouteRegistrar::class);

                if (!$health instanceof HealthHandler) {
                    throw new LogicException('HealthHandler service is invalid.');
                }

                if (!$auth instanceof AuthRouteRegistrar) {
                    throw new LogicException('AuthRouteRegistrar service is invalid.');
                }

                if (!$org instanceof OrganizationRouteRegistrar) {
                    throw new LogicException('OrganizationRouteRegistrar service is invalid.');
                }

                if (!$settings instanceof VaultSettingsRouteRegistrar) {
                    throw new LogicException('VaultSettingsRouteRegistrar service is invalid.');
                }

                if (!$audit instanceof AuditRouteRegistrar) {
                    throw new LogicException('AuditRouteRegistrar service is invalid.');
                }

                if (!$document instanceof DocumentRouteRegistrar) {
                    throw new LogicException('DocumentRouteRegistrar service is invalid.');
                }

                if (!$user instanceof UserRouteRegistrar) {
                    throw new LogicException('UserRouteRegistrar service is invalid.');
                }

                if (!$export instanceof ExportRouteRegistrar) {
                    throw new LogicException('ExportRouteRegistrar service is invalid.');
                }

                if (!$ocr instanceof OcrRouteRegistrar) {
                    throw new LogicException('OcrRouteRegistrar service is invalid.');
                }

                if (!$demo instanceof DemoRouteRegistrar) {
                    throw new LogicException('DemoRouteRegistrar service is invalid.');
                }

                return [
                    static fn ($router) => $router->get('/health', $health->handle(...)),
                    static fn ($router) => $auth->register($router),
                    static fn ($router) => $org->register($router),
                    static fn ($router) => $settings->register($router),
                    static fn ($router) => $audit->register($router),
                    static fn ($router) => $document->register($router),
                    static fn ($router) => $user->register($router),
                    static fn ($router) => $export->register($router),
                    static fn ($router) => $ocr->register($router),
                    static fn ($router) => $demo($router),
                ];
            },
        );

        // Exception handlers
        $builder->set(
            self::EXCEPTION_HANDLERS,
            static function (ContainerInterface $c): array {
                return [
                    $c->get(InvalidCredentialsExceptionHandler::class),
                    $c->get(OrganizationNotFoundExceptionHandler::class),
                    $c->get(OrganizationSlugConflictExceptionHandler::class),
                    $c->get(VaultDocumentNotFoundExceptionHandler::class),
                    $c->get(DuplicateFileExceptionHandler::class),
                    $c->get(MimeTypeNotAllowedExceptionHandler::class),
                    $c->get(FileTooLargeExceptionHandler::class),
                    $c->get(FileIntegrityExceptionHandler::class),
                    $c->get(InvalidDocumentStateExceptionHandler::class),
                    $c->get(UserNotFoundExceptionHandler::class),
                    $c->get(UserEmailConflictExceptionHandler::class),
                    $c->get(InvalidUserRoleExceptionHandler::class),
                    $c->get(CannotDeleteSelfExceptionHandler::class),
                    $c->get(OcrExceptionHandler::class),
                ];
            },
        );
    }
}
