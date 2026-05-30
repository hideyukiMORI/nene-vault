<?php

declare(strict_types=1);

namespace NeneVault\Organization;

use LogicException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use NeneVault\VaultSettings\VaultSettingsSeederInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;

final readonly class OrganizationServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                OrganizationRepositoryInterface::class,
                static function (ContainerInterface $c): OrganizationRepositoryInterface {
                    $query = $c->get(DatabaseQueryExecutorInterface::class);

                    if (!$query instanceof DatabaseQueryExecutorInterface) {
                        throw new LogicException('DatabaseQueryExecutorInterface service is invalid.');
                    }

                    return new PdoOrganizationRepository($query);
                },
            )
            // ── Use cases ──
            ->set(
                CreateOrganizationUseCaseInterface::class,
                static function (ContainerInterface $c): CreateOrganizationUseCaseInterface {
                    $repo = $c->get(OrganizationRepositoryInterface::class);
                    $seeder = $c->get(VaultSettingsSeederInterface::class);

                    if (!$repo instanceof OrganizationRepositoryInterface) {
                        throw new LogicException('OrganizationRepositoryInterface service is invalid.');
                    }

                    if (!$seeder instanceof VaultSettingsSeederInterface) {
                        throw new LogicException('VaultSettingsSeederInterface service is invalid.');
                    }

                    return new CreateOrganizationUseCase($repo, $seeder);
                },
            )
            ->set(
                ListOrganizationsUseCaseInterface::class,
                static function (ContainerInterface $c): ListOrganizationsUseCaseInterface {
                    $repo = $c->get(OrganizationRepositoryInterface::class);

                    if (!$repo instanceof OrganizationRepositoryInterface) {
                        throw new LogicException('OrganizationRepositoryInterface service is invalid.');
                    }

                    return new ListOrganizationsUseCase($repo);
                },
            )
            ->set(
                GetOrganizationByIdUseCaseInterface::class,
                static function (ContainerInterface $c): GetOrganizationByIdUseCaseInterface {
                    $repo = $c->get(OrganizationRepositoryInterface::class);

                    if (!$repo instanceof OrganizationRepositoryInterface) {
                        throw new LogicException('OrganizationRepositoryInterface service is invalid.');
                    }

                    return new GetOrganizationByIdUseCase($repo);
                },
            )
            ->set(
                UpdateOrganizationUseCaseInterface::class,
                static function (ContainerInterface $c): UpdateOrganizationUseCaseInterface {
                    $repo = $c->get(OrganizationRepositoryInterface::class);

                    if (!$repo instanceof OrganizationRepositoryInterface) {
                        throw new LogicException('OrganizationRepositoryInterface service is invalid.');
                    }

                    return new UpdateOrganizationUseCase($repo);
                },
            )
            ->set(
                DeleteOrganizationUseCaseInterface::class,
                static function (ContainerInterface $c): DeleteOrganizationUseCaseInterface {
                    $repo = $c->get(OrganizationRepositoryInterface::class);

                    if (!$repo instanceof OrganizationRepositoryInterface) {
                        throw new LogicException('OrganizationRepositoryInterface service is invalid.');
                    }

                    return new DeleteOrganizationUseCase($repo);
                },
            )
            // ── Handlers ──
            ->set(
                ListOrganizationsHandler::class,
                static function (ContainerInterface $c): ListOrganizationsHandler {
                    $uc = $c->get(ListOrganizationsUseCaseInterface::class);
                    $json = $c->get(JsonResponseFactory::class);

                    if (!$uc instanceof ListOrganizationsUseCaseInterface) {
                        throw new LogicException('ListOrganizationsUseCaseInterface service is invalid.');
                    }

                    if (!$json instanceof JsonResponseFactory) {
                        throw new LogicException('JsonResponseFactory service is invalid.');
                    }

                    return new ListOrganizationsHandler($uc, $json);
                },
            )
            ->set(
                GetOrganizationByIdHandler::class,
                static function (ContainerInterface $c): GetOrganizationByIdHandler {
                    $uc = $c->get(GetOrganizationByIdUseCaseInterface::class);
                    $json = $c->get(JsonResponseFactory::class);

                    if (!$uc instanceof GetOrganizationByIdUseCaseInterface) {
                        throw new LogicException('GetOrganizationByIdUseCaseInterface service is invalid.');
                    }

                    if (!$json instanceof JsonResponseFactory) {
                        throw new LogicException('JsonResponseFactory service is invalid.');
                    }

                    return new GetOrganizationByIdHandler($uc, $json);
                },
            )
            ->set(
                CreateOrganizationHandler::class,
                static function (ContainerInterface $c): CreateOrganizationHandler {
                    $uc = $c->get(CreateOrganizationUseCaseInterface::class);
                    $json = $c->get(JsonResponseFactory::class);

                    if (!$uc instanceof CreateOrganizationUseCaseInterface) {
                        throw new LogicException('CreateOrganizationUseCaseInterface service is invalid.');
                    }

                    if (!$json instanceof JsonResponseFactory) {
                        throw new LogicException('JsonResponseFactory service is invalid.');
                    }

                    return new CreateOrganizationHandler($uc, $json);
                },
            )
            ->set(
                UpdateOrganizationHandler::class,
                static function (ContainerInterface $c): UpdateOrganizationHandler {
                    $uc = $c->get(UpdateOrganizationUseCaseInterface::class);
                    $json = $c->get(JsonResponseFactory::class);

                    if (!$uc instanceof UpdateOrganizationUseCaseInterface) {
                        throw new LogicException('UpdateOrganizationUseCaseInterface service is invalid.');
                    }

                    if (!$json instanceof JsonResponseFactory) {
                        throw new LogicException('JsonResponseFactory service is invalid.');
                    }

                    return new UpdateOrganizationHandler($uc, $json);
                },
            )
            ->set(
                DeleteOrganizationHandler::class,
                static function (ContainerInterface $c): DeleteOrganizationHandler {
                    $uc = $c->get(DeleteOrganizationUseCaseInterface::class);
                    $responseFactory = $c->get(ResponseFactoryInterface::class);

                    if (!$uc instanceof DeleteOrganizationUseCaseInterface) {
                        throw new LogicException('DeleteOrganizationUseCaseInterface service is invalid.');
                    }

                    if (!$responseFactory instanceof ResponseFactoryInterface) {
                        throw new LogicException('ResponseFactoryInterface service is invalid.');
                    }

                    return new DeleteOrganizationHandler($uc, $responseFactory);
                },
            )
            // ── Exception handlers ──
            ->set(
                OrganizationNotFoundExceptionHandler::class,
                static function (ContainerInterface $c): OrganizationNotFoundExceptionHandler {
                    $pd = $c->get(ProblemDetailsResponseFactory::class);

                    if (!$pd instanceof ProblemDetailsResponseFactory) {
                        throw new LogicException('ProblemDetailsResponseFactory service is invalid.');
                    }

                    return new OrganizationNotFoundExceptionHandler($pd);
                },
            )
            ->set(
                OrganizationSlugConflictExceptionHandler::class,
                static function (ContainerInterface $c): OrganizationSlugConflictExceptionHandler {
                    $pd = $c->get(ProblemDetailsResponseFactory::class);

                    if (!$pd instanceof ProblemDetailsResponseFactory) {
                        throw new LogicException('ProblemDetailsResponseFactory service is invalid.');
                    }

                    return new OrganizationSlugConflictExceptionHandler($pd);
                },
            )
            // ── Route registrar ──
            ->set(
                OrganizationRouteRegistrar::class,
                static function (ContainerInterface $c): OrganizationRouteRegistrar {
                    return new OrganizationRouteRegistrar(
                        list: $c->get(ListOrganizationsHandler::class),
                        get: $c->get(GetOrganizationByIdHandler::class),
                        create: $c->get(CreateOrganizationHandler::class),
                        update: $c->get(UpdateOrganizationHandler::class),
                        delete: $c->get(DeleteOrganizationHandler::class),
                    );
                },
            );
    }
}
