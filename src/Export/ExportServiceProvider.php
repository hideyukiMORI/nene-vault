<?php

declare(strict_types=1);

namespace NeneVault\Export;

use LogicException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use NeneVault\Audit\AuditRecorder;
use NeneVault\Audit\AuditRecorderInterface;
use NeneVault\Audit\PdoAuditEventRepository;
use NeneVault\Document\VaultDocumentRepositoryInterface;
use NeneVault\DocumentVersion\DocumentStorageInterface;
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
                    $storage = $c->get(DocumentStorageInterface::class);
                    $tx = $c->get(DatabaseTransactionManagerInterface::class);

                    if (!$documents instanceof VaultDocumentRepositoryInterface) {
                        throw new LogicException('VaultDocumentRepositoryInterface service is invalid.');
                    }

                    if (!$storage instanceof DocumentStorageInterface) {
                        throw new LogicException('DocumentStorageInterface service is invalid.');
                    }

                    if (!$tx instanceof DatabaseTransactionManagerInterface) {
                        throw new LogicException('DatabaseTransactionManagerInterface service is invalid.');
                    }

                    return new ExportDocumentsUseCase(
                        $documents,
                        $storage,
                        $tx,
                        static fn (DatabaseQueryExecutorInterface $e): AuditRecorderInterface => new AuditRecorder(new PdoAuditEventRepository($e)),
                    );
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
