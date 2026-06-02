<?php

declare(strict_types=1);

namespace NeneVault\Audit;

use LogicException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Http\JsonResponseFactory;
use Psr\Container\ContainerInterface;

final readonly class AuditServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                AuditEventRepositoryInterface::class,
                static function (ContainerInterface $c): AuditEventRepositoryInterface {
                    $query = $c->get(DatabaseQueryExecutorInterface::class);

                    if (!$query instanceof DatabaseQueryExecutorInterface) {
                        throw new LogicException('DatabaseQueryExecutorInterface service is invalid.');
                    }

                    return new PdoAuditEventRepository($query);
                },
            )
            ->set(
                AuditRecorderInterface::class,
                static function (ContainerInterface $c): AuditRecorderInterface {
                    $repo = $c->get(AuditEventRepositoryInterface::class);

                    if (!$repo instanceof AuditEventRepositoryInterface) {
                        throw new LogicException('AuditEventRepositoryInterface service is invalid.');
                    }

                    return new AuditRecorder($repo);
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
