<?php

declare(strict_types=1);

namespace NeneVault\Export;

use LogicException;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use NeneVault\Audit\AuditRecorderInterface;
use NeneVault\Document\VaultDocumentRepositoryInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final readonly class ExportServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                ExportDocumentsUseCaseInterface::class,
                static function (ContainerInterface $c): ExportDocumentsUseCaseInterface {
                    $documents = $c->get(VaultDocumentRepositoryInterface::class);
                    $audit = $c->get(AuditRecorderInterface::class);

                    if (!$documents instanceof VaultDocumentRepositoryInterface) {
                        throw new LogicException('VaultDocumentRepositoryInterface service is invalid.');
                    }

                    if (!$audit instanceof AuditRecorderInterface) {
                        throw new LogicException('AuditRecorderInterface service is invalid.');
                    }

                    return new ExportDocumentsUseCase($documents, $audit);
                },
            )
            ->set(
                ExportDocumentsHandler::class,
                static function (ContainerInterface $c): ExportDocumentsHandler {
                    $uc = $c->get(ExportDocumentsUseCaseInterface::class);
                    $rf = $c->get(ResponseFactoryInterface::class);
                    $sf = $c->get(StreamFactoryInterface::class);

                    if (!$uc instanceof ExportDocumentsUseCaseInterface) {
                        throw new LogicException('ExportDocumentsUseCaseInterface service is invalid.');
                    }

                    if (!$rf instanceof ResponseFactoryInterface) {
                        throw new LogicException('ResponseFactoryInterface service is invalid.');
                    }

                    if (!$sf instanceof StreamFactoryInterface) {
                        throw new LogicException('StreamFactoryInterface service is invalid.');
                    }

                    return new ExportDocumentsHandler($uc, $rf, $sf);
                },
            )
            ->set(
                ExportRouteRegistrar::class,
                static function (ContainerInterface $c): ExportRouteRegistrar {
                    $h = $c->get(ExportDocumentsHandler::class);

                    if (!$h instanceof ExportDocumentsHandler) {
                        throw new LogicException('ExportDocumentsHandler service is invalid.');
                    }

                    return new ExportRouteRegistrar($h);
                },
            );
    }
}
