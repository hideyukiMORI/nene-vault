<?php

declare(strict_types=1);

namespace NeneVault\DocumentVersion;

final readonly class S3DocumentStorageConfig
{
    public function __construct(
        public string $endpoint,
        public string $region,
        public string $bucket,
        public string $accessKey,
        public string $secretKey,
        public string $prefix = '',
        public bool $pathStyle = false,
    ) {
    }

    /** Base URL for object URLs (informational; never exposed in API responses). */
    public function baseUrl(): string
    {
        if ($this->pathStyle) {
            return rtrim($this->endpoint, '/') . '/' . $this->bucket;
        }

        $host = parse_url($this->endpoint, PHP_URL_HOST) ?? $this->endpoint;
        $scheme = parse_url($this->endpoint, PHP_URL_SCHEME) ?? 'https';
        $port = parse_url($this->endpoint, PHP_URL_PORT);

        $portSuffix = $port !== null ? ':' . $port : '';

        return $scheme . '://' . $this->bucket . '.' . $host . $portSuffix;
    }

    /** Resolve the S3 request URL for a given key. */
    public function objectUrl(string $key): string
    {
        if ($this->pathStyle) {
            return rtrim($this->endpoint, '/') . '/' . $this->bucket . '/' . ltrim($key, '/');
        }

        return $this->baseUrl() . '/' . ltrim($key, '/');
    }
}
