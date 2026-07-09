<?php

declare(strict_types=1);

/**
 * Tier A web installer for NeNe Vault on shared hosting (#120 — ported to the
 * invoice/clear shape: lives in `public_html/` so it is reachable with the
 * documented docroot, resolves the project root as `dirname(__DIR__)`, and
 * keeps `vendor/`, `src/` and `.env` above the docroot).
 *
 * The wizard shell follows the proven nene-clear installer (step wizard with
 * field-level validation that preserves input, DB connection test, loading
 * substeps, blocked view, CLI pattern export); the setup internals are
 * Vault's existing hardened path unchanged: `InstallEnvironment::values()` →
 * `EnvironmentWriter` (0640, escaped), `docker/bootstrap-schema.php` (SQLite)
 * or `Nene2\Install\DatabaseSchemaApplier` (MySQL, in-process — shared hosts
 * disable exec), `docker/seed-initial.php` with the admin password handed
 * over in memory only, and `ReInstallationGuard` (marker + DB probe).
 *
 * On success the installer deletes itself. CLI:
 * `php public_html/install.php --export-patterns [dir]` renders every screen
 * state to static HTML (design-handoff source).
 */

use Nene2\Install\DatabaseSchemaApplier;
use Nene2\Install\EnvironmentWriter;
use Nene2\Install\ReInstallationGuard;
use NeneVault\Install\DatabaseProvisioningProbe;
use NeneVault\Install\InstallEnvironment;
use Phinx\Config\Config as PhinxConfig;

$root = dirname(__DIR__);
$marker = $root . '/var/.installed';
$envFile = $root . '/.env';

const MIN_PHP = '8.4.1';

// -------------------------------------------------------------------------
// Helpers (dependency-zero until the vendor check passes)
// -------------------------------------------------------------------------

/** HTML-escape. */
function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/** Read a POST field as a trimmed string. */
function post(string $key): string
{
    return is_string($_POST[$key] ?? null) ? trim((string) $_POST[$key]) : '';
}

/** Read a POST field without trimming (passwords). */
function post_raw(string $key): string
{
    return is_string($_POST[$key] ?? null) ? (string) $_POST[$key] : '';
}

/** 403 refusal shared by the entry guard and the pre-setup re-check. */
function refuse_install(string $message): never
{
    http_response_code(403);
    echo render_installer_page([
        'view' => 'blocked',
        'blockedMessage' => $message,
    ]);
    exit;
}

/** SVG icons (static, trusted markup). */
function ico(string $name): string
{
    return match ($name) {
        'mark' => '<svg viewBox="0 0 42 42" fill="none"><rect x="7" y="5" width="28" height="32" rx="3" stroke="currentColor" stroke-width="2.4"/><path d="M14 13h14M14 19h14M14 25h8" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/><path d="M25 29.5l3 3 5.5-6" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'check' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10.5l4 4 8-9"/></svg>',
        'x' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M5 5l10 10M15 5L5 15"/></svg>',
        'arrow' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10h11M11 5l5 5-5 5"/></svg>',
        'back' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M16 10H5M9 5L4 10l5 5"/></svg>',
        'shield' => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M8 2l5 2v3.5c0 3-2 5.3-5 6.5-3-1.2-5-3.5-5-6.5V4z"/></svg>',
        'server' => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2.5" y="3" width="11" height="4.5" rx="1"/><rect x="2.5" y="8.5" width="11" height="4.5" rx="1"/><circle cx="5" cy="5.25" r=".6" fill="currentColor"/><circle cx="5" cy="10.75" r=".6" fill="currentColor"/></svg>',
        'oss' => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M8 1.5l2 4.5 4.8.4-3.6 3.2 1.1 4.7L8 11.8 3.7 14.3l1.1-4.7L1.2 6.4 6 6z"/></svg>',
        'help' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7.5"/><path d="M7.8 7.7a2.2 2.2 0 0 1 4.3.6c0 1.5-2.1 1.9-2.1 3"/><path d="M10 14.2v.01"/></svg>',
        'eye' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2 10s3-5.5 8-5.5S18 10 18 10s-3 5.5-8 5.5S2 10 2 10z"/><circle cx="10" cy="10" r="2.4"/></svg>',
        'warn' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 3l8 14H2z"/><path d="M10 8v4M10 14.5v.01"/></svg>',
        'trash' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 5.5h13M8 5.5V4a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v1.5M5.5 5.5l.7 10a1.5 1.5 0 0 0 1.5 1.4h4.6a1.5 1.5 0 0 0 1.5-1.4l.7-10"/></svg>',
        'login' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 4H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3"/><path d="M12 6l4 4-4 4M16 10H8"/></svg>',
        'org' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="6" height="10" rx="1"/><rect x="11" y="3" width="6" height="14" rx="1"/><path d="M5 10h2M5 13h2M13 6h2M13 9h2M13 12h2"/></svg>',
        default => '',
    };
}

// -------------------------------------------------------------------------
// Requirements
// -------------------------------------------------------------------------

/** @return list<array{label: string, detail: string, ok: bool, fix: string}> */
function requirement_checks(string $root): array
{
    $exts = ['pdo', 'pdo_sqlite', 'pdo_mysql', 'zip', 'mbstring', 'json'];
    $extsOk = array_reduce($exts, static fn (bool $carry, string $e): bool => $carry && extension_loaded($e), true);

    if (!is_dir($root . '/var')) {
        @mkdir($root . '/var', 0755, true);
    }

    return [
        [
            'label' => 'PHP ' . MIN_PHP . ' 以上',
            'detail' => '現在: ' . PHP_VERSION,
            'ok' => version_compare(PHP_VERSION, MIN_PHP, '>='),
            'fix' => 'サーバーのコントロールパネルで使用する PHP のバージョンを 8.4 以上に切り替えてください。',
        ],
        [
            'label' => 'PHP 拡張モジュール',
            'detail' => implode(' / ', $exts),
            'ok' => $extsOk,
            'fix' => '不足している拡張モジュールを有効化してください（ホスティングのサポートにご確認ください）。',
        ],
        [
            'label' => 'var/ ディレクトリへの書き込み権限',
            'detail' => 'インストール完了マーカーとデータを保存します',
            'ok' => is_writable($root . '/var'),
            'fix' => 'ファイルマネージャまたは FTP で <code>var/</code> フォルダのパーミッションを「書き込み可（755 または 775）」に変更してください。',
        ],
        [
            'label' => 'ルートディレクトリへの書き込み権限',
            'detail' => '.env ファイルを作成します',
            'ok' => is_writable($root),
            'fix' => '展開先フォルダを一時的に書き込み可にしてください。インストール完了後は元の権限に戻して構いません。',
        ],
        [
            'label' => 'vendor/ ディレクトリ（依存一式）',
            'detail' => '依存ライブラリ',
            'ok' => is_file($root . '/vendor/autoload.php'),
            'fix' => 'ZIP ファイルが完全に展開されているか確認してください。',
        ],
    ];
}

// -------------------------------------------------------------------------
// Page renderer — single function so the CLI pattern export renders exactly
// what the live installer serves.
// -------------------------------------------------------------------------

/**
 * @param array{
 *   view: string,
 *   checks?: list<array{label: string, detail: string, ok: bool, fix: string}>,
 *   reqErrors?: list<array{label: string, detail: string, ok: bool, fix: string}>,
 *   errors?: list<string>,
 *   fieldErrors?: array<string, string>,
 *   old?: array<string, string>,
 *   messages?: list<string>,
 *   summary?: string,
 *   blockedMessage?: string
 * } $state
 */
