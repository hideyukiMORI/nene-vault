<?php

declare(strict_types=1);

namespace NeneVault\Tests\DocumentVersion;

use NeneVault\DocumentVersion\S3DocumentStorage;
use NeneVault\DocumentVersion\S3DocumentStorageConfig;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Unit tests for S3DocumentStorage helper methods.
 *
 * Integration tests (actual S3 PUT/GET/HEAD) are skipped unless
 * NENE_VAULT_S3_BUCKET is set in the environment.
 */
final class S3DocumentStorageTest extends TestCase
{
    private function config(bool $pathStyle = false): S3DocumentStorageConfig
    {
        return new S3DocumentStorageConfig(
            endpoint:  'https://s3.amazonaws.com',
            region:    'ap-northeast-1',
            bucket:    'test-bucket',
            accessKey: 'AKIAIOSFODNN7EXAMPLE',
            secretKey: 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
            prefix:    'vault',
            pathStyle: $pathStyle,
        );
    }

    private function storage(bool $pathStyle = false): S3DocumentStorage
    {
        return new S3DocumentStorage($this->config($pathStyle));
    }

    // ── S3DocumentStorageConfig ───────────────────────────────────────────────

    public function test_config_virtual_hosted_base_url(): void
    {
        $cfg = $this->config();
        $this->assertSame('https://test-bucket.s3.amazonaws.com', $cfg->baseUrl());
    }

    public function test_config_path_style_base_url(): void
    {
        $cfg = new S3DocumentStorageConfig(
            endpoint:  'http://minio:9000',
            region:    'us-east-1',
            bucket:    'my-bucket',
            accessKey: 'key',
            secretKey: 'secret',
            pathStyle: true,
        );
        $this->assertSame('http://minio:9000/my-bucket', $cfg->baseUrl());
    }

    public function test_config_object_url_virtual_hosted(): void
    {
        $cfg = $this->config();
        $this->assertSame(
            'https://test-bucket.s3.amazonaws.com/vault/my-key.pdf',
            $cfg->objectUrl('vault/my-key.pdf'),
        );
    }

    public function test_config_object_url_path_style(): void
    {
        $cfg = new S3DocumentStorageConfig(
            endpoint:  'http://localhost:9000',
            region:    'us-east-1',
            bucket:    'vault',
            accessKey: 'key',
            secretKey: 'secret',
            pathStyle: true,
        );
        $this->assertSame(
            'http://localhost:9000/vault/org/doc/v1/file.pdf',
            $cfg->objectUrl('org/doc/v1/file.pdf'),
        );
    }

    // ── Path helpers ──────────────────────────────────────────────────────────

    public function test_resolve_absolute_path_returns_url(): void
    {
        $storage = $this->storage();
        $url = $storage->resolveAbsolutePath('vault/1/abc/v1/invoice.pdf');
        $this->assertStringStartsWith('https://', $url);
        $this->assertStringContainsString('invoice.pdf', $url);
    }

    // ── Signature V4 helpers (via reflection) ─────────────────────────────────

    public function test_canonical_headers_sorted_lowercased(): void
    {
        $storage = $this->storage();
        $method = new ReflectionMethod($storage, 'canonicalHeaders');
        $result = $method->invoke($storage, [
            'Host'                => 'bucket.s3.amazonaws.com',
            'x-amz-date'         => '20260531T120000Z',
            'Content-Type'       => 'application/pdf',
            'x-amz-content-sha256' => 'abc123',
        ]);

        $lines = explode("\n", rtrim($result));
        $names = array_map(static fn ($l) => explode(':', $l)[0], $lines);

        $this->assertSame($names, array_values(array_unique($names)), 'Header names must be unique');
        $this->assertSame($names, array_map('strtolower', $names), 'Header names must be lowercase');

        $sorted = $names;
        sort($sorted);
        $this->assertSame($sorted, $names, 'Headers must be sorted');
    }

    public function test_canonical_query_string_sorted(): void
    {
        $storage = $this->storage();
        $method = new ReflectionMethod($storage, 'canonicalQueryString');
        $result = $method->invoke($storage, ['z' => '3', 'a' => '1', 'm' => '2']);
        $this->assertSame('a=1&m=2&z=3', $result);
    }

    public function test_canonical_query_string_empty(): void
    {
        $storage = $this->storage();
        $method = new ReflectionMethod($storage, 'canonicalQueryString');
        $result = $method->invoke($storage, []);
        $this->assertSame('', $result);
    }

    public function test_signing_key_is_binary(): void
    {
        $storage = $this->storage();
        $method = new ReflectionMethod($storage, 'signingKey');
        $key = $method->invoke($storage, '20260531');
        $this->assertSame(32, strlen($key), 'HMAC-SHA256 binary key must be 32 bytes');
    }

    // ── sha256 on temp file ───────────────────────────────────────────────────

    public function test_sha256_of_temp_file(): void
    {
        $storage = $this->storage();
        $tmp = tempnam(sys_get_temp_dir(), 's3_test_');
        assert($tmp !== false);
        file_put_contents($tmp, 'hello world');

        $hash = $storage->sha256($tmp);
        $this->assertSame(
            'b94d27b9934d3e08a52e52d7da7dabfac484efe37a5380ee9088f7ace2efcde9',
            $hash,
        );
        @unlink($tmp);
    }
}
