<?php

declare(strict_types=1);

namespace NeneVault\DocumentVersion;

use RuntimeException;

/**
 * S3-compatible document storage adapter (Phase 4, ADR 0012).
 *
 * Supports AWS S3, MinIO, Backblaze B2, DigitalOcean Spaces, and any
 * S3-compatible service via path-style or virtual-hosted-style URLs.
 *
 * Authentication uses AWS Signature Version 4 implemented inline — no
 * external SDK dependency.
 *
 * Configure via environment:
 *   NENE_VAULT_STORAGE_ADAPTER=s3
 *   NENE_VAULT_S3_ENDPOINT=https://s3.amazonaws.com       (or MinIO URL)
 *   NENE_VAULT_S3_REGION=ap-northeast-1
 *   NENE_VAULT_S3_BUCKET=my-vault-bucket
 *   NENE_VAULT_S3_ACCESS_KEY=AKIAIOSFODNN7EXAMPLE
 *   NENE_VAULT_S3_SECRET_KEY=wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY
 *   NENE_VAULT_S3_PREFIX=vault                            (optional)
 *   NENE_VAULT_S3_PATH_STYLE=true                         (for MinIO / non-AWS)
 */
final readonly class S3DocumentStorage implements DocumentStorageInterface
{
    private const SERVICE = 's3';

    public function __construct(
        private S3DocumentStorageConfig $config,
    ) {
    }

    public function store(
        string $sourceTmpPath,
        int $organizationId,
        string $documentId,
        int $versionNumber,
        string $originalFilename,
    ): string {
        $sanitized = $this->sanitizeFilename($originalFilename);
        $relativePath = sprintf('vault/%d/%s/v%d/%s', $organizationId, $documentId, $versionNumber, $sanitized);
        $key = $this->toKey($relativePath);

        $contents = file_get_contents($sourceTmpPath);
        assert($contents !== false);

        $contentType = mime_content_type($sourceTmpPath) ?: 'application/octet-stream';
        $contentSha256 = hash('sha256', $contents);

        $this->s3Put($key, $contents, $contentType, $contentSha256);

        return $relativePath;
    }

    public function resolveAbsolutePath(string $relativePath): string
    {
        return $this->config->objectUrl($this->toKey($relativePath));
    }

    public function exists(string $relativePath): bool
    {
        $key = $this->toKey($relativePath);
        $status = $this->s3Head($key);

        return $status === 200;
    }

    public function readContents(string $relativePath): string
    {
        $key = $this->toKey($relativePath);

        return $this->s3Get($key);
    }

    public function sha256(string $absolutePath): string
    {
        $hash = hash_file('sha256', $absolutePath);

        if ($hash === false) {
            throw new RuntimeException('Failed to compute SHA-256 for: ' . $absolutePath);
        }

        return $hash;
    }

    // ── S3 HTTP operations ────────────────────────────────────────────────────

    private function s3Put(string $key, string $body, string $contentType, string $contentSha256): void
    {
        $url = $this->config->objectUrl($key);
        $date = $this->now();
        $headers = [
            'Content-Type'        => $contentType,
            'Content-Length'      => (string) strlen($body),
            'x-amz-content-sha256' => $contentSha256,
            'x-amz-date'          => $date['datetime'],
            'Host'                => $this->host(),
        ];
        $headers['Authorization'] = $this->sigV4Auth('PUT', $key, [], $headers, $contentSha256, $date);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $this->curlHeaders($headers),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
        ]);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException(sprintf('S3 PUT failed with status %d for key "%s".', $status, $key));
        }
    }

    private function s3Head(string $key): int
    {
        $url = $this->config->objectUrl($key);
        $date = $this->now();
        $emptyHash = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';
        $headers = [
            'x-amz-content-sha256' => $emptyHash,
            'x-amz-date'           => $date['datetime'],
            'Host'                 => $this->host(),
        ];
        $headers['Authorization'] = $this->sigV4Auth('HEAD', $key, [], $headers, $emptyHash, $date);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY         => true,
            CURLOPT_HTTPHEADER     => $this->curlHeaders($headers),
            CURLOPT_RETURNTRANSFER => true,
        ]);

        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $status;
    }

    private function s3Get(string $key): string
    {
        $url = $this->config->objectUrl($key);
        $date = $this->now();
        $emptyHash = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';
        $headers = [
            'x-amz-content-sha256' => $emptyHash,
            'x-amz-date'           => $date['datetime'],
            'Host'                 => $this->host(),
        ];
        $headers['Authorization'] = $this->sigV4Auth('GET', $key, [], $headers, $emptyHash, $date);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => $this->curlHeaders($headers),
            CURLOPT_RETURNTRANSFER => true,
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!is_string($body) || $status < 200 || $status >= 300) {
            throw new RuntimeException(sprintf('S3 GET failed with status %d for key "%s".', $status, $key));
        }

        return $body;
    }

    // ── AWS Signature Version 4 ───────────────────────────────────────────────

    /**
     * @param array<string, string> $queryParams
     * @param array<string, string> $headers
     * @param array{date: string, datetime: string} $date
     */
    private function sigV4Auth(
        string $method,
        string $key,
        array $queryParams,
        array $headers,
        string $payloadHash,
        array $date,
    ): string {
        $canonicalHeaders = $this->canonicalHeaders($headers);
        $signedHeaders = $this->signedHeaderNames($headers);

        $canonicalRequest = implode("\n", [
            $method,
            '/' . ltrim($key, '/'),
            $this->canonicalQueryString($queryParams),
            $canonicalHeaders,
            $signedHeaders,
            $payloadHash,
        ]);

        $scope = implode('/', [$date['date'], $this->config->region, self::SERVICE, 'aws4_request']);
        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $date['datetime'],
            $scope,
            hash('sha256', $canonicalRequest),
        ]);

        $signingKey = $this->signingKey($date['date']);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        return sprintf(
            'AWS4-HMAC-SHA256 Credential=%s/%s,SignedHeaders=%s,Signature=%s',
            $this->config->accessKey,
            $scope,
            $signedHeaders,
            $signature,
        );
    }

    /** @param array<string, string> $headers */
    private function canonicalHeaders(array $headers): string
    {
        $lower = [];

        foreach ($headers as $name => $value) {
            $lower[strtolower($name)] = trim($value);
        }

        ksort($lower);
        $lines = [];

        foreach ($lower as $name => $value) {
            $lines[] = $name . ':' . $value;
        }

        return implode("\n", $lines) . "\n";
    }

    /** @param array<string, string> $headers */
    private function signedHeaderNames(array $headers): string
    {
        $names = array_map('strtolower', array_keys($headers));
        sort($names);

        return implode(';', $names);
    }

    /** @param array<string, string> $params */
    private function canonicalQueryString(array $params): string
    {
        if ($params === []) {
            return '';
        }

        ksort($params);
        $parts = [];

        foreach ($params as $k => $v) {
            $parts[] = rawurlencode($k) . '=' . rawurlencode($v);
        }

        return implode('&', $parts);
    }

    private function signingKey(string $date): string
    {
        $kDate    = hash_hmac('sha256', $date, 'AWS4' . $this->config->secretKey, true);
        $kRegion  = hash_hmac('sha256', $this->config->region, $kDate, true);
        $kService = hash_hmac('sha256', self::SERVICE, $kRegion, true);

        return hash_hmac('sha256', 'aws4_request', $kService, true);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function toKey(string $relativePath): string
    {
        return $this->config->prefix !== ''
            ? $this->config->prefix . '/' . $relativePath
            : $relativePath;
    }

    private function host(): string
    {
        $parsed = parse_url($this->config->endpoint, PHP_URL_HOST);
        $host = is_string($parsed) ? $parsed : '';
        $port = parse_url($this->config->endpoint, PHP_URL_PORT);

        if ($this->config->pathStyle) {
            return $port !== null ? $host . ':' . $port : $host;
        }

        $bucketHost = $this->config->bucket . '.' . $host;

        return $port !== null ? $bucketHost . ':' . $port : $bucketHost;
    }

    /** @return array{date: string, datetime: string} */
    private function now(): array
    {
        $ts = gmdate('Ymd\THis\Z');

        return [
            'date'     => substr($ts, 0, 8),
            'datetime' => $ts,
        ];
    }

    /**
     * @param array<string, string> $headers
     * @return list<string>
     */
    private function curlHeaders(array $headers): array
    {
        $result = [];

        foreach ($headers as $name => $value) {
            $result[] = $name . ': ' . $value;
        }

        return $result;
    }

    private function sanitizeFilename(string $filename): string
    {
        $base = basename($filename);
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $base) ?? 'upload';
        $safe = trim($safe, '.');

        return $safe === '' ? 'upload' : $safe;
    }
}
