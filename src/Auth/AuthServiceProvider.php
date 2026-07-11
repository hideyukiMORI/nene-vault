<?php

declare(strict_types=1);

namespace NeneVault\Auth;

use LogicException;
use Nene2\Auth\TokenIssuerInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\ClockInterface;
use Nene2\Http\JsonResponseFactory;
use Psr\Container\ContainerInterface;

final readonly class AuthServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                UserRepositoryInterface::class,
                static function (ContainerInterface $c): UserRepositoryInterface {
                    $query = $c->get(DatabaseQueryExecutorInterface::class);

                    if (!$query instanceof DatabaseQueryExecutorInterface) {
                        throw new LogicException('DatabaseQueryExecutorInterface service is invalid.');
                    }

                    return new PdoUserRepository($query);
                },
            )
            ->set(
                LoginUseCase::class,
                static function (ContainerInterface $c): LoginUseCase {
                    $users = $c->get(UserRepositoryInterface::class);
                    $tokenIssuer = $c->get('nene-vault.token_issuer');

                    if (!$users instanceof UserRepositoryInterface) {
                        throw new LogicException('UserRepositoryInterface service is invalid.');
                    }

                    if (!$tokenIssuer instanceof TokenIssuerInterface) {
                        throw new LogicException('TokenIssuerInterface service is invalid.');
                    }

                    return new LoginUseCase($users, $tokenIssuer);
                },
            )
            ->set(
                LoginThrottleInterface::class,
                static function (ContainerInterface $c): LoginThrottleInterface {
                    $query = $c->get(DatabaseQueryExecutorInterface::class);
                    $clock = $c->get(ClockInterface::class);

                    if (!$query instanceof DatabaseQueryExecutorInterface) {
                        throw new LogicException('DatabaseQueryExecutorInterface service is invalid.');
                    }

                    if (!$clock instanceof ClockInterface) {
                        throw new LogicException('ClockInterface service is invalid.');
                    }

                    return new PdoLoginThrottle($query, $clock);
                },
            )
            ->set(
                LoginHandler::class,
                static function (ContainerInterface $c): LoginHandler {
                    $useCase = $c->get(LoginUseCase::class);
                    $json = $c->get(JsonResponseFactory::class);
                    $throttle = $c->get(LoginThrottleInterface::class);

                    if (!$useCase instanceof LoginUseCase) {
                        throw new LogicException('LoginUseCase service is invalid.');
                    }

                    if (!$json instanceof JsonResponseFactory) {
                        throw new LogicException('JsonResponseFactory service is invalid.');
                    }

                    if (!$throttle instanceof LoginThrottleInterface) {
                        throw new LogicException('LoginThrottleInterface service is invalid.');
                    }

                    return new LoginHandler($useCase, $json, $throttle);
                },
            )
            ->set(
                InvalidCredentialsExceptionHandler::class,
                static function (ContainerInterface $c): InvalidCredentialsExceptionHandler {
                    $pd = $c->get(ProblemDetailsResponseFactory::class);

                    if (!$pd instanceof ProblemDetailsResponseFactory) {
                        throw new LogicException('ProblemDetailsResponseFactory service is invalid.');
                    }

                    return new InvalidCredentialsExceptionHandler($pd);
                },
            )
            ->set(
                TooManyLoginAttemptsExceptionHandler::class,
                static function (ContainerInterface $c): TooManyLoginAttemptsExceptionHandler {
                    $pd = $c->get(ProblemDetailsResponseFactory::class);

                    if (!$pd instanceof ProblemDetailsResponseFactory) {
                        throw new LogicException('ProblemDetailsResponseFactory service is invalid.');
                    }

                    return new TooManyLoginAttemptsExceptionHandler($pd);
                },
            )
            ->set(
                'nene-vault.route_registrar.auth',
                static function (ContainerInterface $c): AuthRouteRegistrar {
                    $handler = $c->get(LoginHandler::class);

                    if (!$handler instanceof LoginHandler) {
                        throw new LogicException('LoginHandler service is invalid.');
                    }

                    return new AuthRouteRegistrar($handler);
                },
            );
    }
}
