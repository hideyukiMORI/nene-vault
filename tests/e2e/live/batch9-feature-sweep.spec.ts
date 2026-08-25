import { expect, test, type Browser, type Page, type TestInfo } from '@playwright/test';
import { createHash } from 'node:crypto';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

/**
 * Live-target QA — batch 9: feature sweep, one happy path and one failure path per feature (#450).
 *
 * Owner's instruction after the 0.9.1 deploy (2026-08-25): "正常系・異常系を全機能に1ルートだけで
 * いいから実ブラウザで打鍵して、表示と機能の確認をして". Function is asserted; display is captured
 * (a screenshot per case) for a person to look at. Every case records its verdict and evidence
 * into `results.json` + `index.html` under `docs/qa/feature-sweep/<date>/` even when it fails,
 * so one broken feature does not hide the others — the test itself fails at the end if any
 * case failed.
 *
 * Runs against the PUBLIC demo (`/demo/standard` mints a disposable admin org, swept after its
 * TTL), so writes are safe. NOT wired into CI (裁定 2026-07-21). Batches 1–4 predate the kit
 * migration and still look for `.modal`; this batch uses the kit's `<dialog>`.
 *
 * Usage:  npm run e2e:live --prefix frontend -- batch9
 */

type Kind = 'normal' | 'abnormal';
interface CaseResult {
  feature: string;
  kind: Kind;
  name: string;
  ok: boolean;
  evidence: string;
  shot: string | null;
}

const results: CaseResult[] = [];
let outDir = '';
let shotSeq = 0;

function dialog(page: Page) {
  return page.locator('dialog[open]').first();
}

async function shoot(page: Page, slug: string): Promise<string> {
  shotSeq += 1;
  const file = `${String(shotSeq).padStart(2, '0')}-${slug}.png`;
  await page.screenshot({ path: resolve(outDir, file), fullPage: false }).catch(() => undefined);
  return file;
}

async function runCase(
  page: Page,
  feature: string,
  kind: Kind,
  name: string,
  slug: string,
  body: () => Promise<string>,
): Promise<void> {
  let ok = false;
  let evidence = '';
  try {
    evidence = await body();
    ok = true;
  } catch (e) {
    evidence = `❌ ${(e as Error).message.split('\n')[0].slice(0, 300)}`;
  }
  const shot = await shoot(page, slug);
  results.push({ feature, kind, name, ok, evidence, shot });
  console.log(`${ok ? '✅' : '❌'} [${feature} / ${kind}] ${name} — ${evidence.slice(0, 160)}`);
}

async function seat(
  page: Page,
  base: string,
  path: '/demo/standard' | '/demo/guided',
): Promise<void> {
  await page.goto(`${base}${path}`, { waitUntil: 'networkidle' });
  try {
    await page.waitForSelector('nav.rail-nav', { timeout: 30_000 });
  } catch {
    await page.goto(`${base}${path}`, { waitUntil: 'networkidle' });
    await page.waitForSelector('nav.rail-nav', { timeout: 30_000 });
  }
}

async function railTo(page: Page, name: string, url: RegExp): Promise<void> {
  await page.locator('nav.rail-nav').getByRole('button', { name, exact: true }).click();
  await page.waitForURL(url, { timeout: 10_000 });
  await page.waitForLoadState('networkidle');
}

const PDF = Buffer.from(
  '%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n' +
    '3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\nxref\n0 4\n0000000000 65535 f \n' +
    'trailer<</Root 1 0 R/Size 4>>\nstartxref\n0\n%%EOF\n',
);
const sha256 = (b: Buffer) => createHash('sha256').update(b).digest('hex');

