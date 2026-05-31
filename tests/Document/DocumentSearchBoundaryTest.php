<?php

declare(strict_types=1);

namespace NeneVault\Tests\Document;

use NeneVault\Tests\Support\ApiTestCase;

/**
 * Search boundary tests: each filter individually, combinations, pagination,
 * include_voided, empty results, default limit.
 */
final class DocumentSearchBoundaryTest extends ApiTestCase
{
    private static string $token   = '';
    private static int    $orgId   = 0;
    private static string $_marker = '';
    private static bool   $seeded  = false;

    public static function setUpBeforeClass(): void
    {
        self::bootContainer();
        self::$orgId   = self::ensureOrg('test-org');
        self::$token   = self::issueToken('admin', self::$orgId, userId: 110);
        self::$_marker = 'SrchBnd-' . uniqid();
    }

    protected function setUp(): void
    {
        // Seed a deterministic set once, using instance upload helpers.
        if (!self::$seeded) {
            $h = $this->handler();
            $this->uploadDoc($h, self::$token, self::$_marker . '-Alpha', '2026-02-10', '50000', 'invoice_received');
            $this->uploadDoc($h, self::$token, self::$_marker . '-Beta', '2026-08-20', '75000', 'contract');
            $this->uploadDoc($h, self::$token, self::$_marker . '-Gamma', '2026-11-01', '999', 'receipt');
            self::$seeded = true;
        }
    }

    // ── Counterparty filter ───────────────────────────────────────────────────

    public function test_counterparty_partial_match(): void
    {
        $resp = $this->handler()->handle(
            $this->request('GET', '/admin/vault/documents?counterparty_name=' . urlencode(self::$_marker . '-Alpha'), self::$token),
        );
        $this->assertSame(200, $resp->getStatusCode());
        $body = json_decode((string) $resp->getBody(), true);
        $this->assertGreaterThanOrEqual(1, $body['total']);
        foreach ($body['items'] as $item) {
            $this->assertStringContainsString(self::$_marker . '-Alpha', $item['counterparty_name']);
        }
    }

    public function test_counterparty_no_match_returns_empty(): void
    {
        $resp = $this->handler()->handle(
            $this->request('GET', '/admin/vault/documents?counterparty_name=NORESULT_' . uniqid(), self::$token),
        );
        $this->assertSame(200, $resp->getStatusCode());
        $body = json_decode((string) $resp->getBody(), true);
        $this->assertSame(0, $body['total']);
        $this->assertCount(0, $body['items']);
    }

    // ── Date range filter ─────────────────────────────────────────────────────

    public function test_date_from_filter(): void
    {
        $resp = $this->handler()->handle(
            $this->request(
                'GET',
                '/admin/vault/documents?transaction_date_from=2026-08-01&counterparty_name=' . urlencode(self::$_marker),
                self::$token,
            ),
        );
        $body = json_decode((string) $resp->getBody(), true);
        foreach ($body['items'] as $item) {
            $this->assertGreaterThanOrEqual('2026-08-01', $item['transaction_date']);
        }
    }

    public function test_date_to_filter(): void
    {
        $resp = $this->handler()->handle(
            $this->request(
                'GET',
                '/admin/vault/documents?transaction_date_to=2026-06-30&counterparty_name=' . urlencode(self::$_marker),
                self::$token,
            ),
        );
        $body = json_decode((string) $resp->getBody(), true);
        foreach ($body['items'] as $item) {
            $this->assertLessThanOrEqual('2026-06-30', $item['transaction_date']);
        }
    }

    public function test_date_range_no_overlap_returns_empty(): void
    {
        $resp = $this->handler()->handle(
            $this->request(
                'GET',
                '/admin/vault/documents?transaction_date_from=2000-01-01&transaction_date_to=2000-12-31&counterparty_name=' . urlencode(self::$_marker),
                self::$token,
            ),
        );
        $body = json_decode((string) $resp->getBody(), true);
        $this->assertSame(0, $body['total']);
    }

    // ── Amount range filter ───────────────────────────────────────────────────

