<?php

declare(strict_types=1);

namespace NeneVault\Audit;

use LogicException;
use Nene2\Audit\AuditEventRepositoryInterface;
use Nene2\Audit\AuditPayloadMode;
use Nene2\Audit\AuditRecorderFactory;
use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Audit\AuditTableConfig;
use Nene2\Audit\PdoAuditEventRepository;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Http\ClockInterface;
use Nene2\Http\JsonResponseFactory;
use Nene2\Http\UtcClock;
use Psr\Container\ContainerInterface;

final readonly class AuditServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                ClockInterface::class,
                static fn (): ClockInterface => new UtcClock(),
            )
            ->set(
                AuditTableConfig::class,
                static function (): AuditTableConfig {
                    // vault's audit_events table predates the framework module: column
                    // names differ from AuditTableConfig::canonical() on two axes.
                    // `source` has no axis in AuditTableConfig — call sites fold it into
                    // `metadata['source']` instead (see AuditEventPresenter).
                    return new AuditTableConfig(
                        table: 'audit_events',
                        mode: AuditPayloadMode::BeforeAfter,
                        actorColumn: 'actor_user_id',
                        occurredAtColumn: 'created_at',
                        idIsAutoIncrement: true,
                    );
                },
            )
            ->set(
                AuditEventRepositoryInterface::class,
                static function (ContainerInterface $c): AuditEventRepositoryInterface {
                    $query = $c->get(DatabaseQueryExecutorInterface::class);
                    $config = $c->get(AuditTableConfig::class);

                    if (!$query instanceof DatabaseQueryExecutorInterface) {
                        throw new LogicException('DatabaseQueryExecutorInterface service is invalid.');
                    }

                    if (!$config instanceof AuditTableConfig) {
                        throw new LogicException('AuditTableConfig service is invalid.');
                    }

                    return new PdoAuditEventRepository($query, $config);
                },
            )
            ->set(
                AuditRecorderFactoryInterface::class,
                static function (ContainerInterface $c): AuditRecorderFactoryInterface {
                    $clock = $c->get(ClockInterface::class);
                    $config = $c->get(AuditTableConfig::class);

                    if (!$clock instanceof ClockInterface) {
                        throw new LogicException('ClockInterface service is invalid.');
                    }

                    if (!$config instanceof AuditTableConfig) {
                        throw new LogicException('AuditTableConfig service is invalid.');
                    }

                    // No org holder: RequestScopedHolder<string> (product) is not assignable to
                    // the framework's invariant RequestScopedHolder<string|int> — every call site
                    // sets organizationId explicitly on the AuditEvent instead (see recipe §PHPStan).
                    return new AuditRecorderFactory($clock, $config);
                },
            )
            ->set(
                ListAuditEventsUseCaseInterface::class,
                static function (ContainerInterface $c): ListAuditEventsUseCaseInterface {
                    $repo = $c->get(AuditEventRepositoryInterface::class);

                    if (!$repo instanceof AuditEventRepositoryInterface) {
                        throw new LogicException('AuditEventRepositoryInterface service is invalid.');
                    }

                    return new ListAuditEventsUseCase($repo);
                },
            )
            ->set(
                ListAuditEventsHandler::class,
                static function (ContainerInterface $c): ListAuditEventsHandler {
                    $useCase = $c->get(ListAuditEventsUseCaseInterface::class);
                    $json = $c->get(JsonResponseFactory::class);

                    if (!$useCase instanceof ListAuditEventsUseCaseInterface) {
                        throw new LogicException('ListAuditEventsUseCaseInterface service is invalid.');
                    }

                    if (!$json instanceof JsonResponseFactory) {
                        throw new LogicException('JsonResponseFactory service is invalid.');
                    }

                    return new ListAuditEventsHandler($useCase, $json);
                },
            )
            ->set(
                AuditRouteRegistrar::class,
                static function (ContainerInterface $c): AuditRouteRegistrar {
                    $handler = $c->get(ListAuditEventsHandler::class);

                    if (!$handler instanceof ListAuditEventsHandler) {
                        throw new LogicException('ListAuditEventsHandler service is invalid.');
                    }

                    return new AuditRouteRegistrar($handler);
                },
            );
    }
}
