<?php

declare(strict_types=1);

namespace NeneVault\Document;

use LogicException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use NeneVault\Audit\AuditEventRepositoryInterface;
use NeneVault\Audit\AuditRecorderInterface;
use NeneVault\DocumentVersion\DocumentStorageInterface;
use NeneVault\DocumentVersion\DocumentVersionRepositoryInterface;
use NeneVault\DocumentVersion\LocalFilesystemDocumentStorage;
use NeneVault\DocumentVersion\PdoDocumentVersionRepository;
use NeneVault\VaultSettings\VaultSettingsRepositoryInterface;
use Psr\Container\ContainerInterface;

final readonly class DocumentServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                DocumentStorageInterface::class,
                static function (): DocumentStorageInterface {
                    $root = (string) (getenv('NENE_VAULT_STORAGE_PATH') ?: 'storage/vault');

                    if (!str_starts_with($root, '/')) {
                        $root = dirname(__DIR__, 2) . '/' . $root;
                    }

                    return new LocalFilesystemDocumentStorage($root);
                },
            )
            ->set(
                DocumentVersionRepositoryInterface::class,
                static function (ContainerInterface $c): DocumentVersionRepositoryInterface {
                    $query = $c->get(DatabaseQueryExecutorInterface::class);

                    if (!$query instanceof DatabaseQueryExecutorInterface) {
                        throw new LogicException('DatabaseQueryExecutorInterface service is invalid.');
                    }

                    return new PdoDocumentVersionRepository($query);
                },
            )
            ->set(
                VaultDocumentRepositoryInterface::class,
                static function (ContainerInterface $c): VaultDocumentRepositoryInterface {
                    $query = $c->get(DatabaseQueryExecutorInterface::class);

                    if (!$query instanceof DatabaseQueryExecutorInterface) {
                        throw new LogicException('DatabaseQueryExecutorInterface service is invalid.');
                    }

                    return new PdoVaultDocumentRepository($query);
                },
            )
            ->set(
                UploadDocumentUseCaseInterface::class,
                static function (ContainerInterface $c): UploadDocumentUseCaseInterface {
                    $documents = $c->get(VaultDocumentRepositoryInterface::class);
                    $versions = $c->get(DocumentVersionRepositoryInterface::class);
                    $storage = $c->get(DocumentStorageInterface::class);
                    $settings = $c->get(VaultSettingsRepositoryInterface::class);
                    $audit = $c->get(AuditRecorderInterface::class);

                    if (!$documents instanceof VaultDocumentRepositoryInterface) {
                        throw new LogicException('VaultDocumentRepositoryInterface service is invalid.');
                    }

                    if (!$versions instanceof DocumentVersionRepositoryInterface) {
                        throw new LogicException('DocumentVersionRepositoryInterface service is invalid.');
                    }

                    if (!$storage instanceof DocumentStorageInterface) {
                        throw new LogicException('DocumentStorageInterface service is invalid.');
                    }

                    if (!$settings instanceof VaultSettingsRepositoryInterface) {
                        throw new LogicException('VaultSettingsRepositoryInterface service is invalid.');
                    }

                    if (!$audit instanceof AuditRecorderInterface) {
                        throw new LogicException('AuditRecorderInterface service is invalid.');
                    }

                    $maxMb = (int) (getenv('NENE_VAULT_MAX_FILE_SIZE_MB') ?: 20);

                    return new UploadDocumentUseCase(
                        $documents,
                        $versions,
                        $storage,
                        $settings,
                        $audit,
                        $maxMb * 1024 * 1024,
                    );
                },
            )
            ->set(
                GetDocumentByIdUseCaseInterface::class,
                static function (ContainerInterface $c): GetDocumentByIdUseCaseInterface {
                    $documents = $c->get(VaultDocumentRepositoryInterface::class);
                    $versions = $c->get(DocumentVersionRepositoryInterface::class);

                    if (!$documents instanceof VaultDocumentRepositoryInterface) {
                        throw new LogicException('VaultDocumentRepositoryInterface service is invalid.');
                    }

                    if (!$versions instanceof DocumentVersionRepositoryInterface) {
                        throw new LogicException('DocumentVersionRepositoryInterface service is invalid.');
                    }

                    return new GetDocumentByIdUseCase($documents, $versions);
                },
            )
            ->set(
                SearchDocumentsUseCaseInterface::class,
                static function (ContainerInterface $c): SearchDocumentsUseCaseInterface {
                    $documents = $c->get(VaultDocumentRepositoryInterface::class);

                    if (!$documents instanceof VaultDocumentRepositoryInterface) {
                        throw new LogicException('VaultDocumentRepositoryInterface service is invalid.');
                    }

                    return new SearchDocumentsUseCase($documents);
                },
            )
            ->set(
                UpdateDocumentMetadataUseCaseInterface::class,
                static function (ContainerInterface $c): UpdateDocumentMetadataUseCaseInterface {
                    return new UpdateDocumentMetadataUseCase(
                        self::repo($c),
                        self::versionRepo($c),
                        self::audit($c),
                    );
                },
            )
            ->set(
                VoidDocumentUseCaseInterface::class,
                static function (ContainerInterface $c): VoidDocumentUseCaseInterface {
                    return new VoidDocumentUseCase(
                        self::repo($c),
                        self::versionRepo($c),
                        self::audit($c),
                    );
                },
            )
            ->set(
                RestoreDocumentUseCaseInterface::class,
                static function (ContainerInterface $c): RestoreDocumentUseCaseInterface {
                    return new RestoreDocumentUseCase(
                        self::repo($c),
                        self::versionRepo($c),
                        self::audit($c),
                    );
                },
            )
            ->set(
                GetDocumentHistoryUseCaseInterface::class,
                static function (ContainerInterface $c): GetDocumentHistoryUseCaseInterface {
                    $auditEvents = $c->get(AuditEventRepositoryInterface::class);

                    if (!$auditEvents instanceof AuditEventRepositoryInterface) {
                        throw new LogicException('AuditEventRepositoryInterface service is invalid.');
                    }

                    return new GetDocumentHistoryUseCase(
                        self::repo($c),
                        self::versionRepo($c),
                        $auditEvents,
                    );
                },
            )
            ->set(
                UpdateDocumentMetadataHandler::class,
                static function (ContainerInterface $c): UpdateDocumentMetadataHandler {
                    $uc = $c->get(UpdateDocumentMetadataUseCaseInterface::class);

                    if (!$uc instanceof UpdateDocumentMetadataUseCaseInterface) {
                        throw new LogicException('UpdateDocumentMetadataUseCaseInterface service is invalid.');
                    }

                    return new UpdateDocumentMetadataHandler($uc, self::json($c));
                },
            )
            ->set(
                VoidDocumentHandler::class,
                static function (ContainerInterface $c): VoidDocumentHandler {
                    $uc = $c->get(VoidDocumentUseCaseInterface::class);

                    if (!$uc instanceof VoidDocumentUseCaseInterface) {
                        throw new LogicException('VoidDocumentUseCaseInterface service is invalid.');
                    }

                    return new VoidDocumentHandler($uc, self::json($c));
                },
            )
            ->set(
                RestoreDocumentHandler::class,
                static function (ContainerInterface $c): RestoreDocumentHandler {
                    $uc = $c->get(RestoreDocumentUseCaseInterface::class);

                    if (!$uc instanceof RestoreDocumentUseCaseInterface) {
                        throw new LogicException('RestoreDocumentUseCaseInterface service is invalid.');
                    }

                    return new RestoreDocumentHandler($uc, self::json($c));
                },
            )
            ->set(
                GetDocumentHistoryHandler::class,
                static function (ContainerInterface $c): GetDocumentHistoryHandler {
                    $uc = $c->get(GetDocumentHistoryUseCaseInterface::class);

                    if (!$uc instanceof GetDocumentHistoryUseCaseInterface) {
                        throw new LogicException('GetDocumentHistoryUseCaseInterface service is invalid.');
                    }

                    return new GetDocumentHistoryHandler($uc, self::json($c));
                },
            )
            ->set(
                SearchDocumentsHandler::class,
                static function (ContainerInterface $c): SearchDocumentsHandler {
                    $uc = $c->get(SearchDocumentsUseCaseInterface::class);
                    $json = $c->get(JsonResponseFactory::class);

                    if (!$uc instanceof SearchDocumentsUseCaseInterface) {
                        throw new LogicException('SearchDocumentsUseCaseInterface service is invalid.');
                    }

                    if (!$json instanceof JsonResponseFactory) {
                        throw new LogicException('JsonResponseFactory service is invalid.');
                    }

                    return new SearchDocumentsHandler($uc, $json);
                },
            )
            ->set(
                UploadDocumentHandler::class,
                static function (ContainerInterface $c): UploadDocumentHandler {
                    $uc = $c->get(UploadDocumentUseCaseInterface::class);
                    $json = $c->get(JsonResponseFactory::class);

                    if (!$uc instanceof UploadDocumentUseCaseInterface) {
                        throw new LogicException('UploadDocumentUseCaseInterface service is invalid.');
                    }

                    if (!$json instanceof JsonResponseFactory) {
                        throw new LogicException('JsonResponseFactory service is invalid.');
                    }

                    return new UploadDocumentHandler($uc, $json);
                },
            )
            ->set(
                GetDocumentByIdHandler::class,
                static function (ContainerInterface $c): GetDocumentByIdHandler {
                    $uc = $c->get(GetDocumentByIdUseCaseInterface::class);
                    $json = $c->get(JsonResponseFactory::class);

                    if (!$uc instanceof GetDocumentByIdUseCaseInterface) {
                        throw new LogicException('GetDocumentByIdUseCaseInterface service is invalid.');
                    }

                    if (!$json instanceof JsonResponseFactory) {
                        throw new LogicException('JsonResponseFactory service is invalid.');
                    }

                    return new GetDocumentByIdHandler($uc, $json);
                },
            )
            ->set(
                VaultDocumentNotFoundExceptionHandler::class,
                static function (ContainerInterface $c): VaultDocumentNotFoundExceptionHandler {
                    $pd = $c->get(ProblemDetailsResponseFactory::class);

                    if (!$pd instanceof ProblemDetailsResponseFactory) {
                        throw new LogicException('ProblemDetailsResponseFactory service is invalid.');
                    }

                    return new VaultDocumentNotFoundExceptionHandler($pd);
                },
            )
            ->set(
                DuplicateFileExceptionHandler::class,
                static function (ContainerInterface $c): DuplicateFileExceptionHandler {
                    $pd = $c->get(ProblemDetailsResponseFactory::class);

                    if (!$pd instanceof ProblemDetailsResponseFactory) {
                        throw new LogicException('ProblemDetailsResponseFactory service is invalid.');
                    }

                    return new DuplicateFileExceptionHandler($pd);
                },
            )
            ->set(
                MimeTypeNotAllowedExceptionHandler::class,
                static function (ContainerInterface $c): MimeTypeNotAllowedExceptionHandler {
                    $pd = $c->get(ProblemDetailsResponseFactory::class);

                    if (!$pd instanceof ProblemDetailsResponseFactory) {
                        throw new LogicException('ProblemDetailsResponseFactory service is invalid.');
                    }

                    return new MimeTypeNotAllowedExceptionHandler($pd);
                },
            )
            ->set(
                FileTooLargeExceptionHandler::class,
                static function (ContainerInterface $c): FileTooLargeExceptionHandler {
                    $pd = $c->get(ProblemDetailsResponseFactory::class);

                    if (!$pd instanceof ProblemDetailsResponseFactory) {
                        throw new LogicException('ProblemDetailsResponseFactory service is invalid.');
                    }

                    return new FileTooLargeExceptionHandler($pd);
                },
            )
            ->set(
                DocumentRouteRegistrar::class,
                static function (ContainerInterface $c): DocumentRouteRegistrar {
                    $upload = $c->get(UploadDocumentHandler::class);
                    $search = $c->get(SearchDocumentsHandler::class);
                    $get = $c->get(GetDocumentByIdHandler::class);
                    $updateMetadata = $c->get(UpdateDocumentMetadataHandler::class);
                    $void = $c->get(VoidDocumentHandler::class);
                    $restore = $c->get(RestoreDocumentHandler::class);
                    $history = $c->get(GetDocumentHistoryHandler::class);

                    if (!$upload instanceof UploadDocumentHandler) {
                        throw new LogicException('UploadDocumentHandler service is invalid.');
                    }

                    if (!$search instanceof SearchDocumentsHandler) {
                        throw new LogicException('SearchDocumentsHandler service is invalid.');
                    }

                    if (!$get instanceof GetDocumentByIdHandler) {
                        throw new LogicException('GetDocumentByIdHandler service is invalid.');
                    }

                    if (!$updateMetadata instanceof UpdateDocumentMetadataHandler) {
                        throw new LogicException('UpdateDocumentMetadataHandler service is invalid.');
                    }

                    if (!$void instanceof VoidDocumentHandler) {
                        throw new LogicException('VoidDocumentHandler service is invalid.');
                    }

                    if (!$restore instanceof RestoreDocumentHandler) {
                        throw new LogicException('RestoreDocumentHandler service is invalid.');
                    }

                    if (!$history instanceof GetDocumentHistoryHandler) {
                        throw new LogicException('GetDocumentHistoryHandler service is invalid.');
                    }

                    return new DocumentRouteRegistrar($upload, $search, $get, $updateMetadata, $void, $restore, $history);
                },
            );
    }

    private static function repo(ContainerInterface $c): VaultDocumentRepositoryInterface
    {
        $r = $c->get(VaultDocumentRepositoryInterface::class);

        if (!$r instanceof VaultDocumentRepositoryInterface) {
            throw new LogicException('VaultDocumentRepositoryInterface service is invalid.');
        }

        return $r;
    }

    private static function versionRepo(ContainerInterface $c): DocumentVersionRepositoryInterface
    {
        $r = $c->get(DocumentVersionRepositoryInterface::class);

        if (!$r instanceof DocumentVersionRepositoryInterface) {
            throw new LogicException('DocumentVersionRepositoryInterface service is invalid.');
        }

        return $r;
    }

    private static function audit(ContainerInterface $c): AuditRecorderInterface
    {
        $a = $c->get(AuditRecorderInterface::class);

        if (!$a instanceof AuditRecorderInterface) {
            throw new LogicException('AuditRecorderInterface service is invalid.');
        }

        return $a;
    }

    private static function json(ContainerInterface $c): JsonResponseFactory
    {
        $j = $c->get(JsonResponseFactory::class);

        if (!$j instanceof JsonResponseFactory) {
            throw new LogicException('JsonResponseFactory service is invalid.');
        }

        return $j;
    }
}