    public function test_amount_min_filter(): void
    {
        $resp = $this->handler()->handle(
            $this->request(
                'GET',
                '/admin/vault/documents?amount_min_cents=50000&counterparty_name=' . urlencode(self::$_marker),
                self::$token,
            ),
        );
        $body = json_decode((string) $resp->getBody(), true);
        foreach ($body['items'] as $item) {
            $this->assertGreaterThanOrEqual(50000, $item['amount_cents']);
        }
    }

    public function test_amount_max_filter(): void
    {
        $resp = $this->handler()->handle(
            $this->request(
                'GET',
                '/admin/vault/documents?amount_max_cents=1000&counterparty_name=' . urlencode(self::$_marker),
                self::$token,
            ),
        );
        $body = json_decode((string) $resp->getBody(), true);
        foreach ($body['items'] as $item) {
            $this->assertLessThanOrEqual(1000, $item['amount_cents']);
        }
    }

    public function test_amount_exact_match(): void
    {
        $resp = $this->handler()->handle(
            $this->request(
                'GET',
                '/admin/vault/documents?amount_min_cents=75000&amount_max_cents=75000&counterparty_name=' . urlencode(self::$_marker),
                self::$token,
            ),
        );
        $body = json_decode((string) $resp->getBody(), true);
        $this->assertGreaterThanOrEqual(1, $body['total']);
        foreach ($body['items'] as $item) {
            $this->assertSame(75000, $item['amount_cents']);
        }
    }

    // ── Category filter ───────────────────────────────────────────────────────

    public function test_category_filter_contract(): void
    {
        $resp = $this->handler()->handle(
            $this->request(
                'GET',
                '/admin/vault/documents?category=contract&counterparty_name=' . urlencode(self::$_marker),
                self::$token,
            ),
        );
        $body = json_decode((string) $resp->getBody(), true);
        $this->assertGreaterThanOrEqual(1, $body['total']);
        foreach ($body['items'] as $item) {
            $this->assertSame('contract', $item['category']);
        }
    }

    public function test_category_filter_receipt(): void
    {
        $resp = $this->handler()->handle(
            $this->request(
                'GET',
                '/admin/vault/documents?category=receipt&counterparty_name=' . urlencode(self::$_marker),
                self::$token,
            ),
        );
        $body = json_decode((string) $resp->getBody(), true);
        foreach ($body['items'] as $item) {
            $this->assertSame('receipt', $item['category']);
        }
    }

    // ── Combined filters ──────────────────────────────────────────────────────

    public function test_counterparty_and_date_combination(): void
    {
        $resp = $this->handler()->handle(
            $this->request(
                'GET',
                '/admin/vault/documents?counterparty_name=' . urlencode(self::$_marker . '-Beta') . '&transaction_date_from=2026-07-01',
                self::$token,
            ),
        );
        $body = json_decode((string) $resp->getBody(), true);
        $this->assertGreaterThanOrEqual(1, $body['total']);
        foreach ($body['items'] as $item) {
            $this->assertStringContainsString(self::$_marker . '-Beta', $item['counterparty_name']);
        }
    }

    public function test_three_filter_combination(): void
    {
        $resp = $this->handler()->handle(
            $this->request(
                'GET',
                '/admin/vault/documents?counterparty_name=' . urlencode(self::$_marker) . '&transaction_date_from=2026-01-01&amount_min_cents=1',
                self::$token,
            ),
        );
        $this->assertSame(200, $resp->getStatusCode());
        $body = json_decode((string) $resp->getBody(), true);
        $this->assertGreaterThanOrEqual(3, $body['total']);
    }

    // ── Voided documents ──────────────────────────────────────────────────────

    public function test_voided_excluded_by_default(): void
    {
        $marker  = 'VoidSrch-' . uniqid();
        $handler = $this->handler();
        $id      = $this->uploadDoc($handler, self::$token, $marker, '2026-05-01', '1000');

        $handler->handle($this->request('POST', "/admin/vault/documents/{$id}/void", self::$token, ['void_reason' => 'test']));

        $resp = $handler->handle(
            $this->request('GET', '/admin/vault/documents?counterparty_name=' . urlencode($marker), self::$token),
        );
        $body = json_decode((string) $resp->getBody(), true);
        $ids  = array_column($body['items'], 'id');
        $this->assertNotContains($id, $ids, 'Voided document must not appear without include_voided=true');
    }

