<?php

declare(strict_types=1);

namespace NeneVault;

use LogicException;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Http\JsonResponseFactory;
use Nene2\Http\RequestScopedHolder;
use NeneVault\Audit\AuditRouteRegistrar;
use NeneVault\Audit\AuditServiceProvider;
use NeneVault\Auth\AuthRouteRegistrar;
use NeneVault\Auth\InvalidCredentialsExceptionHandler;
use NeneVault\Document\DocumentRouteRegistrar;
use NeneVault\Document\DocumentServiceProvider;
use NeneVault\Document\DuplicateFileExceptionHandler;
use NeneVault\Document\FileIntegrityExceptionHandler;
use NeneVault\Document\FileTooLargeExceptionHandler;
use NeneVault\Document\MimeTypeNotAllowedExceptionHandler;
use NeneVault\Document\VaultDocumentNotFoundExceptionHandler;
use NeneVault\Export\ExportRouteRegistrar;
use NeneVault\Export\ExportServiceProvider;
use NeneVault\Http\HealthHandler;
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
            ->addProvider(new ExportServiceProvider());

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

                return [
                    static fn ($router) => $router->get('/health', $health->handle(...)),
                    static fn ($router) => $auth->register($router),
                    static fn ($router) => $org->register($router),
                    static fn ($router) => $settings->register($router),
                    static fn ($router) => $audit->register($router),
                    static fn ($router) => $document->register($router),
                    static fn ($router) => $user->register($router),
                    static fn ($router) => $export->register($router),
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
                    $c->get(UserNotFoundExceptionHandler::class),
                    $c->get(UserEmailConflictExceptionHandler::class),
                    $c->get(InvalidUserRoleExceptionHandler::class),
                    $c->get(CannotDeleteSelfExceptionHandler::class),
                ];
            },
        );
    }
}