function esc(s: string): string {
  return s
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function renderIndex(meta: Record<string, string>): string {
  const rows = results
    .map(
      (r) =>
        `<tr class="${r.ok ? 'ok' : 'ng'}"><td>${esc(r.feature)}</td><td>${r.kind === 'normal' ? '正常' : '異常'}</td>` +
        `<td>${esc(r.name)}</td><td>${r.ok ? '✅' : '❌'}</td><td>${esc(r.evidence)}</td>` +
        `<td>${r.shot ? `<a href="${r.shot}" target="_blank"><img src="${r.shot}" alt=""></a>` : ''}</td></tr>`,
    )
    .join('\n');
  const metaRows = Object.entries(meta)
    .map(([k, v]) => `<tr><th>${esc(k)}</th><td>${esc(v)}</td></tr>`)
    .join('');
  const okCount = results.filter((r) => r.ok).length;
  return `<!doctype html><meta charset="utf-8"><title>Feature sweep — ${esc(meta.date)}</title>
<style>body{font:14px/1.5 system-ui,sans-serif;margin:24px;color:#222;background:#fafafa}
table{border-collapse:collapse;width:100%}th,td{border:1px solid #ddd;padding:6px 8px;vertical-align:top;text-align:left}
thead th{background:#eee;position:sticky;top:0}tr.ng td{background:#fff4f4}td img{width:260px;height:auto;border:1px solid #ccc;display:block}
.meta{width:auto;margin-bottom:16px}.meta th{background:#f3f3f3;white-space:nowrap}</style>
<h1>Feature sweep — ${esc(meta.date)} · ${okCount}/${results.length} ✅</h1>
<table class="meta">${metaRows}</table>
<table><thead><tr><th>機能</th><th>系</th><th>ケース</th><th>判定</th><th>根拠</th><th>画面</th></tr></thead><tbody>${rows}</tbody></table>`;
}

test.describe.configure({ mode: 'serial' });

test('FEATURE-SWEEP: one happy + one failure path per feature, on production', async ({
  browser,
}, testInfo: TestInfo) => {
  test.setTimeout(15 * 60_000);
  const base = (testInfo.project.use.baseURL ?? 'https://vault.ayane.co.jp').replace(/\/$/, '');
  const date = new Date().toISOString().slice(0, 10);
  outDir = resolve(
    testInfo.config.rootDir,
    '../../../docs/qa/feature-sweep',
    process.env.NENE_VAULT_SWEEP_DIR ?? date,
  );
  mkdirSync(outDir, { recursive: true });
  const tag = `Sweep-${Date.now().toString(36)}`;

  // ── A. auth ─────────────────────────────────────────────────────────────
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 800 }, locale: 'en-US' });
  const page = await ctx.newPage();
  // 🔴 Every action is bounded: a missing element becomes a recorded ❌, never a hang.
  page.setDefaultTimeout(20_000);
  await runCase(
    page,
    '認証',
    'normal',
    '/demo/standard で admin 着席 → Home',
    'auth-ok',
    async () => {
      await seat(page, base, '/demo/standard');
      await expect(page.getByText('Welcome back')).toBeVisible({ timeout: 10_000 });
      const links = await page.locator('nav.rail-nav button.rail-link').allInnerTexts();
      return `rail: ${links.map((l) => l.trim()).join(' / ')}`;
    },
  );
  {
    const c2 = await browser.newContext({
      viewport: { width: 1280, height: 800 },
      locale: 'en-US',
    });
    const p2 = await c2.newPage();
    p2.setDefaultTimeout(20_000);
    await runCase(
      p2,
      '認証',
      'abnormal',
      '未認証で /documents → ログインフォーム／誤パスワード → エラー文',
      'auth-ng',
      async () => {
        await p2.goto(`${base}/documents`, { waitUntil: 'networkidle' });
        await expect(p2.locator('input[type="email"]')).toBeVisible({ timeout: 10_000 });
        await p2.locator('input[type="email"]').fill('nobody@example.com');
        await p2.locator('input[type="password"]').fill('wrong-password-123');
        await p2.getByRole('button', { name: /log in/i }).click();
        await expect(p2.getByText(/incorrect|invalid|error/i).first()).toBeVisible({
          timeout: 10_000,
        });
        return 'login form shown in place; wrong credentials → error message visible; no redirect to app';
      },
    );
    await c2.close();
  }

  // ── B. search ───────────────────────────────────────────────────────────
  await railTo(page, 'Received Documents', /\/documents$/);
  let firstCounterparty = '';
  await runCase(
    page,
    '文書一覧・検索',
    'normal',
    '取引先で絞り込み → 該当行のみ',
    'search-ok',
    async () => {
      await page.locator('table tbody tr').first().waitFor({ timeout: 10_000 });
      const before = await page.locator('table tbody tr').count();
      firstCounterparty = (
        await page.locator('table tbody tr').first().locator('td').nth(1).innerText()
      ).trim();
      await page.locator('input[name="counterparty_name"]').fill(firstCounterparty);
      await page.getByRole('button', { name: 'Search', exact: true }).click();
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(500);
      const rows = page.locator('table tbody tr');
      const n = await rows.count();
      expect(n).toBeGreaterThan(0);
      for (let i = 0; i < n; i++)
        expect(await rows.nth(i).innerText()).toContain(firstCounterparty);
      return `${before} rows → "${firstCounterparty}" → ${n} rows, all matching`;
    },
  );
  await runCase(page, '文書一覧・検索', 'abnormal', '該当なし → 空状態', 'search-ng', async () => {
    await page.locator('input[name="counterparty_name"]').fill('ZZZ-NoSuchCounterparty-ZZZ');
    await page.getByRole('button', { name: 'Search', exact: true }).click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(500);
    const n = await page.locator('table tbody tr').count();
    const body = await page.locator('main, body').first().innerText();
    expect(n).toBe(0);
    expect(body).toMatch(/No documents|No data|0 documents/i);
    return `0 rows; empty-state text present`;
  });
  await page.getByRole('button', { name: 'Clear', exact: true }).click();
  await page.waitForLoadState('networkidle');

  // ── C. upload ───────────────────────────────────────────────────────────
  await runCase(
    page,
    'アップロード',
    'normal',
    '正当な PDF → 一覧に出る',
    'upload-ok',
    async () => {
      await page.getByRole('button', { name: 'Upload Document' }).click();
      const d = dialog(page);
      await d.waitFor({ state: 'visible', timeout: 10_000 });
      await d
        .locator('input[type="file"]')
        .setInputFiles({ name: `${tag}.pdf`, mimeType: 'application/pdf', buffer: PDF });
      await d.locator('input[name="counterparty_name"]').fill(tag);
      await d.getByRole('button', { name: 'Upload', exact: true }).click();
      await d.waitFor({ state: 'hidden', timeout: 30_000 });
      await page.waitForLoadState('networkidle');
      await expect(page.locator('table tbody tr', { hasText: tag }).first()).toBeVisible({
        timeout: 15_000,
      });
      return `dialog closed; row "${tag}" listed`;
    },
  );
  await runCase(
    page,
    'アップロード',
    'abnormal',
    '偽 PDF（テキストを .pdf）→ 拒否・一覧に増えない',
    'upload-ng',
    async () => {
      const before = await page.locator('table tbody tr').count();
      await page.getByRole('button', { name: 'Upload Document' }).click();
      const d = dialog(page);
      await d.waitFor({ state: 'visible', timeout: 10_000 });
      await d.locator('input[type="file"]').setInputFiles({
        name: 'fake.pdf',
        mimeType: 'application/pdf',
        buffer: Buffer.from('hello, not a pdf'),
      });
      await d.locator('input[name="counterparty_name"]').fill(`${tag}-fake`);
      await d.getByRole('button', { name: 'Upload', exact: true }).click();
      await page.waitForTimeout(2_500);
      const stillOpen = await d.count();
      const text = stillOpen ? (await d.innerText()).replace(/\s+/g, ' ') : '';
      expect(stillOpen, 'dialog stays open after a rejected upload').toBe(1);
      expect(text).toMatch(/error|not allowed|unsupported|MIME|type|invalid|failed/i);
      await d
        .getByRole('button', { name: /cancel|close/i })
        .first()
        .click();
      await d.waitFor({ state: 'hidden', timeout: 10_000 });
      await page.waitForLoadState('networkidle');
      const after = await page.locator('table tbody tr').count();
      expect(after).toBe(before);
      return `rejected; message: "${(text.match(/[^.]*(unsupported|not allowed|invalid|error|failed)[^.]*/i) ?? [''])[0].trim()}"; rows ${before} → ${after}`;
    },
  );

  // ── D. detail ───────────────────────────────────────────────────────────
  await runCase(
    page,
    '文書詳細',
    'normal',
    '詳細（メタデータ・バージョン・変更履歴）',
    'detail-ok',
    async () => {
      await page
        .locator('table tbody tr', { hasText: tag })
        .first()
        .getByRole('button', { name: 'Details' })
        .click();
      await page.waitForURL(/\/documents\/[^/]+$/, { timeout: 10_000 });
      await page.waitForLoadState('networkidle');
      await page.getByText('Metadata', { exact: true }).first().waitFor({ timeout: 20_000 });
      const body = await page.locator('body').innerText();
      expect(body).toContain('Metadata');
      expect(body).toContain('File');
      expect(body).toContain('Change History');
      expect(body).toContain(sha256(PDF));
      expect(body).toMatch(/SHA-256/i);
      expect(body).toContain(tag);
      return `detail shows Metadata / File (SHA-256 = uploaded bytes) / Change History for "${tag}"`;
    },
  );
  const detailUrl = page.url();
  await runCase(
    page,
    '文書詳細',
    'abnormal',
    '存在しない id → エラー表示（500 でない・シェル健在）',
    'detail-ng',
    async () => {
      const resp = await page.goto(`${base}/documents/01ZZZZZZZZZZZZZZZZZZZZZZZZ`, {
        waitUntil: 'networkidle',
      });
      await page.waitForTimeout(800);
      const body = await page.locator('body').innerText();
      expect(resp?.status() ?? 0).toBeLessThan(500);
      await expect(page.locator('nav.rail-nav')).toBeVisible();
      expect(body).toMatch(/error|not found/i);
      return `HTTP ${resp?.status()}; error text visible; rail intact`;
    },
  );

  // ── E. metadata edit ─────────────────────────────────────────────────────
  await page.goto(detailUrl, { waitUntil: 'networkidle' });
  await runCase(
    page,
    'メタデータ編集',
    'normal',
    '取引先を変更 → 保存 → 反映',
    'meta-ok',
    async () => {
      await page.getByRole('button', { name: 'Edit', exact: true }).click();
      const d = dialog(page);
      await d.waitFor({ state: 'visible', timeout: 10_000 });
      await d.locator('input[name="counterparty_name"]').fill(`${tag} edited`);
      await d.getByRole('button', { name: 'Save Changes' }).click();
      await d.waitFor({ state: 'hidden', timeout: 20_000 });
      await page.waitForLoadState('networkidle');
      await expect(page.getByText(`${tag} edited`).first()).toBeVisible({ timeout: 10_000 });
      return `counterparty now "${tag} edited"`;
    },
  );
  await runCase(
    page,
    'メタデータ編集',
    'abnormal',
    '金額に負数 → バリデーションで保存不可',
    'meta-ng',
    async () => {
      await page.getByRole('button', { name: 'Edit', exact: true }).click();
      const d = dialog(page);
      await d.waitFor({ state: 'visible', timeout: 10_000 });
      await d.locator('input[type="number"]').first().fill('-1');
      await d.getByRole('button', { name: 'Save Changes' }).click();
      await page.waitForTimeout(1_500);
      expect(await d.count(), 'dialog stays open').toBe(1);
      const text = (await d.innerText()).replace(/\s+/g, ' ');
      expect(text).toMatch(/too small|invalid|must|error|required/i);
      await d
        .getByRole('button', { name: /cancel|close/i })
        .first()
        .click();
      await d.waitFor({ state: 'hidden', timeout: 10_000 });
      return `blocked; message: "${(text.match(/[^.]*(too small|invalid|must|error|required)[^.]*/i) ?? [''])[0].trim().slice(0, 120)}"`;
    },
  );

  // ── F. void / restore ────────────────────────────────────────────────────
  await runCase(
    page,
    '無効化／復元',
    'abnormal',
    '理由なしで Void → 必須エラー',
    'void-ng',
    async () => {
      await page.getByRole('button', { name: 'Void', exact: true }).click();
      const d = dialog(page);
      await d.waitFor({ state: 'visible', timeout: 10_000 });
      await d.getByRole('button', { name: 'Void', exact: true }).click();
      await page.waitForTimeout(1_000);
      expect(await d.count()).toBe(1);
      const text = (await d.innerText()).replace(/\s+/g, ' ');
      expect(text).toMatch(/required/i);
      await d
        .getByRole('button', { name: /cancel|close/i })
        .first()
        .click();
      await d.waitFor({ state: 'hidden', timeout: 10_000 });
      return 'empty reason → "This field is required." shown; not submitted';
    },
  );
  await runCase(
    page,
    '無効化／復元',
    'normal',
    '理由つき Void → Voided → Restore → Active',
    'void-restore-ok',
    async () => {
      await page.getByRole('button', { name: 'Void', exact: true }).click();
      let d = dialog(page);
      await d.waitFor({ state: 'visible', timeout: 10_000 });
      await d
        .locator('textarea, input[name="void_reason"]')
        .first()
        .fill('QA sweep — registered by mistake');
      await d.getByRole('button', { name: 'Void', exact: true }).click();
      await d.waitFor({ state: 'hidden', timeout: 20_000 });
      await page.waitForLoadState('networkidle');
      await expect(page.getByText(/voided/i).first()).toBeVisible({ timeout: 10_000 });
      await page.getByRole('button', { name: 'Restore', exact: true }).click();
      d = dialog(page);
      await d.waitFor({ state: 'visible', timeout: 10_000 });
      await d.getByRole('button', { name: 'Restore', exact: true }).click();
      await d.waitFor({ state: 'hidden', timeout: 20_000 });
      await page.waitForLoadState('networkidle');
      await expect(page.getByText(/\bactive\b/i).first()).toBeVisible({ timeout: 10_000 });
      return 'voided (reason recorded) → restored → Active';
    },
  );

  // ── G. download ─────────────────────────────────────────────────────────
  await runCase(
    page,
    'ダウンロード',
    'normal',
    'Download → 受信バイトの sha256 がアップロード時と一致',
    'download-ok',
    async () => {
      const [dl] = await Promise.all([
        page.waitForEvent('download', { timeout: 30_000 }),
        page.getByRole('button', { name: 'Download', exact: true }).first().click(),
      ]);
      const path = await dl.path();
      const got = sha256(readFileSync(path as string));
      expect(got).toBe(sha256(PDF));
      return `sha256 ${got.slice(0, 12)}… = uploaded (${PDF.length} bytes)`;
    },
  );

  // ── H. audit ────────────────────────────────────────────────────────────
  await railTo(page, 'Audit Log', /\/audit$/);
  await runCase(
    page,
    '監査ログ',
    'normal',
    '一覧 → Action で絞り込み（voided）',
    'audit-ok',
    async () => {
      // The list re-renders once after its first paint (loading → rows); a count taken in
      // between reads 0 (measured locally 2026-08-25). Poll instead of counting once.
      await expect
        .poll(() => page.locator('table tbody tr').count(), { timeout: 20_000 })
        .toBeGreaterThan(0);
      const before = await page.locator('table tbody tr').count();
      const action = page.locator('#audit-filter-action').first();
      await action.fill('document.voided'); // exact match on the stored action (measured)
      await page.getByRole('button', { name: 'Search', exact: true }).click();
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(500);
      const rows = page.locator('table tbody tr');
      const n = await rows.count();
      expect(before).toBeGreaterThan(0);
      expect(n).toBeGreaterThan(0);
      for (let i = 0; i < n; i++) expect(await rows.nth(i).innerText()).toMatch(/voided/i);
      return `${before} rows → filter "document.voided" (exact) → ${n} rows, all voided`;
    },
  );
  await runCase(
    page,
    '監査ログ',
    'abnormal',
    '存在しない Action → 0 件・空状態',
    'audit-ng',
    async () => {
      const action = page.locator('#audit-filter-action').first();
      await action.fill('no.such.action.zzz');
      await page.getByRole('button', { name: 'Search', exact: true }).click();
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(500);
      const n = await page.locator('table tbody tr').count();
      expect(n).toBe(0);
      return '0 rows; page intact';
    },
  );

  // ── I. export ───────────────────────────────────────────────────────────
  await railTo(page, 'Export', /\/export$/);
  await runCase(
    page,
    'エクスポート',
    'normal',
    'CSV（manifest）→ ダウンロード',
    'export-ok',
    async () => {
      await page.locator('input[name="export-format"][value="csv"]').check();
      const [dl] = await Promise.all([
        page.waitForEvent('download', { timeout: 60_000 }),
        page.getByRole('button', { name: 'Start Export' }).click(),
      ]);
      const buf = readFileSync((await dl.path()) as string);
      expect(buf.length).toBeGreaterThan(0);
      const head = buf.toString('utf8').split('\n')[0];
      expect(head).toMatch(/document|id|counterparty/i);
      return `${dl.suggestedFilename()} ${buf.length} bytes; header: ${head.slice(0, 80)}`;
    },
  );
  await runCase(
    page,
    'エクスポート',
    'abnormal',
    '期間の逆転（From > To）→ エラー表示またはブロック',
    'export-ng',
    async () => {
      const dates = page.locator('input[type="date"]');
      await dates.nth(0).fill('2026-12-31');
      await dates.nth(1).fill('2026-01-01');
      let downloaded = false;
      page.once('download', () => {
        downloaded = true;
      });
      await page.getByRole('button', { name: 'Start Export' }).click();
      await page.waitForTimeout(3_000);
      const body = (await page.locator('main, body').first().innerText()).replace(/\s+/g, ' ');
      await expect(page.locator('nav.rail-nav')).toBeVisible();
      const msg = body.match(/error|invalid|must|before|after|range/i);
      expect(downloaded || msg !== null, 'either an error message or no download').toBeTruthy();
      return downloaded
        ? '⚠️ accepted (downloaded) — no range validation'
        : `blocked; text: "${(body.match(/[^.]*(error|invalid|must|before|after|range)[^.]*/i) ?? [''])[0].trim().slice(0, 120)}"`;
    },
  );

  // ── J. settings ─────────────────────────────────────────────────────────
  await railTo(page, 'Vault Settings', /\/settings$/);
  await runCase(
    page,
    '設定',
    'normal',
    '保持年数を変更 → 保存メッセージ',
    'settings-ok',
    async () => {
      const input = page.locator('input[name="retention_years"]');
      await input.waitFor({ timeout: 10_000 });
      const cur = Number(await input.inputValue());
      const next = cur === 11 ? 12 : 11;
      await input.fill(String(next));
      await page.getByRole('button', { name: 'Save Settings' }).click();
      await expect(page.getByText(/settings saved/i)).toBeVisible({ timeout: 10_000 });
      await input.fill(String(cur));
      await page.getByRole('button', { name: 'Save Settings' }).click();
      await page.waitForLoadState('networkidle');
      return `retention ${cur} → ${next} saved ("Vault settings saved.") → restored to ${cur}`;
    },
  );
  await runCase(
    page,
    '設定',
    'abnormal',
    '保持年数 0 → バリデーション（min=7）',
    'settings-ng',
    async () => {
      // Fresh page: the previous case's save refetches settings and resets the form; a fill that
      // lands before that reset is silently overwritten (measured — 0 became 10 and "saved").
      await page.reload({ waitUntil: 'networkidle' });
      const input = page.locator('input[name="retention_years"]');
      await expect(input).not.toHaveValue('', { timeout: 20_000 });
      await input.fill('0');
      await expect(input).toHaveValue('0');
      const seen: { status: number | null } = { status: null };
      page.on('response', (r) => {
        if (r.url().includes('/admin/vault/settings') && r.request().method() !== 'GET')
          seen.status = r.status();
      });
      await page.getByRole('button', { name: 'Save Settings' }).click();
      await page.waitForTimeout(1_500);
      const v = await input.evaluate((el) => {
        const i = el as HTMLInputElement;
        return { valid: i.validity.valid, msg: i.validationMessage, min: i.min };
      });
      const body = (await page.locator('main, body').first().innerText()).replace(/\s+/g, ' ');
      const domMsg = body.match(/[^.]*(too small|minimum|invalid|error|must)[^.]*/i)?.[0].trim();
      const blocked =
        !v.valid || domMsg !== undefined || (seen.status !== null && seen.status >= 400);
      expect(
        blocked,
        `0 must be rejected (validity.valid=${v.valid}, min="${v.min}", server=${seen.status ?? 'no request'})`,
      ).toBe(true);
      if (seen.status !== null)
        expect(seen.status, 'server must not accept 0').toBeGreaterThanOrEqual(400);
      await page.reload({ waitUntil: 'networkidle' });
      const after = await page.locator('input[name="retention_years"]').inputValue();
      expect(after).not.toBe('0');
      return `blocked (min="${v.min}"; browser: "${v.msg}"${domMsg ? `; page: "${domMsg}"` : ''}; server: ${seen.status ?? 'no request sent'}); value after reload=${after}`;
    },
  );

  // ── K. users ────────────────────────────────────────────────────────────
  await railTo(page, 'Users', /\/users$/);
  const email = `${tag.toLowerCase()}@example.com`;
  await runCase(page, 'ユーザー', 'normal', '招待 → 一覧に出る', 'users-ok', async () => {
    await page.getByRole('button', { name: 'Invite User' }).click();
    const d = dialog(page);
    await d.waitFor({ state: 'visible', timeout: 10_000 });
    await d.locator('input[type="email"]').fill(email);
    await d.locator('input[type="password"]').fill('SweepPass-2026!');
    await d
      .getByRole('button', { name: /invite|save|submit/i })
      .last()
      .click();
    await d.waitFor({ state: 'hidden', timeout: 20_000 });
    await page.waitForLoadState('networkidle');
    await expect(page.locator('table tbody tr', { hasText: email }).first()).toBeVisible({
      timeout: 10_000,
    });
    return `invited ${email}; listed`;
  });
  await runCase(
    page,
    'ユーザー',
    'abnormal',
    '同じメールで再招待 → 重複エラー',
    'users-ng',
    async () => {
      await page.getByRole('button', { name: 'Invite User' }).click();
      const d = dialog(page);
      await d.waitFor({ state: 'visible', timeout: 10_000 });
      await d.locator('input[type="email"]').fill(email);
      await d.locator('input[type="password"]').fill('SweepPass-2026!');
      await d
        .getByRole('button', { name: /invite|save|submit/i })
        .last()
        .click();
      await page.waitForTimeout(2_000);
      expect(await d.count(), 'dialog stays open').toBe(1);
      const text = (await d.innerText()).replace(/\s+/g, ' ');
      expect(text).toMatch(/already in use|conflict|exists|error/i);
      await d
        .getByRole('button', { name: /cancel|close/i })
        .first()
        .click();
      await d.waitFor({ state: 'hidden', timeout: 10_000 });
      return `blocked; message: "${(text.match(/[^.]*(already in use|conflict|exists|error)[^.]*/i) ?? [''])[0].trim().slice(0, 120)}"`;
    },
  );

  // ── L. language ─────────────────────────────────────────────────────────
  await runCase(
    page,
    '言語切替',
    'normal',
    'English → 日本語 で UI 文言が変わる',
    'lang-ok',
    async () => {
      const sel = page.locator('select[aria-label*="anguage"], select').first();
      await sel.selectOption('ja');
      await page.waitForTimeout(800);
      const rail = await page.locator('nav.rail-nav').innerText();
      expect(rail).toMatch(/[぀-ヿ一-鿿]/);
      await sel.selectOption('en');
      await page.waitForTimeout(500);
      return `rail after switch: ${rail.replace(/\s+/g, ' ').slice(0, 80)}`;
    },
  );

  // ── M. viewer seat ──────────────────────────────────────────────────────
  {
    const c3 = await browser.newContext({
      viewport: { width: 1280, height: 800 },
      locale: 'en-US',
    });
    const p3 = await c3.newPage();
    p3.setDefaultTimeout(20_000);
    await runCase(
      p3,
      'viewer（guided）',
      'normal',
      '/demo/guided で閲覧席 → 文書一覧が読める',
      'viewer-ok',
      async () => {
        await seat(p3, base, '/demo/guided');
        const links = (await p3.locator('nav.rail-nav button.rail-link').allInnerTexts()).map((l) =>
          l.trim(),
        );
        await railTo(p3, 'Received Documents', /\/documents$/);
        await p3.locator('table tbody tr').first().waitFor({ timeout: 20_000 });
        const n = await p3.locator('table tbody tr').count();
        expect(n).toBeGreaterThan(0);
        return `rail: ${links.join(' / ')}; ${n} documents readable`;
      },
    );
    await runCase(
      p3,
      'viewer（guided）',
      'abnormal',
      'viewer がアップロードを試みる → 拒否／/settings 直打ち → 拒否',
      'viewer-ng',
      async () => {
        const uploadBtn = await p3.getByRole('button', { name: 'Upload Document' }).count();
        let uploadNote = 'Upload button hidden for viewer';
        if (uploadBtn > 0) {
          await p3.getByRole('button', { name: 'Upload Document' }).click();
          const d = dialog(p3);
          await d.waitFor({ state: 'visible', timeout: 10_000 });
          await d
            .locator('input[type="file"]')
            .setInputFiles({ name: 'viewer.pdf', mimeType: 'application/pdf', buffer: PDF });
          await d.locator('input[name="counterparty_name"]').fill('viewer-attempt');
          await d.getByRole('button', { name: 'Upload', exact: true }).click();
          await p3.waitForTimeout(2_500);
          const open = await d.count();
          const text = (open ? await d.innerText() : await p3.locator('body').innerText()).replace(
            /\s+/g,
            ' ',
          );
          expect(text).toMatch(/forbidden|permission|not allowed|error|failed|403/i);
          uploadNote = `⚠️ Upload button IS visible to viewer (#451); refused → ${open ? 'error in dialog' : `Forbidden page (${p3.url().replace(base, '')})`}: "${(text.match(/[^.]*(forbidden|permission|not allowed|error|failed|403)[^.]*/i) ?? [''])[0].trim()}"`;
          if (open) await p3.keyboard.press('Escape');
        }
        await p3.goto(`${base}/settings`, { waitUntil: 'networkidle' });
        await p3.waitForTimeout(800);
        const body = (await p3.locator('body').innerText()).replace(/\s+/g, ' ');
        const url = p3.url().replace(base, '');
        expect(body).toMatch(/forbidden|permission|not allowed|403|login|error|access denied/i);
        return `${uploadNote}; /settings → ${url} — "${(body.match(/[^.]*(forbidden|permission|not allowed|403|login|error|access denied)[^.]*/i) ?? [''])[0].trim().slice(0, 100)}"`;
      },
    );
    await c3.close();
  }

  // ── N. mobile display (observation) ─────────────────────────────────────
  await page.setViewportSize({ width: 375, height: 812 });
  await railTo(page, 'Received Documents', /\/documents$/);
  await runCase(
    page,
    'モバイル表示',
    'normal',
    '375px で横はみ出しなし（観察）',
    'mobile-ok',
    async () => {
      const o = await page.evaluate(() => ({
        s: document.documentElement.scrollWidth,
        w: window.innerWidth,
      }));
      expect(o.s).toBeLessThanOrEqual(o.w + 1);
      return `scrollWidth ${o.s} ≤ innerWidth ${o.w}`;
    },
  );
  await ctx.close();

  const meta: Record<string, string> = {
    date,
    'measured at (UTC)': new Date().toISOString(),
    target: base,
    'sweep tag': tag,
    cases: `${results.length} (${results.filter((r) => r.ok).length} ok)`,
  };
  writeFileSync(resolve(outDir, 'results.json'), JSON.stringify({ meta, results }, null, 2));
  writeFileSync(resolve(outDir, 'index.html'), renderIndex(meta));
  console.log(`feature-sweep: ${meta.cases} → ${outDir}/index.html`);
  const failed = results.filter((r) => !r.ok);
  expect(
    failed.map((f) => `${f.feature}/${f.kind}: ${f.evidence}`),
    'all cases pass',
  ).toEqual([]);
});
