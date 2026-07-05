<?php

declare(strict_types=1);

namespace NeneVault\VaultSettings;

use LogicException;
use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Http\JsonResponseFactory;
use Psr\Container\ContainerInterface;

final readonly class VaultSettingsServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                VaultSettingsRepositoryInterface::class,
                static function (ContainerInterface $c): VaultSettingsRepositoryInterface {
                    $query = $c->get(DatabaseQueryExecutorInterface::class);

                    if (!$query instanceof DatabaseQueryExecutorInterface) {
                        throw new LogicException('DatabaseQueryExecutorInterface service is invalid.');
                    }

                    return new PdoVaultSettingsRepository($query);
                },
            )
            ->set(
                VaultSettingsSeederInterface::class,
                static function (ContainerInterface $c): VaultSettingsSeederInterface {
                    $repo = $c->get(VaultSettingsRepositoryInterface::class);

                    if (!$repo instanceof PdoVaultSettingsRepository) {
                        throw new LogicException('VaultSettingsRepository service is invalid.');
                    }

                    return $repo;
                },
            )
            ->set(
                GetVaultSettingsUseCaseInterface::class,
                static function (ContainerInterface $c): GetVaultSettingsUseCaseInterface {
                    $repo = $c->get(VaultSettingsRepositoryInterface::class);

                    if (!$repo instanceof VaultSettingsRepositoryInterface) {
                        throw new LogicException('VaultSettingsRepositoryInterface service is invalid.');
                    }

                    return new GetVaultSettingsUseCase($repo);
                },
            )
            ->set(
                GetVaultSettingsHandler::class,
                static function (ContainerInterface $c): GetVaultSettingsHandler {
                    $useCase = $c->get(GetVaultSettingsUseCaseInterface::class);
                    $json = $c->get(JsonResponseFactory::class);

                    if (!$useCase instanceof GetVaultSettingsUseCaseInterface) {
                        throw new LogicException('GetVaultSettingsUseCaseInterface service is invalid.');
                    }

                    if (!$json instanceof JsonResponseFactory) {
                        throw new LogicException('JsonResponseFactory service is invalid.');
                    }

                    return new GetVaultSettingsHandler($useCase, $json);
                },
            )
            ->set(
                UpdateVaultSettingsUseCaseInterface::class,
                static function (ContainerInterface $c): UpdateVaultSettingsUseCaseInterface {
                    $tx = $c->get(DatabaseTransactionManagerInterface::class);

                    if (!$tx instanceof DatabaseTransactionManagerInterface) {
                        throw new LogicException('DatabaseTransactionManagerInterface service is invalid.');
                    }

                    $audit = $c->get(AuditRecorderFactoryInterface::class);

                    if (!$audit instanceof AuditRecorderFactoryInterface) {
                        throw new LogicException('AuditRecorderFactoryInterface service is invalid.');
                    }

                    return new UpdateVaultSettingsUseCase(
                        $tx,
                        static fn (DatabaseQueryExecutorInterface $e): VaultSettingsRepositoryInterface => new PdoVaultSettingsRepository($e),
                        $audit,
                    );
                },
            )
            ->set(
                UpdateVaultSettingsHandler::class,
                static function (ContainerInterface $c): UpdateVaultSettingsHandler {
                    $useCase = $c->get(UpdateVaultSettingsUseCaseInterface::class);
                    $json = $c->get(JsonResponseFactory::class);

                    if (!$useCase instanceof UpdateVaultSettingsUseCaseInterface) {
                        throw new LogicException('UpdateVaultSettingsUseCaseInterface service is invalid.');
                    }

                    if (!$json instanceof JsonResponseFactory) {
                        throw new LogicException('JsonResponseFactory service is invalid.');
                    }

                    return new UpdateVaultSettingsHandler($useCase, $json);
                },
            )
            ->set(
                VaultSettingsRouteRegistrar::class,
                static function (ContainerInterface $c): VaultSettingsRouteRegistrar {
                    $get = $c->get(GetVaultSettingsHandler::class);
                    $update = $c->get(UpdateVaultSettingsHandler::class);

                    if (!$get instanceof GetVaultSettingsHandler) {
                        throw new LogicException('GetVaultSettingsHandler service is invalid.');
                    }

                    if (!$update instanceof UpdateVaultSettingsHandler) {
                        throw new LogicException('UpdateVaultSettingsHandler service is invalid.');
                    }

                    return new VaultSettingsRouteRegistrar($get, $update);
                },
            );
    }
}
