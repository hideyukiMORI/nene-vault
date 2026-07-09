<?php

declare(strict_types=1);

namespace NeneVault\Tests\Http;

use NeneVault\Http\AuthorizationHeaderFallback;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class AuthorizationHeaderFallbackTest extends TestCase
{
    private function request(): ServerRequestInterface
    {
        return (new Psr17Factory())->createServerRequest('GET', '/admin/documents');
    }

    public function test_standard_header_wins_over_the_mirror(): void
    {
        $request = $this->request()
            ->withHeader('Authorization', 'Bearer standard')
            ->withHeader('X-Authorization', 'Bearer mirror');

        self::assertSame('Bearer standard', AuthorizationHeaderFallback::apply($request)->getHeaderLine('Authorization'));
    }

    public function test_mirror_is_adopted_when_standard_header_is_absent(): void
    {
        $request = $this->request()->withHeader('X-Authorization', 'Bearer mirror');

        self::assertSame('Bearer mirror', AuthorizationHeaderFallback::apply($request)->getHeaderLine('Authorization'));
    }

    public function test_no_headers_leaves_the_request_unchanged(): void
    {
        self::assertFalse(AuthorizationHeaderFallback::apply($this->request())->hasHeader('Authorization'));
    }
}