function render_installer_page(array $state): string
{
    $view = $state['view'];
    $checks = $state['checks'] ?? [];
    $reqErrors = $state['reqErrors'] ?? [];
    $errors = $state['errors'] ?? [];
    $fieldErrors = $state['fieldErrors'] ?? [];
    $oldValues = $state['old'] ?? [];
    $messages = $state['messages'] ?? [];
    $summary = $state['summary'] ?? '';
    $blockedMessage = $state['blockedMessage'] ?? '';

    $old = static fn (string $k, string $default = ''): string => h($oldValues[$k] ?? $default);
    $hasError = $errors !== [] || $fieldErrors !== [];
    $adapterOld = ($oldValues['db_adapter'] ?? 'mysql') === 'sqlite' ? 'sqlite' : 'mysql';

    $hosts = [
        ['id' => 'heteml', 'label' => 'ヘテムル', 'host' => 'mysqlXXX.phy.heteml.lan', 'db' => '_nene_vault', 'user' => '_nene_vault', 'note' => '「データベース」→「データベース一覧」の「ホスト名（DB サーバー）」を使います。ユーザー名は DB 名と同じです。'],
        ['id' => 'sakura', 'label' => 'さくら', 'host' => 'mysqlXXX.db.sakura.ne.jp', 'db' => 'yourname_vault', 'user' => 'yourname', 'note' => '「データベース」→ 該当 DB の「データベースサーバ」欄がホスト名です。'],
        ['id' => 'xserver', 'label' => 'エックスサーバー', 'host' => 'mysqlXXXX.xserver.jp', 'db' => 'yourid_vault', 'user' => 'yourid_user', 'note' => 'サーバーパネル「MySQL 設定」→「MySQL ホスト名」を確認してください。'],
        ['id' => 'conoha', 'label' => 'ConoHa WING', 'host' => 'mysqlXXX.conoha.ne.jp', 'db' => 'yourname_vault', 'user' => 'yourname', 'note' => '「データベース」→ 対象 DB の「ホスト名」をコピーしてください。'],
        ['id' => 'other', 'label' => 'その他 / わからない', 'host' => 'localhost', 'db' => 'yourname_vault', 'user' => 'yourname', 'note' => '契約中のレンタルサーバー管理画面（コントロールパネル）の「データベース」欄で確認できます。'],
    ];

    $stepIdx = match ($view) {
        'app' => 1,
        'failed' => 1,
        'complete' => 2,
        default => 0,
    };
    $vsteps = [
        ['t' => 'データベース', 'd' => '接続情報の入力'],
        ['t' => 'アプリ設定', 'd' => '組織と管理者の作成'],
        ['t' => '完了', 'd' => 'セットアップ終了'],
    ];

    $reqList = static function (array $rows): string {
        $html = '<ul class="reqs">';
        foreach ($rows as $c) {
            $html .= '<li class="' . ($c['ok'] ? 'pass' : 'fail') . '">'
                . '<span class="ic">' . ($c['ok'] ? ico('check') : ico('x')) . '</span>'
                . '<div class="rq-body"><div class="rq-t">' . h($c['label']) . '</div>'
                . '<div class="rq-d">' . h($c['detail']) . '</div>'
                . (!$c['ok'] ? '<div class="rq-fix"><b>解決方法:</b> ' . $c['fix'] . '</div>' : '')
                . '</div></li>';
        }

        return $html . '</ul>';
    };
    $alert = static fn (string $kind, string $title, string $textHtml, string $detail = ''): string => '<div class="alert ' . $kind . '">' . ico($kind === 'ok' ? 'check' : 'warn')
        . '<div class="a-body"><div class="a-title">' . h($title) . '</div><div class="a-text">' . $textHtml . '</div>'
        . ($detail !== '' ? '<details><summary>技術的な詳細を表示</summary><div class="det">' . h($detail) . '</div></details>' : '')
        . '</div></div>';
    $fieldErr = static function (string $key, string $hint) use ($fieldErrors): string {
        if (isset($fieldErrors[$key])) {
            return '<p class="err-text">' . ico('warn') . h($fieldErrors[$key]) . '</p>';
        }

        return $hint !== '' ? '<p class="hint">' . $hint . '</p>' : '';
    };
    $inputClass = static fn (string $key): string => isset($fieldErrors[$key]) ? 'input is-error' : 'input';

    $body = '';

    if ($view === 'blocked') {
        $body = '<div class="iz-head">インストールできません</div>'
            . $alert('error', 'インストールがブロックされました', h($blockedMessage))
            . '<div class="sec-warn"><span class="sw-ico">' . ico('trash') . '</span><div>'
            . '<div class="sw-t">セキュリティ: install.php を削除してください</div>'
            . '<div class="sw-d">構成済みの環境に <code>install.php</code> を残すと、第三者に再セットアップされる恐れがあります。FTP またはファイルマネージャから<b>今すぐ削除</b>してください。</div>'
            . '</div></div>';
    } elseif ($view === 'requirements') {
        $body = '<div class="iz-head">サーバー要件の確認</div>'
            . '<div class="iz-headsub">インストールを始める前に、サーバーが NeNe Vault の動作条件を満たしているか確認します。</div>';
        $body .= $reqErrors === []
            ? $alert('ok', 'すべての要件を満たしています', 'このサーバーでインストールを続行できます。')
            : $alert('error', '要件チェックに失敗しました', '以下を解消してから、ページを再読み込みしてください。');
        $body .= $reqList($checks);
        $body .= '<div class="btn-row">'
            . ($reqErrors === []
                ? '<a class="btn btn-primary btn-block" href="install.php?step=1">セットアップを開始' . ico('arrow') . '</a>'
                : '<a class="btn btn-primary btn-block" href="install.php">再読み込みして再チェック</a>')
            . '</div>';
    } elseif ($view === 'database') {
        $body = '<div class="iz-head">データベースに接続</div>'
            . '<div class="iz-headsub">接続情報を入力してください。MySQL の値は契約中の<b>レンタルサーバー管理画面（コントロールパネル）の「データベース」欄</b>で確認できます。</div>';
        if ($errors !== []) {
            $body .= $alert(
                'error',
                'データベースに接続できませんでした',
                'ホスト名・ポート・ユーザー名・パスワードをご確認ください。共有サーバーではホスト名が <code>localhost</code> ではなく専用ホスト名のことが多いです。',
                implode("\n", $errors),
            );
        }

        $chips = '';
        foreach ($hosts as $hh) {
            $chips .= '<button type="button" class="host-chip" data-id="' . h($hh['id']) . '" data-host="' . h($hh['host']) . '" data-db="' . h($hh['db']) . '" data-user="' . h($hh['user']) . '" data-note="' . h($hh['note']) . '">' . h($hh['label']) . '</button>';
        }

        $mysqlHidden = $adapterOld === 'sqlite' ? ' hidden' : '';
        $sqliteHidden = $adapterOld === 'sqlite' ? '' : ' hidden';

        $body .= '<form method="post" action="install.php?step=1" id="dbForm">'
            . '<div class="field"><label class="label" for="db_adapter">データベースの種類'
            . '<span class="tip" tabindex="0">?<span class="tip-body">通常は MySQL を選びます。SQLite はお試し・単一プロセス向けで、本番運用には推奨しません。</span></span></label>'
            . '<select id="db_adapter" name="db_adapter" class="select">'
            . '<option value="mysql"' . ($adapterOld === 'mysql' ? ' selected' : '') . '>MySQL（推奨・共有ホスティング）</option>'
            . '<option value="sqlite"' . ($adapterOld === 'sqlite' ? ' selected' : '') . '>SQLite（お試し・単一ファイル）</option>'
            . '</select></div>'
            . '<div id="sqliteNote"' . $sqliteHidden . '>'
            . '<div class="alert warn">' . ico('warn') . '<div class="a-body"><div class="a-title">SQLite はお試し向けです</div>'
            . '<div class="a-text">データは <code>var/nene_vault.sqlite</code> に保存されます。同時アクセスに弱いため、本番運用では MySQL を推奨します。</div></div></div></div>'
            . '<div id="mysqlFields"' . $mysqlHidden . '>'
            . '<div class="host-help"><div class="hh-q">' . ico('help') . 'お使いのレンタルサーバーは？</div>'
            . '<div class="hh-sub">選ぶと、ホスト名の<b>記入例</b>を自動入力します（実際の値はコントロールパネルでご確認ください）。</div>'
            . '<div class="host-chips" id="hostChips">' . $chips . '</div>'
            . '<button type="button" class="linkbtn cp-toggle" id="cpToggle">コントロールパネルのどこを見る？</button>'
            . '<div class="cp-diagram" id="cpDiagram" hidden>'
            . '<div class="cp-bar"><span class="dot"></span><span class="dot"></span><span class="dot"></span><span class="cp-url">https://cp.your-host.example/database</span></div>'
            . '<div class="cp-grid"><div class="cp-menu">'
            . '<div class="cp-mi"><span class="cp-bullet"></span>ドメイン</div><div class="cp-mi"><span class="cp-bullet"></span>メール</div>'
            . '<div class="cp-mi hot"><span class="cp-bullet"></span>データベース</div><div class="cp-mi"><span class="cp-bullet"></span>FTP</div><div class="cp-mi"><span class="cp-bullet"></span>SSL</div>'
            . '</div><div class="cp-body"><div class="cp-h">データベース情報</div><div class="cp-kv">'
            . '<span class="k">ホスト名</span><span class="v hl" id="cpHost">localhost</span>'
            . '<span class="k">データベース名</span><span class="v" id="cpDb">yourname_vault</span>'
            . '<span class="k">ユーザー名</span><span class="v" id="cpUser">yourname</span>'
            . '<span class="k">ポート</span><span class="v">3306</span>'
            . '</div><div class="cp-note" id="cpNote">契約中のレンタルサーバー管理画面（コントロールパネル）の「データベース」欄で確認できます。黄色の<b>ホスト名</b>を下のフォームにそのまま貼り付けてください。</div></div></div></div></div>'
            . '<div class="form-row2">'
            . '<div class="field"><label class="label" for="db_host">ホスト<span class="req">*</span>'
            . '<span class="tip" tabindex="0">?<span class="tip-body">データベースサーバーのアドレス。共有ホスティングでは <code>localhost</code> ではなく専用ホスト名のことが多いです。</span></span></label>'
            . '<input id="db_host" name="db_host" class="input mono" value="' . $old('db_host', 'localhost') . '" placeholder="例: mysqlXXX.phy.heteml.lan"></div>'
            . '<div class="field"><label class="label" for="db_port">ポート<span class="req">*</span>'
            . '<span class="tip" tabindex="0">?<span class="tip-body">通常は MySQL 既定の <code>3306</code> のままで問題ありません。</span></span></label>'
            . '<input id="db_port" name="db_port" class="input mono" value="' . $old('db_port', '3306') . '"></div>'
            . '</div>'
            . '<div class="field"><label class="label" for="db_name">データベース名<span class="req">*</span>'
            . '<span class="tip" tabindex="0">?<span class="tip-body">コントロールパネルで作成済みのデータベース名。空のデータベースを指定してください（既存データには触れません）。</span></span></label>'
            . '<input id="db_name" name="db_name" class="input mono" value="' . $old('db_name') . '" placeholder="例: yourname_vault">'
            . '<p class="hint">事前に作成した<b>空のデータベース</b>を指定します。テーブルはこのインストーラが作成します。</p></div>'
            . '<div class="field"><label class="label" for="db_user">ユーザー名<span class="req">*</span>'
            . '<span class="tip" tabindex="0">?<span class="tip-body">そのデータベースにアクセスできる MySQL ユーザー名。コントロールパネルの DB 情報に記載されています。</span></span></label>'
            . '<input id="db_user" name="db_user" class="input mono" value="' . $old('db_user') . '" placeholder="例: yourname_vault"></div>'
            . '<div class="field"><label class="label" for="db_password">パスワード<span class="opt">（サーバーによっては任意）</span>'
            . '<span class="tip" tabindex="0">?<span class="tip-body">上記 MySQL ユーザーのパスワード。<b>NeNe Vault のログインパスワードとは別物</b>です。</span></span></label>'
            . '<div class="pw-wrap"><input id="db_password" name="db_password" class="input mono" type="password" value="' . $old('db_password') . '" placeholder="••••••••">'
            . '<button type="button" class="pw-eye" data-pw="db_password" tabindex="-1" aria-label="パスワード表示切替">' . ico('eye') . '</button></div>'
            . '<p class="hint">サーバーの DB ユーザーのパスワード。<b>NeNe Vault のログインパスワードとは別物</b>です。</p></div>'
            . '</div>'
            . '<div class="btn-row"><a class="btn btn-ghost btn-back" href="install.php" aria-label="戻る">' . ico('back') . '</a>'
            . '<button type="submit" class="btn btn-primary">接続テストして次へ' . ico('arrow') . '</button></div>'
            . '</form>';
    } elseif ($view === 'app' || $view === 'failed') {
        $body = '<div class="iz-head">組織と管理者アカウントを作成</div>'
            . '<div class="iz-headsub">アーカイブを利用する組織と、最初にサインインする管理者アカウントを設定します。書類の保存先ディレクトリもここで決まります。</div>';
        if ($view === 'failed') {
            $body .= $alert('error', 'セットアップに失敗しました', 'データベースは作成済みの可能性があります。エラー内容を確認して再実行してください。', implode("\n", $messages));
        }
        if ($errors !== []) {
            $body .= $alert('error', '入力内容を確認してください', h(implode(' ', $errors)));
        }

        $body .= '<form method="post" action="install.php?step=2" id="appForm">'
            . '<div class="tenant-sec"><div class="ts-h">' . ico('org') . '組織</div></div>'
            . '<div class="field"><label class="label" for="org_name">組織名（会社名）<span class="req">*</span>'
            . '<span class="tip" tabindex="0">?<span class="tip-body">画面表示に使われる組織の正式名称です。後から変更できます。</span></span></label>'
            . '<input id="org_name" name="org_name" class="' . $inputClass('org_name') . '" value="' . $old('org_name') . '" placeholder="例: 株式会社ねね商事">'
            . $fieldErr('org_name', '画面表示に使われます（後から変更可）。')
            . '</div>'
            . '<div class="field"><label class="label" for="org_slug">組織スラッグ<span class="opt">（任意・英数字とハイフン）</span>'
            . '<span class="tip" tabindex="0">?<span class="tip-body">システム内部で組織を識別する短い英数字 ID。空欄なら組織名から自動生成します。シングルテナント構成（既定）ではこの組織が直接表示されます。</span></span></label>'
            . '<input id="org_slug" name="org_slug" class="' . $inputClass('org_slug') . ' mono" value="' . $old('org_slug') . '" placeholder="例: nene-shoji">'
            . $fieldErr('org_slug', '空欄で自動生成。小文字英数字とハイフンのみ。')
            . '</div>'
            . '<div class="field"><label class="label" for="storage_path">書類の保存先ディレクトリ<span class="opt">（既定: storage/vault）</span>'
            . '<span class="tip" tabindex="0">?<span class="tip-body">アップロードされた書類ファイルの保存先。<b>公開ディレクトリ（public_html）の外</b>を指定してください。相対パスはアプリのルート基準です。</span></span></label>'
            . '<input id="storage_path" name="storage_path" class="' . $inputClass('storage_path') . ' mono" value="' . $old('storage_path', 'storage/vault') . '">'
            . $fieldErr('storage_path', '公開ディレクトリの外に置きます（既定のままを推奨）。')
            . '</div>'
            . '<div class="field"><label class="label" for="admin_email">管理者メールアドレス<span class="req">*</span>'
            . '<span class="tip" tabindex="0">?<span class="tip-body">最初の管理者アカウントのログイン ID になります。</span></span></label>'
            . '<input id="admin_email" name="admin_email" type="email" class="' . $inputClass('admin_email') . '" value="' . $old('admin_email') . '" placeholder="例: admin@yourcompany.co.jp" required>'
            . $fieldErr('admin_email', 'このメールが<b>最初の管理者ログイン ID</b> になります。')
            . '</div>'
            . '<div class="field"><label class="label" for="admin_password">管理者パスワード<span class="opt">（12 文字以上）</span><span class="req">*</span>'
            . '<span class="tip" tabindex="0">?<span class="tip-body">12 文字以上。安全にハッシュ化して保存され、.env には書き込まれません。<b>DB 接続パスワードとは別物</b>です。</span></span></label>'
            . '<div class="pw-wrap"><input id="admin_password" name="admin_password" class="' . $inputClass('admin_password') . '" type="password" placeholder="12 文字以上" required minlength="12">'
            . '<button type="button" class="pw-eye" data-pw="admin_password" tabindex="-1" aria-label="パスワード表示切替">' . ico('eye') . '</button></div>'
            . $fieldErr('admin_password', '12 文字以上。<b>ハッシュ化して安全に保管</b>されます（.env には書き込みません）。')
            . '</div>'
            . '<div class="btn-row"><a class="btn btn-ghost btn-back" href="install.php?step=1" aria-label="戻る">' . ico('back') . '</a>'
            . '<button type="submit" class="btn btn-primary">インストールを実行' . ico('arrow') . '</button></div>'
            . '</form>';
    } else { // complete
        $body = '<div class="done-mark">' . ico('check') . '</div>'
            . '<div class="done-title">インストール完了</div>'
            . '<div class="done-sub">' . h($summary) . '</div>'
            . '<div class="sec-warn"><span class="sw-ico">' . ico('trash') . '</span><div>'
            . '<div class="sw-t">install.php は自動的に削除されました</div>'
            . '<div class="sw-d">このページを離れると再表示できません。もしファイルが残っている場合は、FTP またはファイルマネージャから<b>手動で削除</b>してください。</div>'
            . '</div></div>'
            . '<div class="next-h">次のステップ</div>'
            . '<ol class="next-list">'
            . '<li><span class="nl-n">1</span><div><b>管理画面にログイン</b><div class="nl-d">先ほど設定した管理者メール・パスワードで。</div></div></li>'
            . '<li><span class="nl-n">2</span><div><b>最初の受取書類をアップロード</b><div class="nl-d">PDF / JPEG / PNG。SHA-256 と保存期間が自動で記録されます。</div></div></li>'
            . '<li><span class="nl-n">3</span><div><b>保存要件を確認</b><div class="nl-d">電子帳簿保存法の検索要件（取引先・期間・金額）が最初から有効です。</div></div></li>'
            . '</ol>'
            . '<a class="btn btn-primary btn-block btn-lg" href="./">' . ico('login') . '管理画面にログイン</a>';
    }

    $vstepHtml = '';
    $hstepHtml = '';
    foreach ($vsteps as $i => $s) {
        $cls = $i === $stepIdx ? 'active' : ($i < $stepIdx ? 'done' : '');
        $vstepHtml .= '<li class="' . $cls . '"><div class="vs-rail"><span class="vs-dot">' . ($i < $stepIdx ? ico('check') : (string) ($i + 1)) . '</span><span class="vs-line"></span></div>'
            . '<div class="vs-body"><div class="vs-t">' . h($s['t']) . '</div><div class="vs-d">' . h($s['d']) . '</div></div></li>';
        $hstepHtml .= '<div class="hs ' . $cls . '">' . ($i + 1) . '. ' . h($s['t']) . '</div>';
    }

    $errFlag = $hasError || $view === 'failed' ? '1' : '0';
    $viewAttr = h($view);
    $mark = ico('mark');
    $shield = ico('shield');
    $server = ico('server');
    $oss = ico('oss');
    $warnIco = ico('warn');
    $css = installer_css();

    return <<<HTML
    <!DOCTYPE html>
    <html lang="ja">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>NeNe Vault — セットアップウィザード</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 42 42'%3E%3Crect width='42' height='42' rx='8' fill='%23233a5c'/%3E%3Crect x='10' y='8' width='22' height='26' rx='2.5' stroke='%23fff' fill='none' stroke-width='2.2'/%3E%3Cpath d='M15 15h12M15 20h12M15 25h7' stroke='%23c8a96a' stroke-width='2.2' stroke-linecap='round'/%3E%3C/svg%3E">
    <style>{$css}</style>
    </head>
    <body data-view="{$viewAttr}" data-error="{$errFlag}">
    <div class="iz"><div class="iz-stage">
      <aside class="iz-aside">
        <div class="iz-bs-top">
          <span class="mono-mark">{$mark}</span>
          <div><div class="abt-name">NeNe Vault</div><div class="abt-sub">Setup Wizard</div></div>
        </div>
        <div class="iz-bs-mid">
          <h2>受け取った書類を、<br>証拠能力ごと保管する。</h2>
          <p class="lead">電子帳簿保存法に対応した受取書類アーカイブ。SHA-256 検証・改ざん不可の版管理・完全な監査証跡。3 ステップでセットアップが完了します。</p>
          <ul class="vstep">{$vstepHtml}</ul>
        </div>
        <div class="iz-bs-foot">
          <div class="iz-trust">
            <span class="tb">{$shield}電子帳簿保存法対応</span>
            <span class="tb">{$server}セルフホスト</span>
            <span class="tb">{$oss}オープンソース（MIT）</span>
          </div>
          <div class="copy">© 2026 NeNe Vault — install.php</div>
        </div>
      </aside>
      <div class="iz-main">
        <div class="iz-form" id="izView">
          <div class="hstep">{$hstepHtml}</div>
          {$body}
        </div>
        <div class="iz-form iz-loading" id="izLoading" hidden>
          <div class="ld-h">インストールしています</div>
          <div class="ld-sub">スキーマ作成から初期データ投入までを順に実行しています。完了までこのページを開いたままにしてください。</div>
          <div class="ld-bar"><span id="ldBar"></span></div>
          <ul class="substeps" id="substeps"></ul>
          <div class="ld-warn">{$warnIco}このページを閉じたり、ボタンを二度押ししないでください。</div>
        </div>
      </div>
    </div></div>
    <script src="installer.js"></script>
    </body>
    </html>
    HTML;
}

