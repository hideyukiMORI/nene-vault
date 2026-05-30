<?php

declare(strict_types=1);

namespace NeneVault\Document;

use LogicException;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
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
                    $get = $c->get(GetDocumentByIdHandler::class);

                    if (!$upload instanceof UploadDocumentHandler) {
                        throw new LogicException('UploadDocumentHandler service is invalid.');
                    }

                    if (!$get instanceof GetDocumentByIdHandler) {
                        throw new LogicException('GetDocumentByIdHandler service is invalid.');
                    }

                    return new DocumentRouteRegistrar($upload, $get);
                },
            );
    }
}
