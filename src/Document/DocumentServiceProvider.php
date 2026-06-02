<?php

declare(strict_types=1);

namespace NeneVault\Document;

use Closure;
use LogicException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use NeneVault\Audit\AuditEventRepositoryInterface;
use NeneVault\Audit\AuditRecorder;
use NeneVault\Audit\AuditRecorderInterface;
use NeneVault\Audit\PdoAuditEventRepository;
use NeneVault\DocumentVersion\DocumentStorageInterface;
use NeneVault\DocumentVersion\DocumentVersionRepositoryInterface;
use NeneVault\DocumentVersion\LocalFilesystemDocumentStorage;
use NeneVault\DocumentVersion\PdoDocumentVersionRepository;
use NeneVault\DocumentVersion\S3DocumentStorage;
use NeneVault\DocumentVersion\S3DocumentStorageConfig;
use NeneVault\VaultSettings\PdoVaultSettingsRepository;
use NeneVault\VaultSettings\VaultSettingsRepositoryInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final readonly class DocumentServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                DocumentStorageInterface::class,
                static function (): DocumentStorageInterface {
                    $adapter = (string) (getenv('NENE_VAULT_STORAGE_ADAPTER') ?: 'local');

                    if ($adapter === 's3') {
                        return new S3DocumentStorage(new S3DocumentStorageConfig(
                            endpoint:  (string) (getenv('NENE_VAULT_S3_ENDPOINT') ?: 'https://s3.amazonaws.com'),
                            region:    (string) (getenv('NENE_VAULT_S3_REGION') ?: 'us-east-1'),
                            bucket:    (string) (getenv('NENE_VAULT_S3_BUCKET') ?: ''),
                            accessKey: (string) (getenv('NENE_VAULT_S3_ACCESS_KEY') ?: ''),
                            secretKey: (string) (getenv('NENE_VAULT_S3_SECRET_KEY') ?: ''),
                            prefix:    (string) (getenv('NENE_VAULT_S3_PREFIX') ?: ''),
                            pathStyle: (getenv('NENE_VAULT_S3_PATH_STYLE') === 'true'),
                        ));
                    }

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
                    $storage = $c->get(DocumentStorageInterface::class);

                    if (!$storage instanceof DocumentStorageInterface) {
                        throw new LogicException('DocumentStorageInterface service is invalid.');
                    }

                    $maxMb = (int) (getenv('NENE_VAULT_MAX_FILE_SIZE_MB') ?: 20);

                    return new UploadDocumentUseCase(
                        self::tx($c),
                        self::documentRepositoryFactory(),
                        self::versionRepositoryFactory(),
                        $storage,
                        self::settingsRepositoryFactory(),
                        self::auditRecorderFactory(),
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
                        self::tx($c),
                        self::documentRepositoryFactory(),
                        self::versionRepositoryFactory(),
                        self::auditRecorderFactory(),
                    );
                },
            )
            ->set(
                VoidDocumentUseCaseInterface::class,
                static function (ContainerInterface $c): VoidDocumentUseCaseInterface {
                    return new VoidDocumentUseCase(
                        self::tx($c),
                        self::documentRepositoryFactory(),
                        self::versionRepositoryFactory(),
                        self::auditRecorderFactory(),
                    );
                },
            )
            ->set(
                RestoreDocumentUseCaseInterface::class,
                static function (ContainerInterface $c): RestoreDocumentUseCaseInterface {
                    return new RestoreDocumentUseCase(
                        self::tx($c),
                        self::documentRepositoryFactory(),
                        self::versionRepositoryFactory(),
                        self::auditRecorderFactory(),
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
                DownloadDocumentVersionUseCaseInterface::class,
                static function (ContainerInterface $c): DownloadDocumentVersionUseCaseInterface {
                    $storage = $c->get(DocumentStorageInterface::class);

                    if (!$storage instanceof DocumentStorageInterface) {
                        throw new LogicException('DocumentStorageInterface service is invalid.');
                    }

                    return new DownloadDocumentVersionUseCase(self::versionRepo($c), $storage);
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
                DownloadDocumentVersionHandler::class,
                static function (ContainerInterface $c): DownloadDocumentVersionHandler {
                    $uc = $c->get(DownloadDocumentVersionUseCaseInterface::class);
                    $responseFactory = $c->get(ResponseFactoryInterface::class);
                    $streamFactory = $c->get(StreamFactoryInterface::class);

                    if (!$uc instanceof DownloadDocumentVersionUseCaseInterface) {
                        throw new LogicException('DownloadDocumentVersionUseCaseInterface service is invalid.');
                    }

                    if (!$responseFactory instanceof ResponseFactoryInterface) {
                        throw new LogicException('ResponseFactoryInterface service is invalid.');
                    }

                    if (!$streamFactory instanceof StreamFactoryInterface) {
                        throw new LogicException('StreamFactoryInterface service is invalid.');
                    }

                    return new DownloadDocumentVersionHandler($uc, $responseFactory, $streamFactory);
                },
            )
            ->set(
                FileIntegrityExceptionHandler::class,
                static function (ContainerInterface $c): FileIntegrityExceptionHandler {
                    $pd = $c->get(ProblemDetailsResponseFactory::class);

                    if (!$pd instanceof ProblemDetailsResponseFactory) {
                        throw new LogicException('ProblemDetailsResponseFactory service is invalid.');
                    }

                    return new FileIntegrityExceptionHandler($pd);
                },
            )
            ->set(
                InvalidDocumentStateExceptionHandler::class,
                static function (ContainerInterface $c): InvalidDocumentStateExceptionHandler {
                    $pd = $c->get(ProblemDetailsResponseFactory::class);

                    if (!$pd instanceof ProblemDetailsResponseFactory) {
                        throw new LogicException('ProblemDetailsResponseFactory service is invalid.');
                    }

                    return new InvalidDocumentStateExceptionHandler($pd);
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
                    $download = $c->get(DownloadDocumentVersionHandler::class);

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

                    if (!$download instanceof DownloadDocumentVersionHandler) {
                        throw new LogicException('DownloadDocumentVersionHandler service is invalid.');
                    }

                    return new DocumentRouteRegistrar($upload, $search, $get, $updateMetadata, $void, $restore, $history, $download);
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

    private static function tx(ContainerInterface $c): DatabaseTransactionManagerInterface
    {
        $t = $c->get(DatabaseTransactionManagerInterface::class);

        if (!$t instanceof DatabaseTransactionManagerInterface) {
            throw new LogicException('DatabaseTransactionManagerInterface service is invalid.');
        }

        return $t;
    }

    /** @return Closure(DatabaseQueryExecutorInterface): VaultDocumentRepositoryInterface */
    private static function documentRepositoryFactory(): Closure
    {
        return static fn (DatabaseQueryExecutorInterface $e): VaultDocumentRepositoryInterface => new PdoVaultDocumentRepository($e);
    }

    /** @return Closure(DatabaseQueryExecutorInterface): DocumentVersionRepositoryInterface */
    private static function versionRepositoryFactory(): Closure
    {
        return static fn (DatabaseQueryExecutorInterface $e): DocumentVersionRepositoryInterface => new PdoDocumentVersionRepository($e);
    }

    /** @return Closure(DatabaseQueryExecutorInterface): VaultSettingsRepositoryInterface */
    private static function settingsRepositoryFactory(): Closure
    {
        return static fn (DatabaseQueryExecutorInterface $e): VaultSettingsRepositoryInterface => new PdoVaultSettingsRepository($e);
    }

    /** @return Closure(DatabaseQueryExecutorInterface): AuditRecorderInterface */
    private static function auditRecorderFactory(): Closure
    {
        return static fn (DatabaseQueryExecutorInterface $e): AuditRecorderInterface => new AuditRecorder(new PdoAuditEventRepository($e));
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
