<?php

declare(strict_types=1);

namespace NeneVault\User;

use Closure;
use LogicException;
use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use NeneVault\Auth\PdoUserRepository;
use NeneVault\Auth\UserRepositoryInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;

final readonly class UserServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                CreateUserUseCaseInterface::class,
                static fn (ContainerInterface $c): CreateUserUseCaseInterface
                    => new CreateUserUseCase(self::tx($c), self::usersFactory(), self::auditFactory($c)),
            )
            ->set(
                UpdateUserUseCaseInterface::class,
                static fn (ContainerInterface $c): UpdateUserUseCaseInterface
                    => new UpdateUserUseCase(self::tx($c), self::usersFactory(), self::auditFactory($c)),
            )
            ->set(
                DeleteUserUseCaseInterface::class,
                static fn (ContainerInterface $c): DeleteUserUseCaseInterface
                    => new DeleteUserUseCase(self::tx($c), self::usersFactory(), self::auditFactory($c)),
            )
            ->set(
                ListUsersUseCaseInterface::class,
                static fn (ContainerInterface $c): ListUsersUseCaseInterface
                    => new ListUsersUseCase(self::users($c)),
            )
            ->set(
                GetUserByIdUseCaseInterface::class,
                static fn (ContainerInterface $c): GetUserByIdUseCaseInterface
                    => new GetUserByIdUseCase(self::users($c)),
            )
            ->set(
                ListUsersHandler::class,
                static function (ContainerInterface $c): ListUsersHandler {
                    $uc = $c->get(ListUsersUseCaseInterface::class);

                    if (!$uc instanceof ListUsersUseCaseInterface) {
                        throw new LogicException('ListUsersUseCaseInterface service is invalid.');
                    }

                    return new ListUsersHandler($uc, self::json($c));
                },
            )
            ->set(
                GetUserByIdHandler::class,
                static function (ContainerInterface $c): GetUserByIdHandler {
                    $uc = $c->get(GetUserByIdUseCaseInterface::class);

                    if (!$uc instanceof GetUserByIdUseCaseInterface) {
                        throw new LogicException('GetUserByIdUseCaseInterface service is invalid.');
                    }

                    return new GetUserByIdHandler($uc, self::json($c));
                },
            )
            ->set(
                CreateUserHandler::class,
                static function (ContainerInterface $c): CreateUserHandler {
                    $uc = $c->get(CreateUserUseCaseInterface::class);

                    if (!$uc instanceof CreateUserUseCaseInterface) {
                        throw new LogicException('CreateUserUseCaseInterface service is invalid.');
                    }

                    return new CreateUserHandler($uc, self::json($c));
                },
            )
            ->set(
                UpdateUserHandler::class,
                static function (ContainerInterface $c): UpdateUserHandler {
                    $uc = $c->get(UpdateUserUseCaseInterface::class);

                    if (!$uc instanceof UpdateUserUseCaseInterface) {
                        throw new LogicException('UpdateUserUseCaseInterface service is invalid.');
                    }

                    return new UpdateUserHandler($uc, self::json($c));
                },
            )
            ->set(
                DeleteUserHandler::class,
                static function (ContainerInterface $c): DeleteUserHandler {
                    $uc = $c->get(DeleteUserUseCaseInterface::class);
                    $rf = $c->get(ResponseFactoryInterface::class);

                    if (!$uc instanceof DeleteUserUseCaseInterface) {
                        throw new LogicException('DeleteUserUseCaseInterface service is invalid.');
                    }

                    if (!$rf instanceof ResponseFactoryInterface) {
                        throw new LogicException('ResponseFactoryInterface service is invalid.');
                    }

                    return new DeleteUserHandler($uc, $rf);
                },
            )
            ->set(
                UserRouteRegistrar::class,
                static function (ContainerInterface $c): UserRouteRegistrar {
                    return new UserRouteRegistrar(
                        self::handler($c, ListUsersHandler::class),
                        self::handler($c, GetUserByIdHandler::class),
                        self::handler($c, CreateUserHandler::class),
                        self::handler($c, UpdateUserHandler::class),
                        self::handler($c, DeleteUserHandler::class),
                    );
                },
            )
            ->set(
                UserNotFoundExceptionHandler::class,
                static fn (ContainerInterface $c): UserNotFoundExceptionHandler
                    => new UserNotFoundExceptionHandler(self::pd($c)),
            )
            ->set(
                UserEmailConflictExceptionHandler::class,
                static fn (ContainerInterface $c): UserEmailConflictExceptionHandler
                    => new UserEmailConflictExceptionHandler(self::pd($c)),
            )
            ->set(
                InvalidUserRoleExceptionHandler::class,
                static fn (ContainerInterface $c): InvalidUserRoleExceptionHandler
                    => new InvalidUserRoleExceptionHandler(self::pd($c)),
            )
            ->set(
                CannotDeleteSelfExceptionHandler::class,
                static fn (ContainerInterface $c): CannotDeleteSelfExceptionHandler
                    => new CannotDeleteSelfExceptionHandler(self::pd($c)),
            );
    }

    private static function users(ContainerInterface $c): UserRepositoryInterface
    {
        $r = $c->get(UserRepositoryInterface::class);

        if (!$r instanceof UserRepositoryInterface) {
            throw new LogicException('UserRepositoryInterface service is invalid.');
        }

        return $r;
    }

    private static function tx(ContainerInterface $c): DatabaseTransactionManagerInterface
    {
        $t = $c->get(DatabaseTransactionManagerInterface::class);

        if (!$t instanceof DatabaseTransactionManagerInterface) {
            throw new LogicException('DatabaseTransactionManagerInterface service is invalid.');
        }

        return $t;
    }

    /** @return Closure(DatabaseQueryExecutorInterface): UserRepositoryInterface */
    private static function usersFactory(): Closure
    {
        return static fn (DatabaseQueryExecutorInterface $e): UserRepositoryInterface => new PdoUserRepository($e);
    }

    private static function auditFactory(ContainerInterface $c): AuditRecorderFactoryInterface
    {
        $f = $c->get(AuditRecorderFactoryInterface::class);

        if (!$f instanceof AuditRecorderFactoryInterface) {
            throw new LogicException('AuditRecorderFactoryInterface service is invalid.');
        }

        return $f;
    }

    private static function json(ContainerInterface $c): JsonResponseFactory
    {
        $j = $c->get(JsonResponseFactory::class);

        if (!$j instanceof JsonResponseFactory) {
            throw new LogicException('JsonResponseFactory service is invalid.');
        }

        return $j;
    }

    private static function pd(ContainerInterface $c): ProblemDetailsResponseFactory
    {
        $p = $c->get(ProblemDetailsResponseFactory::class);

        if (!$p instanceof ProblemDetailsResponseFactory) {
            throw new LogicException('ProblemDetailsResponseFactory service is invalid.');
        }

        return $p;
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    private static function handler(ContainerInterface $c, string $class): object
    {
        $h = $c->get($class);

        if (!$h instanceof $class) {
            throw new LogicException($class . ' service is invalid.');
        }

        return $h;
    }
}