    public function test_include_voided_true_shows_voided(): void
    {
        $marker  = 'VoidShow-' . uniqid();
        $handler = $this->handler();
        $id      = $this->uploadDoc($handler, self::$token, $marker, '2026-05-01', '1000');

        $handler->handle($this->request('POST', "/admin/vault/documents/{$id}/void", self::$token, ['void_reason' => 'test']));

        $resp = $handler->handle(
            $this->request('GET', '/admin/vault/documents?counterparty_name=' . urlencode($marker) . '&include_voided=true', self::$token),
        );
        $body = json_decode((string) $resp->getBody(), true);
        $ids  = array_column($body['items'], 'id');
        $this->assertContains($id, $ids);
    }

    // ── Pagination ────────────────────────────────────────────────────────────

    public function test_pagination_limit_1(): void
    {
        $resp = $this->handler()->handle(
            $this->request(
                'GET',
                '/admin/vault/documents?counterparty_name=' . urlencode(self::$_marker) . '&limit=1',
                self::$token,
            ),
        );
        $body = json_decode((string) $resp->getBody(), true);
        $this->assertCount(1, $body['items']);
        $this->assertGreaterThanOrEqual(3, $body['total']);
        $this->assertSame(1, $body['limit']);
        $this->assertSame(0, $body['offset']);
    }

    public function test_pagination_offset_beyond_total_returns_empty_items(): void
    {
        $resp = $this->handler()->handle(
            $this->request(
                'GET',
                '/admin/vault/documents?counterparty_name=' . urlencode(self::$_marker) . '&limit=10&offset=99999',
                self::$token,
            ),
        );
        $body = json_decode((string) $resp->getBody(), true);
        $this->assertCount(0, $body['items']);
        $this->assertGreaterThanOrEqual(3, $body['total'], 'Total must still reflect full count even when offset exceeds it');
    }

    public function test_pagination_offset_page_2(): void
    {
        $resp1 = $this->handler()->handle(
            $this->request(
                'GET',
                '/admin/vault/documents?counterparty_name=' . urlencode(self::$_marker) . '&limit=1&offset=0',
                self::$token,
            ),
        );
        $resp2 = $this->handler()->handle(
            $this->request(
                'GET',
                '/admin/vault/documents?counterparty_name=' . urlencode(self::$_marker) . '&limit=1&offset=1',
                self::$token,
            ),
        );

        $id1 = json_decode((string) $resp1->getBody(), true)['items'][0]['id'];
        $id2 = json_decode((string) $resp2->getBody(), true)['items'][0]['id'];

        $this->assertNotSame($id1, $id2, 'Offset=1 must return a different document than offset=0');
    }

    // ── Response shape ────────────────────────────────────────────────────────

    public function test_response_contains_required_fields(): void
    {
        $resp = $this->handler()->handle(
            $this->request(
                'GET',
                '/admin/vault/documents?counterparty_name=' . urlencode(self::$_marker) . '&limit=1',
                self::$token,
            ),
        );
        $body = json_decode((string) $resp->getBody(), true);
        $item = $body['items'][0];

        foreach (['id', 'status', 'counterparty_name', 'category', 'transaction_date', 'amount_cents', 'retention_expires_at', 'version_number', 'file_sha256'] as $field) {
            $this->assertArrayHasKey($field, $item, "Response must contain field: {$field}");
        }

        $this->assertArrayNotHasKey('file_path', $item, 'file_path must never appear in API responses');
    }

    // ── Auth ──────────────────────────────────────────────────────────────────

    public function test_unauthenticated_returns_401(): void
    {
        $resp = $this->handler()->handle(
            $this->request('GET', '/admin/vault/documents'),
        );
        $this->assertSame(401, $resp->getStatusCode());
    }
}
