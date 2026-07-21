<?php

declare(strict_types=1);

namespace NeneVault;

use Closure;
use LogicException;
use Nene2\Auth\TokenIssuerInterface;
use Nene2\Config\AppConfig;
use Nene2\Demo\DemoRouteRegistrar;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Http\RequestScopedHolder;
use Nene2\Http\UtcClock;
use NeneVault\Audit\AuditRouteRegistrar;
use NeneVault\Audit\AuditServiceProvider;
use NeneVault\Auth\AuthRouteRegistrar;
use NeneVault\Auth\InvalidCredentialsExceptionHandler;
use NeneVault\Auth\TooManyLoginAttemptsExceptionHandler;
use NeneVault\Auth\UserRepositoryInterface;
use NeneVault\Demo\DemoEntryLog;
use NeneVault\Demo\DemoServiceProvider;
use NeneVault\Demo\FileDemoEntryLogSink;
use NeneVault\Demo\GuidedDemoRouteRegistrar;
use NeneVault\Demo\SeatFixedDemoHandler;
use NeneVault\Document\DocumentRouteRegistrar;
use NeneVault\Document\DocumentServiceProvider;
use NeneVault\Document\DuplicateFileExceptionHandler;
use NeneVault\Document\EmptyFileExceptionHandler;
use NeneVault\Document\FileIntegrityExceptionHandler;
use NeneVault\Document\FileTooLargeExceptionHandler;
use NeneVault\Document\InvalidDocumentStateExceptionHandler;
use NeneVault\Document\MimeTypeNotAllowedExceptionHandler;
use NeneVault\Document\VaultDocumentNotFoundExceptionHandler;
use NeneVault\Export\ExportRouteRegistrar;
use NeneVault\Export\ExportServiceProvider;
use NeneVault\Http\RuntimeServiceProvider;
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
            ->addProvider(new OcrServiceProvider())
            ->addProvider(new DemoServiceProvider());

        // Fixed-demo seat (#127; served at /demo/guided since #141)
        $builder->set(
            SeatFixedDemoHandler::class,
            static function (ContainerInterface $c): SeatFixedDemoHandler {
                $config = $c->get(AppConfig::class);
                $users = $c->get(UserRepositoryInterface::class);
                $issuer = $c->get(TokenIssuerInterface::class);
                $psr17 = $c->get(Psr17Factory::class);
                $projectRoot = $c->get(RuntimeServiceProvider::PROJECT_ROOT);

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

                if (!is_string($projectRoot) || $projectRoot === '') {
                    throw new LogicException('Project root service is invalid.');
                }

                // File sink (#192): demo-entry lines go to var/demo-entry.log
                // (SSH-visible) instead of the default error_log (HETEML
                // control-panel-only, invisible for UTM analysis).
                $entryLog = new DemoEntryLog(Closure::fromCallable(
                    new FileDemoEntryLogSink($projectRoot . '/var'),
                ));

                return new SeatFixedDemoHandler($config->demo, $users, $issuer, $psr17, new UtcClock(), $entryLog);
            },
        );
        $builder->set(
            GuidedDemoRouteRegistrar::class,
            static function (ContainerInterface $c): GuidedDemoRouteRegistrar {
                $handler = $c->get(SeatFixedDemoHandler::class);

                if (!$handler instanceof SeatFixedDemoHandler) {
                    throw new LogicException('SeatFixedDemoHandler service is invalid.');
                }

                return new GuidedDemoRouteRegistrar($handler);
            },
        );

        // Route registrars
        $builder->set(
            self::ROUTE_REGISTRARS,
            static function (ContainerInterface $c): array {
                $auth = $c->get('nene-vault.route_registrar.auth');
                $org = $c->get(OrganizationRouteRegistrar::class);
                $settings = $c->get(VaultSettingsRouteRegistrar::class);
                $audit = $c->get(AuditRouteRegistrar::class);
                $document = $c->get(DocumentRouteRegistrar::class);
                $user = $c->get(UserRouteRegistrar::class);
                $export = $c->get(ExportRouteRegistrar::class);
                $ocr = $c->get(OcrRouteRegistrar::class);
                $guidedDemo = $c->get(GuidedDemoRouteRegistrar::class);
                $disposableDemo = $c->get(DemoRouteRegistrar::class);

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

                if (!$guidedDemo instanceof GuidedDemoRouteRegistrar) {
                    throw new LogicException('GuidedDemoRouteRegistrar service is invalid.');
                }

                if (!$disposableDemo instanceof DemoRouteRegistrar) {
                    throw new LogicException('DemoRouteRegistrar service is invalid.');
                }

                // GET /health is provided by RuntimeApplicationFactory with the
                // DatabaseHealthCheck wired in RuntimeServiceProvider (#163).
                return [
                    static fn ($router) => $auth->register($router),
                    static fn ($router) => $org->register($router),
                    static fn ($router) => $settings->register($router),
                    static fn ($router) => $audit->register($router),
                    static fn ($router) => $document->register($router),
                    static fn ($router) => $user->register($router),
                    static fn ($router) => $export->register($router),
                    static fn ($router) => $ocr->register($router),
                    static fn ($router) => $guidedDemo($router),
                    static fn ($router) => $disposableDemo($router),
                ];
            },
        );

        // Exception handlers
        $builder->set(
            self::EXCEPTION_HANDLERS,
            static function (ContainerInterface $c): array {
                return [
                    $c->get(InvalidCredentialsExceptionHandler::class),
                    $c->get(TooManyLoginAttemptsExceptionHandler::class),
                    $c->get(OrganizationNotFoundExceptionHandler::class),
                    $c->get(OrganizationSlugConflictExceptionHandler::class),
                    $c->get(VaultDocumentNotFoundExceptionHandler::class),
                    $c->get(DuplicateFileExceptionHandler::class),
                    $c->get(MimeTypeNotAllowedExceptionHandler::class),
                    $c->get(FileTooLargeExceptionHandler::class),
                    $c->get(EmptyFileExceptionHandler::class),
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
