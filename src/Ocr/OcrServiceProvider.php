<?php

declare(strict_types=1);

namespace NeneVault\Ocr;

use LogicException;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use NeneVault\Document\VaultDocumentRepositoryInterface;
use NeneVault\DocumentVersion\DocumentStorageInterface;
use NeneVault\DocumentVersion\DocumentVersionRepositoryInterface;
use Psr\Container\ContainerInterface;

final readonly class OcrServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                OcrExtractorInterface::class,
                static function (): OcrExtractorInterface {
                    $binary = (string) (getenv('NENE_VAULT_OCR_BINARY') ?: 'tesseract');
                    $lang = (string) (getenv('NENE_VAULT_OCR_LANG') ?: 'jpn+eng');

                    return new TesseractOcrExtractor($binary, $lang);
                },
            )
            ->set(
                OcrMetadataExtractor::class,
                static fn (): OcrMetadataExtractor => new OcrMetadataExtractor(),
            )
            ->set(
                OcrSuggestUseCaseInterface::class,
                static function (ContainerInterface $c): OcrSuggestUseCaseInterface {
                    $docs = $c->get(VaultDocumentRepositoryInterface::class);
                    $versions = $c->get(DocumentVersionRepositoryInterface::class);
                    $storage = $c->get(DocumentStorageInterface::class);
                    $ocr = $c->get(OcrExtractorInterface::class);
                    $extractor = $c->get(OcrMetadataExtractor::class);

                    if (!$docs instanceof VaultDocumentRepositoryInterface) {
                        throw new LogicException('VaultDocumentRepositoryInterface service is invalid.');
                    }

                    if (!$versions instanceof DocumentVersionRepositoryInterface) {
                        throw new LogicException('DocumentVersionRepositoryInterface service is invalid.');
                    }

                    if (!$storage instanceof DocumentStorageInterface) {
                        throw new LogicException('DocumentStorageInterface service is invalid.');
                    }

                    if (!$ocr instanceof OcrExtractorInterface) {
                        throw new LogicException('OcrExtractorInterface service is invalid.');
                    }

                    if (!$extractor instanceof OcrMetadataExtractor) {
                        throw new LogicException('OcrMetadataExtractor service is invalid.');
                    }

                    return new OcrSuggestUseCase($docs, $versions, $storage, $ocr, $extractor);
                },
            )
            ->set(
                OcrSuggestHandler::class,
                static function (ContainerInterface $c): OcrSuggestHandler {
                    $uc = $c->get(OcrSuggestUseCaseInterface::class);
                    $json = $c->get(JsonResponseFactory::class);

                    if (!$uc instanceof OcrSuggestUseCaseInterface) {
                        throw new LogicException('OcrSuggestUseCaseInterface service is invalid.');
                    }

                    if (!$json instanceof JsonResponseFactory) {
                        throw new LogicException('JsonResponseFactory service is invalid.');
                    }

                    return new OcrSuggestHandler($uc, $json);
                },
            )
            ->set(
                OcrRouteRegistrar::class,
                static function (ContainerInterface $c): OcrRouteRegistrar {
                    $h = $c->get(OcrSuggestHandler::class);

                    if (!$h instanceof OcrSuggestHandler) {
                        throw new LogicException('OcrSuggestHandler service is invalid.');
                    }

                    return new OcrRouteRegistrar($h);
                },
            )
            ->set(
                OcrExceptionHandler::class,
                static function (ContainerInterface $c): OcrExceptionHandler {
                    $pd = $c->get(ProblemDetailsResponseFactory::class);

                    if (!$pd instanceof ProblemDetailsResponseFactory) {
                        throw new LogicException('ProblemDetailsResponseFactory service is invalid.');
                    }

                    return new OcrExceptionHandler($pd);
                },
            );
    }
}