/** Installer stylesheet — NeNe Vault identity: warm paper, deep ink, navy primary, brass accent. */
function installer_css(): string
{
    return <<<'CSS'
    :root{
      --font-sans:"Noto Sans JP",system-ui,-apple-system,"Hiragino Kaku Gothic ProN","Yu Gothic",sans-serif;
      --font-num:ui-monospace,"SFMono-Regular","Menlo","Consolas",monospace;
      --bg:oklch(97.2% 0.007 83);--surface:oklch(99.4% 0.004 83);--surface-sunk:oklch(95.4% 0.008 83);
      --border:oklch(90% 0.01 83);--border-strong:oklch(82% 0.014 83);
      --fg:oklch(25% 0.015 260);--fg-muted:oklch(48% 0.014 260);--fg-subtle:oklch(60% 0.012 260);--fg-faint:oklch(70% 0.01 260);
      --brand:oklch(38% 0.07 258);--brand-strong:oklch(31% 0.06 260);--brand-deep:oklch(24% 0.045 262);
      --brand-soft:oklch(94% 0.015 255);--on-brand:oklch(98.5% 0.005 83);
      --brass:oklch(72% 0.09 85);--brass-deep:oklch(58% 0.1 80);
      --ok:oklch(47% 0.07 155);--ok-soft:oklch(94.5% 0.028 155);--danger:oklch(49% 0.115 28);--danger-soft:oklch(95% 0.03 28);
      --warn:oklch(55% 0.075 75);--warn-soft:oklch(95.5% 0.03 83);
      --radius:8px;--radius-sm:6px;
      --ring:0 0 0 3px oklch(38% 0.07 258 / 0.18);
    }
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    html,body{height:100%}
    body{font-family:var(--font-sans);background:var(--bg);color:var(--fg);line-height:1.6;-webkit-font-smoothing:antialiased}
    code{font-family:var(--font-num);background:oklch(93.5% 0.01 83);padding:.05em .35em;border-radius:4px;font-size:.92em}
    .iz{min-height:100vh}
    .iz-stage{display:grid;grid-template-columns:minmax(380px,0.92fr) 1.08fr;min-height:100vh}
    .iz-aside{position:relative;overflow:hidden;color:oklch(93% 0.01 255);display:flex;flex-direction:column;justify-content:space-between;padding:48px 46px 38px;
      background:linear-gradient(157deg,var(--brand-strong) 0%,var(--brand-deep) 60%,oklch(20% 0.04 264) 100%)}
    .iz-aside::before{content:"";position:absolute;inset:0;pointer-events:none;background-image:linear-gradient(oklch(100% 0 0/0.05) 1px,transparent 1px),linear-gradient(90deg,oklch(100% 0 0/0.05) 1px,transparent 1px);background-size:40px 40px;mask-image:linear-gradient(150deg,#000 8%,transparent 80%)}
    .iz-aside>*{position:relative;z-index:1}
    .iz-bs-top{display:flex;align-items:center;gap:13px}
    .mono-mark{width:38px;height:36px;flex:none;color:var(--brass)}
    .mono-mark svg{width:100%;height:100%;display:block}
    .abt-name{font-size:18px;font-weight:700;color:#fff}
    .abt-sub{font-size:10.5px;color:oklch(76% 0.03 255);letter-spacing:.16em;text-transform:uppercase;margin-top:3px}
    .iz-bs-mid{max-width:400px}
    .iz-bs-mid h2{font-size:24px;font-weight:700;line-height:1.55;color:#fff;text-wrap:balance}
    .iz-bs-mid .lead{font-size:13px;color:oklch(82% 0.02 255);margin-top:14px;line-height:1.9}
    .vstep{list-style:none;margin:30px 0 0}
    .vstep li{display:flex;gap:14px}
    .vs-rail{display:flex;flex-direction:column;align-items:center;flex:none}
    .vs-dot{width:30px;height:30px;border-radius:50%;display:grid;place-items:center;font-family:var(--font-num);font-size:13px;font-weight:600;background:oklch(100% 0 0/0.10);color:oklch(85% 0.02 255);border:1px solid oklch(100% 0 0/0.20)}
    .vs-dot svg{width:14px;height:14px}
    .vs-line{width:2px;flex:1;min-height:22px;background:oklch(100% 0 0/0.16);margin:4px 0}
    .vstep li:last-child .vs-line{display:none}
    .vs-body{padding-top:3px;padding-bottom:18px}
    .vs-t{font-size:14px;font-weight:600;color:oklch(91% 0.012 255)}
    .vs-d{font-size:11.5px;color:oklch(70% 0.018 255);margin-top:2px}
    .vstep li.active .vs-dot{background:var(--brass);color:var(--brand-deep);border-color:var(--brass);box-shadow:0 0 0 5px oklch(100% 0 0/0.08)}
    .vstep li.active .vs-t{color:#fff}
    .vstep li.done .vs-dot{background:oklch(100% 0 0/0.16);color:var(--brass)}
    .vstep li.done .vs-line{background:var(--brass);opacity:.6}
    .iz-trust{display:flex;flex-wrap:wrap;gap:8px 14px}
    .tb{display:inline-flex;align-items:center;gap:6px;font-size:11px;color:oklch(78% 0.02 255)}
    .tb svg{width:13px;height:13px}
    .copy{font-size:10.5px;color:oklch(60% 0.018 256);margin-top:14px}
    .iz-main{display:flex;align-items:flex-start;justify-content:center;padding:56px 48px;overflow-y:auto}
    .iz-form{width:100%;max-width:560px}
    .hstep{display:none;gap:8px;margin-bottom:26px}
    .hs{flex:1;text-align:center;font-size:11.5px;font-weight:600;padding:8px 4px;border-radius:var(--radius-sm);color:var(--fg-faint);background:var(--surface-sunk);border:1px solid var(--border)}
    .hs.active{background:var(--brand);color:var(--on-brand);border-color:var(--brand)}
    .hs.done{background:var(--brand-soft);color:var(--brand-strong)}
    .hs.done::before{content:"✓ "}
    .iz-head{font-size:24px;font-weight:700;letter-spacing:.01em}
    .iz-headsub{font-size:13px;color:var(--fg-muted);margin:10px 0 24px;line-height:1.9}
    .alert{display:flex;gap:12px;padding:14px 16px;border-radius:var(--radius);margin-bottom:20px;font-size:13px;border:1px solid}
    .alert>svg{width:18px;height:18px;flex:none;margin-top:2px}
    .alert.ok{background:var(--ok-soft);border-color:color-mix(in oklch,var(--ok) 30%,transparent);color:var(--ok)}
    .alert.error{background:var(--danger-soft);border-color:color-mix(in oklch,var(--danger) 30%,transparent);color:var(--danger)}
    .alert.warn{background:var(--warn-soft);border-color:color-mix(in oklch,var(--warn) 35%,transparent);color:oklch(42% 0.07 70)}
    .a-title{font-weight:700}
    .a-text{margin-top:2px;color:inherit;opacity:.92;line-height:1.75}
    .alert details{margin-top:8px}
    .alert summary{cursor:pointer;font-size:12px;font-weight:600}
    .alert .det{font-family:var(--font-num);font-size:11.5px;white-space:pre-wrap;word-break:break-all;background:oklch(100% 0 0/0.55);border-radius:6px;padding:8px 10px;margin-top:6px}
    .reqs{list-style:none;margin:0 0 24px;border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}
    .reqs li{display:flex;gap:13px;padding:13px 16px;border-bottom:1px solid var(--border);background:var(--surface)}
    .reqs li:last-child{border-bottom:none}
    .reqs .ic{width:22px;height:22px;flex:none;border-radius:50%;display:grid;place-items:center;margin-top:2px}
    .reqs .ic svg{width:12px;height:12px}
    .reqs li.pass .ic{background:var(--ok-soft);color:var(--ok)}
    .reqs li.fail .ic{background:var(--danger-soft);color:var(--danger)}
    .rq-t{font-size:13.5px;font-weight:600}
    .rq-d{font-size:12px;color:var(--fg-subtle);font-family:var(--font-num)}
    .rq-fix{font-size:12px;color:var(--danger);margin-top:6px;line-height:1.7}
    .field{margin-bottom:18px}
    .label{display:flex;align-items:center;gap:6px;font-size:13px;font-weight:600;margin-bottom:7px}
    .req{color:var(--danger)}
    .opt{font-size:11px;font-weight:500;color:var(--fg-subtle)}
    .input,.select{width:100%;padding:10px 12px;font-size:14px;font-family:inherit;color:var(--fg);background:var(--surface);border:1px solid var(--border-strong);border-radius:var(--radius-sm);transition:border-color .15s, box-shadow .15s}
    .input:focus,.select:focus{outline:none;border-color:var(--brand);box-shadow:var(--ring)}
    .input.mono{font-family:var(--font-num);font-size:13.5px}
    .input.is-error{border-color:var(--danger);box-shadow:0 0 0 3px oklch(49% 0.115 28 / 0.15)}
    .hint{font-size:11.5px;color:var(--fg-subtle);margin-top:6px;line-height:1.7}
    .err-text{display:flex;align-items:center;gap:5px;font-size:12px;color:var(--danger);font-weight:600;margin-top:6px}
    .err-text svg{width:13px;height:13px}
    .form-row2{display:grid;grid-template-columns:1fr 140px;gap:14px}
    .tip{position:relative;display:inline-grid;place-items:center;width:16px;height:16px;border-radius:50%;background:var(--surface-sunk);border:1px solid var(--border-strong);color:var(--fg-subtle);font-size:10.5px;font-weight:700;cursor:help}
    .tip-body{position:absolute;left:50%;bottom:calc(100% + 8px);transform:translateX(-50%);width:260px;background:var(--brand-deep);color:oklch(93% 0.008 255);font-size:11.5px;font-weight:400;line-height:1.7;padding:10px 12px;border-radius:8px;opacity:0;pointer-events:none;transition:opacity .12s;z-index:10}
    .tip:hover .tip-body,.tip:focus .tip-body{opacity:1}
    .pw-wrap{position:relative}
    .pw-wrap .input{padding-right:42px}
    .pw-eye{position:absolute;right:6px;top:50%;transform:translateY(-50%);width:30px;height:30px;display:grid;place-items:center;background:none;border:0;border-radius:6px;color:var(--fg-subtle);cursor:pointer}
    .pw-eye:hover{background:var(--surface-sunk)}
    .pw-eye svg{width:17px;height:17px}
    .btn-row{display:flex;gap:10px;margin-top:26px}
    .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:12px 22px;font-size:14px;font-weight:700;font-family:inherit;border-radius:var(--radius);border:1px solid transparent;cursor:pointer;text-decoration:none;transition:filter .15s}
    .btn svg{width:16px;height:16px}
    .btn-primary{background:var(--brand);color:var(--on-brand);flex:1}
    .btn-primary:hover{filter:brightness(1.1)}
    .btn-ghost{background:var(--surface);border-color:var(--border-strong);color:var(--fg-muted)}
    .btn-block{width:100%}
    .btn-lg{padding:14px 24px;font-size:15px}
    .btn-back{flex:none;padding:12px 14px}
    .host-help{background:var(--surface-sunk);border:1px solid var(--border);border-radius:var(--radius);padding:14px 16px;margin-bottom:20px}
    .hh-q{display:flex;align-items:center;gap:7px;font-size:13px;font-weight:700}
    .hh-q svg{width:15px;height:15px;color:var(--fg-subtle)}
    .hh-sub{font-size:11.5px;color:var(--fg-subtle);margin:4px 0 10px}
    .host-chips{display:flex;flex-wrap:wrap;gap:7px}
    .host-chip{padding:6px 13px;font-size:12px;font-weight:600;font-family:inherit;background:var(--surface);border:1px solid var(--border-strong);border-radius:99px;color:var(--fg-muted);cursor:pointer}
    .host-chip.on{background:var(--brand);border-color:var(--brand);color:var(--on-brand)}
    .linkbtn{background:none;border:0;padding:0;font-family:inherit;font-size:12px;font-weight:600;color:var(--brass-deep);cursor:pointer;margin-top:12px;display:inline-flex;align-items:center;gap:4px}
    .linkbtn svg{width:13px;height:13px;transition:transform .15s}
    .linkbtn.open svg{transform:rotate(180deg)}
    .cp-diagram{margin-top:12px;border:1px solid var(--border-strong);border-radius:var(--radius);overflow:hidden;background:var(--surface)}
    .cp-bar{display:flex;align-items:center;gap:5px;padding:7px 12px;background:var(--surface-sunk);border-bottom:1px solid var(--border)}
    .cp-bar .dot{width:8px;height:8px;border-radius:50%;background:var(--border-strong)}
    .cp-url{font-family:var(--font-num);font-size:10.5px;color:var(--fg-faint);margin-left:8px}
    .cp-grid{display:grid;grid-template-columns:120px 1fr}
    .cp-menu{border-right:1px solid var(--border);padding:10px 0}
    .cp-mi{display:flex;align-items:center;gap:7px;font-size:11px;color:var(--fg-subtle);padding:6px 12px}
    .cp-mi.hot{background:var(--brand-soft);color:var(--brand-strong);font-weight:700}
    .cp-bullet{width:5px;height:5px;border-radius:50%;background:currentColor;opacity:.5}
    .cp-body{padding:12px 16px}
    .cp-h{font-size:12px;font-weight:700;margin-bottom:8px}
    .cp-kv{display:grid;grid-template-columns:auto 1fr;gap:4px 14px;font-size:11.5px}
    .cp-kv .k{color:var(--fg-subtle)}
    .cp-kv .v{font-family:var(--font-num)}
    .cp-kv .v.hl{background:oklch(93% 0.06 85);border-radius:4px;padding:0 5px;font-weight:700}
    .cp-note{font-size:11px;color:var(--fg-subtle);margin-top:9px;line-height:1.7}
    .tenant-sec{margin-bottom:10px}
    .ts-h{display:flex;align-items:center;gap:7px;font-size:13px;font-weight:700;margin-bottom:10px}
    .ts-h svg{width:15px;height:15px;color:var(--fg-subtle)}
    .ld-h{font-size:22px;font-weight:700}
    .ld-sub{font-size:13px;color:var(--fg-muted);margin:8px 0 22px;line-height:1.8}
    .ld-bar{height:6px;border-radius:99px;background:var(--surface-sunk);overflow:hidden;margin-bottom:20px}
    .ld-bar span{display:block;height:100%;width:0;border-radius:99px;background:var(--brand);transition:width .5s}
    .substeps{list-style:none}
    .substeps li{display:flex;align-items:center;gap:13px;padding:12px 0;border-bottom:1px solid var(--border)}
    .substeps li:last-child{border-bottom:none}
    .ss-ic{width:22px;height:22px;flex:none;border-radius:50%;display:grid;place-items:center;background:var(--surface-sunk);color:var(--ok)}
    .ss-ic svg{width:12px;height:12px}
    .ss-t{font-size:13.5px;font-weight:600}
    .ss-d{font-size:11.5px;color:var(--fg-subtle)}
    .ss-meta{margin-left:auto;font-size:11px;font-weight:600;color:var(--fg-faint)}
    .ss-done .ss-ic{background:var(--ok-soft)}
    .ss-done .ss-meta{color:var(--ok)}
    .ss-pending{opacity:.5}
    .spinner{width:13px;height:13px;border:2px solid var(--border-strong);border-top-color:var(--brand);border-radius:50%;animation:spin .7s linear infinite;display:inline-block}
    @keyframes spin{to{transform:rotate(360deg)}}
    .ld-warn{display:flex;align-items:center;gap:8px;font-size:12px;color:var(--warn);margin-top:18px}
    .ld-warn svg{width:14px;height:14px}
    .done-mark{width:64px;height:64px;border-radius:50%;background:var(--ok-soft);color:var(--ok);display:grid;place-items:center;margin:8px 0 18px;animation:pop .35s ease-out}
    .done-mark svg{width:30px;height:30px}
    @keyframes pop{0%{transform:scale(.6);opacity:0}100%{transform:scale(1);opacity:1}}
    .done-title{font-size:24px;font-weight:700}
    .done-sub{font-size:13.5px;color:var(--fg-muted);margin:10px 0 22px;line-height:1.9}
    .sec-warn{display:flex;gap:13px;background:var(--danger-soft);border:1px solid color-mix(in oklch,var(--danger) 30%,transparent);border-radius:var(--radius);padding:15px 17px;margin-bottom:24px}
    .sw-ico{width:20px;height:20px;flex:none;color:var(--danger);margin-top:2px}
    .sw-ico svg{width:100%;height:100%}
    .sw-t{font-size:13.5px;font-weight:700;color:var(--danger)}
    .sw-d{font-size:12.5px;color:oklch(40% 0.08 28);margin-top:3px;line-height:1.75}
    .next-h{font-size:13px;font-weight:700;margin-bottom:10px}
    .next-list{list-style:none;margin-bottom:26px}
    .next-list li{display:flex;gap:12px;padding:10px 0;border-bottom:1px solid var(--border);font-size:13px}
    .next-list li:last-child{border-bottom:none}
    .nl-n{width:22px;height:22px;flex:none;border-radius:50%;background:var(--brand-soft);color:var(--brand-strong);display:grid;place-items:center;font-family:var(--font-num);font-size:11.5px;font-weight:700}
    .nl-d{font-size:12px;color:var(--fg-subtle);margin-top:2px;font-weight:400}
    [hidden]{display:none !important}
    @media (max-width:900px){
      .iz-stage{grid-template-columns:1fr}
      .iz-aside{padding:30px 26px 26px;flex-direction:row;align-items:center;justify-content:space-between;gap:16px}
      .iz-aside .iz-bs-mid,.iz-aside .iz-bs-foot{display:none}
      .iz-main{padding:32px 22px 48px}
      .hstep{display:flex}
    }
    @media (max-width:520px){.form-row2{grid-template-columns:1fr}.iz-head,.done-title{font-size:21px}}
    @media (prefers-reduced-motion:reduce){.done-mark{animation:none}}
    CSS;
}

// -------------------------------------------------------------------------
// CLI: pattern export for a future design handoff
// -------------------------------------------------------------------------

if (PHP_SAPI === 'cli') {
    $argvList = is_array($_SERVER['argv'] ?? null) ? array_values($_SERVER['argv']) : [];
    if (($argvList[1] ?? '') !== '--export-patterns') {
        fwrite(STDERR, "Usage: php public_html/install.php --export-patterns [output-dir]\n");
        exit(1);
    }
    $outDir = is_string($argvList[2] ?? null) && $argvList[2] !== '' ? $argvList[2] : $root . '/var/installer-patterns';
    if (!is_dir($outDir) && !mkdir($outDir, 0775, true)) {
        fwrite(STDERR, "Cannot create output dir: {$outDir}\n");
        exit(1);
    }

    $passChecks = requirement_checks($root);
    foreach ($passChecks as &$c) {
        $c['ok'] = true;
    }
    unset($c);
    $failChecks = $passChecks;
    $failChecks[0] = ['label' => 'PHP ' . MIN_PHP . ' 以上', 'detail' => '現在: 8.1.27', 'ok' => false, 'fix' => 'サーバーのコントロールパネルで使用する PHP のバージョンを 8.4 以上に切り替えてください。'];

    $patterns = [
        '01-requirements-pass' => ['view' => 'requirements', 'checks' => $passChecks, 'reqErrors' => []],
        '02-requirements-fail' => ['view' => 'requirements', 'checks' => $failChecks, 'reqErrors' => [$failChecks[0]]],
        '03-database' => ['view' => 'database'],
        '04-database-error' => ['view' => 'database', 'errors' => ["DB 接続エラー: SQLSTATE[HY000] [1045] Access denied for user '_nene_vault'@'10.0.0.8' (using password: YES)"], 'old' => ['db_adapter' => 'mysql', 'db_host' => 'mysql401.phy.heteml.lan', 'db_port' => '3306', 'db_name' => '_nene_vault', 'db_user' => '_nene_vault']],
        '05-database-sqlite' => ['view' => 'database', 'old' => ['db_adapter' => 'sqlite']],
        '06-app' => ['view' => 'app'],
        '07-app-errors' => ['view' => 'app', 'errors' => ['入力内容に誤りがあります。'], 'fieldErrors' => ['org_name' => '組織名を入力してください。', 'admin_email' => '有効なメールアドレスを入力してください。', 'admin_password' => 'パスワードは 12 文字以上にしてください。'], 'old' => ['org_slug' => 'nene-shoji', 'admin_email' => 'admin@example']],
        '08-setup-failed' => ['view' => 'failed', 'messages' => ['Running Phinx migrations (MySQL)...', 'SQLSTATE[42S01]: Base table or view already exists']],
        '09-complete' => ['view' => 'complete', 'summary' => '組織「株式会社ねね商事」と管理者 admin@nene-shoji.co.jp を作成しました。管理画面にログインして、最初の受取書類をアップロードしましょう。'],
        '10-blocked' => ['view' => 'blocked', 'blockedMessage' => 'NeNe Vault は既にインストールされています。install.php を削除してください。'],
    ];

    foreach ($patterns as $name => $state) {
        /** @var array{view: string} $state */
        file_put_contents($outDir . '/' . $name . '.html', render_installer_page($state));
        fwrite(STDOUT, "  {$name}.html\n");
    }
    copy(__DIR__ . '/installer.js', $outDir . '/installer.js');
    fwrite(STDOUT, "Exported to {$outDir}\n");
    exit(0);
}

// -------------------------------------------------------------------------
// Runtime flow
// -------------------------------------------------------------------------

session_start();

$method = is_string($_SERVER['REQUEST_METHOD'] ?? null) ? $_SERVER['REQUEST_METHOD'] : 'GET';
$step = (int) (is_string($_GET['step'] ?? null) ? $_GET['step'] : '0');

/** @var list<string> $errors */
$errors = [];
/** @var array<string, string> $fieldErrors */
$fieldErrors = [];
/** @var list<string> $setupMessages */
$setupMessages = [];
$success = false;
$summary = '';

$vendorPresent = is_file($root . '/vendor/autoload.php');

// Re-installation guard (marker + DB probe; wired only once vendor exists —
// without it the requirements screen blocks first anyway).
$reinstallGuard = null;
if ($vendorPresent) {
    require_once $root . '/vendor/autoload.php';
    $reinstallGuard = new ReInstallationGuard(
        $marker,
        DatabaseProvisioningProbe::fromEnvFile($envFile, $root),
    );
    if ($reinstallGuard->isBlocked()) {
        refuse_install('NeNe Vault は既にインストールされています。再インストールするには var/.installed と既存データベースを削除してください。');
    }
}

$checks = requirement_checks($root);
$reqErrors = array_values(array_filter($checks, static fn (array $c): bool => !$c['ok']));

if ($method === 'POST' && $reqErrors === []) {
    if ($step === 1) {
        // ---- Database step: validate → connection test → carry in session → PRG
        $adapter = post('db_adapter') === 'sqlite' ? 'sqlite' : 'mysql';
        $dbHost = post('db_host') !== '' ? post('db_host') : 'localhost';
        $dbPort = post('db_port') !== '' ? post('db_port') : '3306';
        $dbName = post('db_name');
        $dbUser = post('db_user');
        $dbPass = post_raw('db_password');

        if ($adapter === 'mysql' && ($dbName === '' || $dbUser === '')) {
            $errors[] = 'データベース名とユーザー名は必須です。';
        }

        if ($errors === []) {
            if ($adapter === 'mysql') {
                try {
                    new PDO(
                        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
                        $dbUser,
                        $dbPass,
                        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5],
                    );
                } catch (PDOException $e) {
                    $errors[] = 'DB 接続エラー: ' . $e->getMessage();
                }
            }

            if ($errors === []) {
                $_SESSION['db'] = [
                    'adapter' => $adapter,
                    'host' => $dbHost,
                    'port' => $dbPort,
                    // SQLite gets an ABSOLUTE path: a relative DB_NAME resolves
                    // against the web server's CWD (the docroot) at runtime and
                    // breaks the app after install (#120).
                    'name' => $adapter === 'sqlite' ? $root . '/var/nene_vault.sqlite' : $dbName,
                    'user' => $dbUser,
                    'password' => $dbPass,
                ];
                header('Location: install.php?step=2');
                exit;
            }
        }
    } elseif ($step === 2) {
        // ---- App step: re-guard, validate, write .env, run setup ------------
        if ($reinstallGuard?->isBlocked() === true) {
            refuse_install('NeNe Vault は既にインストールされています。install.php を削除してください。');
        }

        $orgName = post('org_name');
        $orgSlug = post('org_slug');
        $storagePath = post('storage_path') !== '' ? post('storage_path') : 'storage/vault';
        $adminEmail = post('admin_email');
        $adminPassword = post_raw('admin_password');

        if ($orgName === '') {
            $fieldErrors['org_name'] = '組織名を入力してください。';
        }
        if ($orgSlug !== '' && preg_match('/^[a-z0-9][a-z0-9-]*$/', $orgSlug) !== 1) {
            $fieldErrors['org_slug'] = 'スラッグは小文字英数字とハイフンのみ使えます。';
        }
        if ($adminEmail === '') {
            $fieldErrors['admin_email'] = 'メールアドレスを入力してください。';
        } elseif (filter_var($adminEmail, FILTER_VALIDATE_EMAIL) === false) {
            $fieldErrors['admin_email'] = '有効なメールアドレスを入力してください。';
        }
        if ($adminPassword === '') {
            $fieldErrors['admin_password'] = 'パスワードを入力してください。';
        } elseif (strlen($adminPassword) < 12) {
            $fieldErrors['admin_password'] = 'パスワードは 12 文字以上にしてください。';
        }

        if ($fieldErrors !== []) {
            $errors[] = '入力内容に誤りがあります。';
        }

        $db = is_array($_SESSION['db'] ?? null) ? $_SESSION['db'] : [];
        if ($errors === [] && $db === []) {
            $errors[] = 'データベース設定が見つかりません。ステップ 1 からやり直してください。';
        }

        if ($errors === []) {
            if ($orgSlug === '') {
                $derived = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($orgName)), '-');
                $orgSlug = $derived !== '' ? $derived : 'default';
            }

            try {
                $envValues = InstallEnvironment::values(
                    jwtSecret: EnvironmentWriter::generateSecret(32),
                    storagePath: $storagePath,
                    orgSlug: $orgSlug,
                    orgName: $orgName,
                    adminEmail: $adminEmail,
                    db: $db,
                );
                (new EnvironmentWriter())->write($envFile, $envValues);

                // The admin password is handed to setup in memory, never written to .env.
                $result = run_setup($root, $adminPassword);
                $setupMessages = $result['messages'];

                if ($result['ok']) {
                    $reinstallGuard?->markInstalled(date('c'));
                    $success = true;
                    $summary = '組織「' . $orgName . '」と管理者 ' . $adminEmail . ' を作成しました。管理画面にログインして、最初の受取書類をアップロードしましょう。';
                    unset($_SESSION['db']);
                    // Self-unlink: a completed install must not leave the installer behind.
                    @unlink(__FILE__);
                }
            } catch (Throwable $e) {
                $errors[] = 'セットアップに失敗しました: ' . $e->getMessage();
            }
        }
    }
}

/**
 * Vault's proven setup path, unchanged from the previous root installer:
 * schema via docker/bootstrap-schema.php (SQLite) or DatabaseSchemaApplier
 * (MySQL, in-process), then docker/seed-initial.php (reads env; the admin
 * password travels via putenv only).
 *
 * @return array{ok: bool, messages: list<string>}
 */
function run_setup(string $root, string $adminPassword): array
{
    $messages = [];

    $env = [];
    foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $env[trim($parts[0])] = trim($parts[1], " \t\"'");
        }
    }

    foreach ($env as $k => $v) {
        putenv($k . '=' . $v);
        $_ENV[$k] = $v;
    }
    putenv('ADMIN_PASSWORD=' . $adminPassword);
    $_ENV['ADMIN_PASSWORD'] = $adminPassword;

    $adapter = $env['DB_ADAPTER'] ?? 'sqlite';

    if ($adapter === 'sqlite') {
        $dbName = $env['DB_NAME'] ?? 'var/nene_vault.sqlite';
        $dbPath = str_starts_with($dbName, '/') ? $dbName : $root . '/' . $dbName;
        if (!is_dir(dirname($dbPath))) {
            mkdir(dirname($dbPath), 0755, true);
        }
        $messages[] = 'Bootstrapping SQLite schema at ' . $dbPath;
        try {
            $bootstrapScript = $root . '/docker/bootstrap-schema.php';
            if (!file_exists($bootstrapScript)) {
                return ['ok' => false, 'messages' => [...$messages, 'bootstrap-schema.php not found']];
            }
            // The bootstrap script resolves a relative DB_NAME against CWD.
            $cwd = getcwd();
            chdir($root);
            try {
                require $bootstrapScript;
            } finally {
                if (is_string($cwd)) {
                    chdir($cwd);
                }
            }
            $messages[] = 'Schema bootstrapped.';
        } catch (Throwable $e) {
            return ['ok' => false, 'messages' => [...$messages, 'Schema error: ' . $e->getMessage()]];
        }
    } else {
        $messages[] = 'Running Phinx migrations (MySQL)...';
        try {
            $migrationOutput = (new DatabaseSchemaApplier())->apply(new PhinxConfig([
                'paths' => ['migrations' => $root . '/database/migrations'],
                'environments' => [
                    'default_environment' => 'install',
                    'install' => [
                        'adapter' => 'mysql',
                        'host' => $env['DB_HOST'] ?? '127.0.0.1',
                        'port' => (int) ($env['DB_PORT'] ?? 3306),
                        'name' => $env['DB_NAME'] ?? 'nene_vault',
                        'user' => $env['DB_USER'] ?? '',
                        'pass' => $env['DB_PASSWORD'] ?? '',
                        'charset' => $env['DB_CHARSET'] ?? 'utf8mb4',
                    ],
                ],
                // Keep in step with phinx.php (creation-ordered migrations).
                'version_order' => 'creation',
            ]));
            foreach (explode("\n", trim($migrationOutput)) as $line) {
                if ($line !== '') {
                    $messages[] = $line;
                }
            }
            $messages[] = 'Migrations complete.';
        } catch (Throwable $e) {
            return ['ok' => false, 'messages' => [...$messages, $e->getMessage()]];
        }
    }

    $messages[] = 'Seeding initial data...';
    try {
        $seedScript = $root . '/docker/seed-initial.php';
        if (!file_exists($seedScript)) {
            return ['ok' => false, 'messages' => [...$messages, 'seed-initial.php not found']];
        }
        $cwd = getcwd();
        chdir($root);
        try {
            require $seedScript;
        } finally {
            if (is_string($cwd)) {
                chdir($cwd);
            }
        }
        $messages[] = 'Seed complete.';
    } catch (Throwable $e) {
        return ['ok' => false, 'messages' => [...$messages, 'Seed error: ' . $e->getMessage()]];
    }

    return ['ok' => true, 'messages' => $messages];
}

// Decide the view. Failed requirements always block on the requirements page.
if ($reqErrors !== []) {
    $view = 'requirements';
} elseif ($success) {
    $view = 'complete';
} elseif ($setupMessages !== [] && !$success && $step === 2 && $errors === [] && $fieldErrors === []) {
    $view = 'failed';
} elseif ($step === 2) {
    $view = 'app';
} elseif ($step === 1) {
    $view = 'database';
} else {
    $view = 'requirements';
}

// Preserve submitted values on re-render.
$oldValues = [];
foreach (['db_adapter', 'db_host', 'db_port', 'db_name', 'db_user', 'db_password', 'org_name', 'org_slug', 'storage_path', 'admin_email'] as $key) {
    $value = $key === 'db_password' ? post_raw($key) : post($key);
    if ($value !== '') {
        $oldValues[$key] = $value;
    }
}

echo render_installer_page([
    'view' => $view,
    'checks' => $checks,
    'reqErrors' => $reqErrors,
    'errors' => $errors,
    'fieldErrors' => $fieldErrors,
    'old' => $oldValues,
    'messages' => $setupMessages,
    'summary' => $summary,
]);
