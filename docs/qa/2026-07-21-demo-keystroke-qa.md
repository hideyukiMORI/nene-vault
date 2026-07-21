# デモ打鍵QA 仕様書 — NeNe Vault（2026-07-21）

- **制定**: 施主 hide（07-21）「正常系・異常系の全ルートを設定→テスト仕様書に残し→実行結果も記録」
- **体制**: 仕様起草＋実行 = Vault リナ／観点枠＋敵対的レビュー = 統合リナ（hub, Fable）。二段構え（起草→hub 査読→実行）を必須とする。
- **テンプレ**: `_work/reports/2026-07-21-demo-qa-template.md`（hub 管理・観点カタログ A〜F の正本）
- **将来接続**: 本仕様書は T2（Playwright スクリプト化）の種。シナリオ ID（`VLT-<観点>-<連番>`）は e2e 移植時にそのまま spec 名になる前提で採番する。
- **Issue**: #275（起草のみ・実行は hub 承認後）

> **本ドキュメントは起草段階。** §2 の各シナリオ「結果／証拠」欄と §3 実行記録は、hub のレビュー PASS 後にブラウザ打鍵で埋める。現時点では手順と期待結果のみを確定させる。

---

## 0. スコープと安全規範

- **対象は公開デモ環境のみ**（`https://vault.ayane.co.jp`）。本番顧客データには触れない。書き込み系シナリオは nightly reset される demo org 内で完結させる。
- 破壊的操作（void・全件変更）は「デモとして見せてよい範囲」でのみ実行。Vault は **hard-delete 禁止**なので「削除」は存在せず、ライフサイクルの終端は **void（→restore 可）**。この前提を各シナリオに明記する。
- **実行中はデモへのデプロイ・データ操作を凍結**（hub 管理・現在 施主の打鍵テストのため凍結中）。本仕様書の起草は repo 内ドキュメント作業のみで凍結対象外。
- 実行記録に **ビルド SHA・実行日時（TZ 明記）・ブラウザ/バージョン・画面幅・デモ URL** を必ず残す（§3）。

### 0.1 起草時に判明した構成前提（hub 査読の要確認事項）

打鍵前にフロント実装を精査（`frontend/src` 全域）した結果、テンプレ既定と実装が食い違う3点を先に固定しておく。**hub はここを最初に確認してほしい**。

| # | テンプレ既定 | Vault 実装の実際 | 本仕様書での扱い |
|---|---|---|---|
| P-1 | A-6 `/demo/standard` `/demo/guided` が SPA ルート | React router（`src/app/router.tsx`）に `/demo/*` は**無い**。両者は **PHP 側のデモ入口**（disposable-org 起動点・#141）で、そこから SPA を bootstrap する | 公開デモ環境に対する打鍵なので**在庫として A-6 に採用**。ただし「SPA ルートではなく配信入口」と注記（VLT-A6-*） |
| P-2 | E-1 light/dark テーマ切替 | **ランタイムのテーマトグルは存在しない**。テーマはビルド時 CSS 固定（`shared/ui/theme/active.css`・`prefers-color-scheme`/`data-theme`/トグルUI いずれも無し） | E-1 は**該当なし（理由付き）**。§1 対応表参照 |
| P-3 | — | **catch-all 404 ルートが無い**。未知 URL は React Router 既定エラーUI（"Unexpected Application Error / 404"）に落ち、意匠付き `ForbiddenPage` ではない | **発見候補**として D-2・F-3 にシナリオ化（既定エラーUIが営業品質上許容かを判定） |

---

## 1. 観点対応表（漏れ防止の網・hub 査読はここを機械的に突く）

観点カタログ A〜F の**全項目**について「該当シナリオ ID／該当なし＋理由」を列挙する。

### A. 正常系
| 項目 | 該当シナリオ / 該当なし理由 |
|---|---|
| A-1 全エンティティ CRUD 一巡 | VLT-A1-01（document: 登録→閲覧→編集→void→restore）, VLT-A1-02（user: 招待→一覧→delete。**update UI 無し**を明記）, VLT-A1-03（vault-settings: singleton 更新のみ・create/delete 無し） |
| A-2 一覧（検索/フィルタ/ソート/ページング/0件/大量） | VLT-A2-01（documents 検索・フィルタ）, VLT-A2-02（documents ページング 20/頁）, VLT-A2-03（documents 0件 EmptyState）, VLT-A2-04（audit フィルタ＋reset＋ページング＋0件）, VLT-A2-05（users ページング・0件）。**client-side ソート無し**（サーバ順・列ヘッダソート UI 不在）を明記 |
| A-3 主要業務フロー（売りの導線） | VLT-A3-01（受領文書アップロード→sha256/version 確認→ダウンロードで SHA 一致→履歴記録）, VLT-A3-02（OCR suggest→metadata prefill→確定）, VLT-A3-03（manifest CSV / export ZIP 出力＝電帳法の"売り"） |
| A-4 ダッシュボード・集計値の整合 | **該当なし**: HomePage は capability フィルタ済みクイックリンクのみで集計値・ダッシュボードを持たない（`pages/HomePage.tsx`）。唯一の数値整合は一覧の総件数↔ページング → VLT-A2-02/-05 で兼ねる |
| A-5 ナビ全リンク一巡 | VLT-A5-01（AppShell ナビ全項目＋詳細往復＋パンくず/戻る）, VLT-A5-02（HomePage クイックリンク→各ページ） |
| A-6 デモ固有導線 | VLT-A6-01（/demo/standard: disposable-org・admin seat・TTL 3h・初回オンボーディング）, VLT-A6-02（/demo/guided: fixed viewer seat）。P-1 注記あり |

### B. 異常系 — 入力
| 項目 | 該当シナリオ / 該当なし理由 |
|---|---|
| B-1 境界値 | VLT-B1-01（settings retention_years: 6=下限未満/7=最小/10=警告閾値/99=最大/100=上限超）, VLT-B1-02（amount_cents 0/負数/巨大値）, VLT-B1-03（search クロスフィールド from>to・min>max=**client 検証無し**でサーバ送出） |
| B-2 空・必須欠落・空白のみ | VLT-B2-01（upload file 未選択）, VLT-B2-02（upload counterparty_name 空/空白）, VLT-B2-03（user create email/password 空・password<8）, VLT-B2-04（void void_reason 空） |
| B-3 型不正 | VLT-B3-01（login email 不正形式）, VLT-B3-02（date 欄に不正形式・native date widget 迂回）, VLT-B3-03（amount type=number に文字列） |
| B-4 多バイト・絵文字・RTL・HTML/スクリプト | VLT-B4-01（counterparty_name に `<script>alert(1)</script>` → 一覧・詳細・audit diff の表示エスケープ）, VLT-B4-02（絵文字・RTL・多バイト tags/counterparty） |
| B-5 過長入力 | VLT-B5-01（counterparty_name・tags 上限超/巨大貼付）, VLT-B5-02（void_note 巨大テキスト） |
| B-6 二重送信・連打 | VLT-B6-01（upload/save 連打→saving/uploading 中の多重送信防止）, VLT-B6-02（user delete confirm 連打） |

### C. 異常系 — 認証・権限・境界
| 項目 | 該当シナリオ / 該当なし理由 |
|---|---|
| C-1 未ログイン保護 URL 直叩き | VLT-C1-01（未ログインで /documents /audit /users /settings /export → AuthGate が LoginForm を **in-place** 表示・URL 維持・リダイレクトなし） |
| C-2 存在しない/他org ID 直叩き | VLT-C2-01（/documents/{不在ULID}→problem.document_not_found）, VLT-C2-02（/documents/{他orgULID}→404/403・org 越え情報漏えい無し）, VLT-C2-03（不正形式 /documents/abc） |
| C-3 権限別表示 | VLT-C3-01（viewer: ナビ Documents のみ）, VLT-C3-02（member: void/edit ボタンは **UI 非ゲートで表示**→submit で 403）, VLT-C3-03（viewer が /users 直叩き→API 403→/forbidden ハードリダイレクト）, VLT-C3-04（admin↔superadmin 差＝ManageOrganizations のみ） |
| C-4 セッション切れ後の操作 | VLT-C4-01（期限切れ後の操作→transport 401→session クリア→AuthGate が LoginForm・入力データ喪失有無） |
| C-5 ログアウト→戻るでの閲覧可否 | VLT-C5-01（logout→ブラウザ戻る→保護ページのキャッシュ露出有無・AuthGate 再ガード） |

### D. 異常系 — 遷移・状態
| 項目 | 該当シナリオ / 該当なし理由 |
|---|---|
| D-1 入力途中のリロード/戻る/進む | VLT-D1-01（upload/metadata modal 入力途中でリロード・戻る→喪失警告有無） |
| D-2 深いリンク直行 | VLT-D2-01（/documents/:id ブックマーク直開・未ログイン/ログイン別）, VLT-D2-02（未知 URL /nonexistent → **カスタム404 無し**・React Router 既定エラーUI＝P-3 発見候補） |
| D-3 複数タブ同時操作 | VLT-D3-01（同一文書を 2 タブで metadata 編集競合→後勝ち/エラーの挙動） |
| D-4 遅い回線・読み込み中の連打 | VLT-D4-01（DevTools throttle で loading 状態の壊れ・多重リクエスト） |

### E. 表示・国際化・テーマ
| 項目 | 該当シナリオ / 該当なし理由 |
|---|---|
| E-1 テーマ切替 light/dark | **該当なし**: ランタイムのテーマトグルが実装に存在しない（ビルド時 CSS 固定・P-2）。将来トグルを入れたら本項目を復活させる |
| E-2 レスポンシブ | VLT-E2-01（375px/768px でナビ・テーブル・モーダル・横スクロール発生有無） |
| E-3 言語切替 ja/en | VLT-E3-01（AppShell topbar・Login・Forbidden の切替→未訳キー生露出・レイアウト崩れ・native date widget 言語＝documentElement.lang 追随） |
| E-4 日時・タイムゾーン表示（#228 直結） | VLT-E4-01（同一文書内 3 系統混在: uploaded_at=formatDateTime ローカルTZ／transaction_date・retention_expires_at=formatDate 生UTC日付／DocumentTable uploaded_at=.slice UTC日付）, VLT-E4-02（audit created_at=ローカルTZ × 文書日付=UTC の混在をブラウザ TZ 変更で再現）, VLT-E4-03（users created_at=.slice UTC）。**発見は #228 実データ最終GOへ必ず記録** |
| E-5 通貨・数値・単位の書式 | VLT-E5-01（amount 桁区切り・JPY マイナー単位なし・マイナス・大額） |
| E-6 長い名前/タイトルの折返し | VLT-E6-01（長い counterparty_name/tags/category の折返し・省略表示） |

### F. デモ品質（営業視点）
| 項目 | 該当シナリオ / 該当なし理由 |
|---|---|
| F-1 初見導線 | VLT-F1-01（/demo/guided で初見が迷わず「売り」＝受領文書の登録・検索・エクスポートに到達できるか） |
| F-2 demo データ品質 | VLT-F2-01（seed の ~20 件受領請求書・void/restore 履歴に Lorem ipsum/テスト残骸露出なし） |
| F-3 エラー文言品質 | VLT-F3-01（404/403/validation が開発者向け文言・スタックトレース露出なし・problem.* で localized。**P-3 の既定エラーUI が営業品質上許容か**を判定） |
| F-4 コンソール/ネットワークエラー | VLT-F4-01（DevTools を開いたまま全シナリオ実行・console error / 4xx-5xx の常時発生有無） |

---

## 2. シナリオ（分類・前提・手順・期待・結果・証拠・発見）

書式は 1 シナリオ 1 ブロック固定。**期待は具体文言で書く**（「エラーにならない」禁止）。結果/証拠/発見は実行時に記入。

### A. 正常系

#### VLT-A1-01: 文書ライフサイクル一巡（登録→閲覧→編集→void→restore）
- 分類: A-1 / 正常
- 前提: admin または member でログイン・demo org
- 手順: 1. /documents で「アップロード」→ PDF を選択・counterparty「テスト商事」・category=invoice_received・amount=110000・transaction_date=当日 で送信 2. 一覧に現れた行をクリックし詳細へ 3. 「編集」で counterparty を「テスト商事（改）」に更新 4. 「無効化（void）」で reason「重複のため」を入力し実行 5. 無効化状態から「復元（restore）」を実行
- 期待: 2 でアップロードした文書が一覧最上部に表示。3 の編集後、詳細の counterparty が「テスト商事（改）」に更新され、履歴テーブルに `metadata.updated` 相当の行が増える。4 で status バッジが「無効」に変わり void_reason が記録される。5 で status が「有効」に戻り、履歴に void→restore の 2 行が残る。**hard-delete のボタンは存在しない**こと（削除＝void のみ）を確認。
- 結果: —／証拠: —／発見: —

#### VLT-A1-02: ユーザー招待→一覧→削除（update UI 不在の確認）
- 分類: A-1 / 正常
- 前提: admin/superadmin でログイン
- 手順: 1. /users で「招待」→ email=`qa+member@example.com`・password=8文字以上・role=member で送信 2. 一覧に追加を確認 3. 追加ユーザー行の「削除」→ `window.confirm` を OK 4. 自分自身の行に削除ボタンが無いことを確認
- 期待: 1 で作成成功し一覧に role=member で表示。**編集（update）ボタン/導線は存在しない**（仕様どおり create+delete のみ）。3 で confirm ダイアログ後に一覧から消える。4 で自分の行には削除ボタンが出ない（自己削除ガード）。
- 結果: —／証拠: —／発見: —

#### VLT-A1-03: Vault 設定の更新（singleton）
- 分類: A-1 / 正常
- 前提: admin/superadmin でログイン・/settings
- 手順: 1. retention_years=10 で保存 2. 保存後 updated_at が更新されるのを確認
- 期待: 保存成功。retention_years=10 では警告が出ない（<10 のみ警告）。settings は単一レコードで create/delete の概念が無いことを確認。
- 結果: —／証拠: —／発見: —

#### VLT-A2-01: 文書の検索・フィルタ
- 分類: A-2 / 正常
- 前提: ログイン・demo org（~20 件）
- 手順: 1. counterparty 部分一致 2. category=invoice_received 3. transaction_date_from/to で範囲 4. amount_min/max で範囲 5. include_voided をオン 6. リセット
- 期待: 各条件で結果が絞られる。include_voided オンで無効文書も含まれ、オフで除外。リセットで全件に戻る。件数がフィルタに整合。
- 結果: —／証拠: —／発見: —

#### VLT-A2-02: 文書一覧のページネーション・大量表示
- 分類: A-2 / 正常
- 前提: 21 件以上ある demo org
- 手順: 1. 1 ページ 20 件表示を確認 2. 次ページへ 3. 総件数とページ数の整合を確認
- 期待: 20 件/頁で分割。ページ移動で残りが表示。総件数表示（あれば）とページ数が一致。
- 結果: —／証拠: —／発見: —

#### VLT-A2-03: 文書一覧の 0 件表示
- 分類: A-2 / 正常
- 前提: ログイン
- 手順: 1. 絶対に該当しない counterparty で検索
- 期待: `document.list.empty` の EmptyState が表示され、エラーやスピナー固着にならない。
- 結果: —／証拠: —／発見: —

#### VLT-A2-04: 監査ログのフィルタ・リセット・ページング・0 件
- 分類: A-2 / 正常
- 前提: ManageVaultSettings を持つ role（admin/superadmin）でログイン・/audit
- 手順: 1. entity_type・entity_id・action の free-text で絞り込み 2. リセット 3. ページ移動 4. 該当なし条件で 0 件 5. 行を Enter/Space/クリックで detail drawer を開き diff↔JSON トグル・Escape で閉じる
- 期待: フィルタで絞られ、リセットで戻る。0 件で EmptyState。行操作で drawer が開き、diff と生 JSON を切替でき、Escape で閉じる。
- 結果: —／証拠: —／発見: —

#### VLT-A2-05: ユーザー一覧のページング・0 件
- 分類: A-2 / 正常
- 前提: ManageUsers を持つ role
- 手順: 1. 20 件/頁を確認 2. 0 件時の EmptyState（デモでは通常 1 件以上）
- 期待: 20 件/頁・EmptyState 表示。
- 結果: —／証拠: —／発見: —

#### VLT-A3-01: 受領文書の登録→SHA 検証→ダウンロード整合→履歴（vault の売り）
- 分類: A-3 / 正常
- 前提: UploadDocument を持つ role
- 手順: 1. 既知の PDF をアップロード 2. 詳細で sha256・version を確認 3. 「ダウンロード」で取得 4. 取得ファイルの SHA-256 を手元で算出し詳細表示値と照合 5. 履歴テーブルに登録イベントが載るのを確認
- 期待: 詳細の sha256 が表示され、ダウンロード物のハッシュと**完全一致**（download 時 SHA-256 検証・改竄なし）。version が採番され、履歴に登録行が残る。**storage path は画面/レスポンスに一切現れない**。
- 結果: —／証拠: —／発見: —

#### VLT-A3-02: OCR suggest → メタデータ prefill → 確定
- 分類: A-3 / 正常
- 前提: 文書詳細
- 手順: 1. 「OCR 候補」を実行 2. 候補が編集モーダルに prefill される 3. 内容を確認して確定
- 期待: OCR 候補（counterparty/amount/date 等）が編集フォームに seed され、そのまま or 修正して保存できる。処理中は `processing` 表示。
- 結果: —／証拠: —／発見: —

#### VLT-A3-03: manifest CSV / export ZIP の出力（電帳法の売り）
- 分類: A-3 / 正常
- 前提: ExportDocuments を持つ role・/export
- 手順: 1. date/counterparty/include-voided フィルタを設定 2. CSV 出力 3. ZIP 出力 4. 出力物の中身（明細・保存期限日・件数）を確認
- 期待: CSV/ZIP がダウンロードされ、フィルタ条件に整合。retention 日付・counterparty・件数が一覧と一致。ZIP に文書実体が含まれる。
- 結果: —／証拠: —／発見: —

#### VLT-A5-01: ナビゲーション全リンク一巡
- 分類: A-5 / 正常
- 前提: superadmin でログイン（全ナビ可視）
- 手順: 1. Home→Documents→（詳細→戻る）→Audit→Settings→Users→Export を順に踏破 2. 各ページから Home へ戻る
- 期待: 全リンクが正しいページに遷移し、詳細→一覧の往復でスクロール位置・状態が壊れない。dead link なし。
- 結果: —／証拠: —／発見: —

#### VLT-A5-02: HomePage クイックリンク一巡
- 分類: A-5 / 正常
- 前提: superadmin
- 手順: 1. HomePage の各カードをクリック
- 期待: capability 可視の各カードが対応ページへ遷移。role で不可視のカードは表示されない。
- 結果: —／証拠: —／発見: —

#### VLT-A6-01: /demo/standard 導線（disposable-org・admin seat・TTL 3h）
- 分類: A-6 / 正常（P-1: SPA ルートでなく PHP 配信入口）
- 前提: 未ログイン・クリーンなブラウザ
- 手順: 1. `https://vault.ayane.co.jp/demo/standard` を開く 2. disposable-org が払い出され admin seat で入る 3. アップロード等の書き込み showcase を試す
- 期待: 使い捨て org に admin として着地し、アップロード導線が動く。TTL 3h の旨が示される（あれば）。既存 org のデータには触れない。
- 結果: —／証拠: —／発見: —

#### VLT-A6-02: /demo/guided 導線（fixed viewer seat）
- 分類: A-6 / 正常（P-1）
- 前提: 未ログイン
- 手順: 1. `https://vault.ayane.co.jp/demo/guided` を開く
- 期待: 固定 viewer seat で閲覧デモに着地。ViewDocuments のみのナビ（viewer）で「売り」を閲覧できる。
- 結果: —／証拠: —／発見: —

### B. 異常系 — 入力

#### VLT-B1-01: retention_years 境界値
- 分類: B-1 / 異常
- 前提: /settings
- 手順: 6／7／10／99／100 を順に保存試行
- 期待: 6=拒否（min 7・zod と HTML min 双方）、7=保存可（警告あり<10）、10=保存可（警告なし）、99=保存可、100=拒否（max 99）。拒否時は field-error 表示で送信されない。
- 結果: —／証拠: —／発見: —

#### VLT-B1-02: amount_cents 境界（0・負数・巨大値）
- 分類: B-1 / 異常
- 前提: upload/metadata・search
- 手順: amount に 0／-1／9999999999999 を入力し送信
- 期待: type=number のため負号/桁の扱いを確認。0 は許容（無償）、巨大値はサーバ側検証に依存。壊れた表示・オーバーフローが出ない。
- 結果: —／証拠: —／発見: —

#### VLT-B1-03: search クロスフィールド（from>to・min>max）
- 分類: B-1 / 異常
- 前提: DocumentSearchForm
- 手順: transaction_date_from > to、amount_min > max で検索
- 期待: **client 側クロスフィールド検証は無い**ため送信される。サーバが 0 件 or 妥当なエラーを返し、UI が壊れない。開発者向けエラーが露出しない。
- 結果: —／証拠: —／発見: —（→ 検証追加要否を hub 仕分けへ）

#### VLT-B2-01〜04: 必須欠落
- 分類: B-2 / 異常
- 手順/期待:
  - **VLT-B2-01** upload で file 未選択→送信不可・field-error（refine length>0）
  - **VLT-B2-02** upload で counterparty_name 空/空白のみ→required（min 1）で送信不可
  - **VLT-B2-03** user create で email 空／password 空／password 7 文字→それぞれ required_marker 表示（**注: field ごとの具体メッセージでなく汎用マーカーのみ**＝P 級の文言品質観点も記録）
  - **VLT-B2-04** void で void_reason 空→required で実行不可
- 結果: —／証拠: —／発見: —

#### VLT-B3-01〜03: 型不正
- 分類: B-3 / 異常
- 手順/期待:
  - **VLT-B3-01** login email に `abc`（＠なし）→ zod email で invalid、submit されない
  - **VLT-B3-02** transaction_date に不正形式（native date widget を DevTools で書換 or キー入力）→ サーバ/zod がはじく
  - **VLT-B3-03** amount（type=number）に文字列貼付→ブラウザが数値のみ受理・非数は無視
- 結果: —／証拠: —／発見: —

#### VLT-B4-01: XSS 表示エスケープ（counterparty に script）
- 分類: B-4 / 異常（セキュリティ）
- 前提: upload
- 手順: counterparty_name に `<script>alert(1)</script>`、tags に `<img src=x onerror=alert(1)>` を入れて保存 → 一覧・詳細・audit diff drawer で表示
- 期待: **スクリプトが実行されず**、文字列としてエスケープ表示される（React 既定エスケープ）。alert が出ない。audit の diff/JSON 表示でも同様。
- 結果: —／証拠: —／発見: —

#### VLT-B4-02: 多バイト・絵文字・RTL
- 分類: B-4 / 異常
- 手順: counterparty/tags に 絵文字・アラビア語（RTL）・全角/半角混在を入力し表示
- 期待: 文字化けせず、RTL でレイアウトが破綻しない。一覧・詳細・CSV 出力で整合。
- 結果: —／証拠: —／発見: —

#### VLT-B5-01/02: 過長入力
- 分類: B-5 / 異常
- 手順: counterparty に数千文字、tags にカンマ区切りで大量、void_note に巨大テキスト
- 期待: 入力上限（サーバ側）に達したら妥当なエラー。UI がはみ出さず折返し/省略。送信ハングしない。
- 結果: —／証拠: —／発見: —

#### VLT-B6-01/02: 二重送信・連打
- 分類: B-6 / 異常
- 手順: upload/save/delete ボタンを高速連打・ダブルクリック
- 期待: 送信中はボタンが `saving/uploading/processing` に切替わり無効化され、**多重リクエストが飛ばない**（Network タブで 1 回）。二重作成が起きない。
- 結果: —／証拠: —／発見: —

### C. 異常系 — 認証・権限・境界

#### VLT-C1-01: 未ログインで保護 URL 直叩き
- 分類: C-1 / 異常
- 前提: 未ログイン（token null）
- 手順: /documents /audit /users /settings /export /documents/{id} を直接 URL で開く
- 期待: **リダイレクトせず現在 URL のまま** AuthGate が LoginForm を in-place 表示。ログイン成功で元ページが reactively 復帰。保護データは一切先読み表示されない。
- 結果: —／証拠: —／発見: —

#### VLT-C2-01〜03: ID 直叩き（不在・他org・不正形式）
- 分類: C-2 / 異常（情報漏えい確認）
- 手順/期待:
  - **VLT-C2-01** /documents/{存在しない ULID}→ danger Callout（`problem.document_not_found`）。スタックトレース/内部パス露出なし
  - **VLT-C2-02** /documents/{他 org の実在 ULID}→ 404/403 で内容を出さない（**org 越え漏えいゼロ**が最重要）。storage path も出ない
  - **VLT-C2-03** /documents/abc（不正形式）→ 妥当なエラー、500 で落ちない
- 結果: —／証拠: —／発見: —

#### VLT-C3-01〜04: 権限別表示
- 分類: C-3 / 異常
- 手順/期待:
  - **VLT-C3-01** viewer ログイン→ナビは Documents のみ（Audit/Settings/Users/Export **不可視**）。Home クイックリンクも同様
  - **VLT-C3-02** member ログイン→文書詳細の void/edit ボタンは **UI で非ゲート＝表示される**が、押下・submit すると server 403 → /forbidden。「見えるが実行不可」の挙動を記録（UX 課題候補）
  - **VLT-C3-03** viewer が /users を手打ち→ページは load するが API 403 → transport が `/forbidden` へハードリダイレクト
  - **VLT-C3-04** admin と superadmin の差＝superadmin のみ ManageOrganizations（該当 UI があれば差分確認）
- 結果: —／証拠: —／発見: —

#### VLT-C4-01: セッション切れ後の操作
- 分類: C-4 / 異常
- 前提: ログイン後トークン失効（放置 or 手動失効）
- 手順: 失効後に一覧取得・保存操作
- 期待: transport が 401 検知→session クリア→AuthGate が LoginForm 表示。入力中データの喪失有無を記録（喪失するなら UX 課題）。
- 結果: —／証拠: —／発見: —

#### VLT-C5-01: ログアウト→ブラウザ戻る
- 分類: C-5 / 異常
- 手順: ログイン→保護ページ表示→ログアウト→ブラウザ「戻る」
- 期待: 戻った先で保護データがキャッシュ露出せず、AuthGate が再度 LoginForm を出す。
- 結果: —／証拠: —／発見: —

### D. 異常系 — 遷移・状態

#### VLT-D1-01: 入力途中のリロード/戻る
- 分類: D-1 / 異常
- 手順: upload/metadata modal に入力途中でリロード・ブラウザ戻る
- 期待: データ喪失時に警告が出るか（beforeunload 等）。出ないなら「喪失する」と記録（仕様判断へ）。
- 結果: —／証拠: —／発見: —

#### VLT-D2-01: 深いリンク直行（詳細ブックマーク）
- 分類: D-2 / 異常
- 手順: /documents/:id をブックマークから直開（未ログイン/ログイン両方）
- 期待: 未ログイン→AuthGate、ログイン→詳細が正しく load。
- 結果: —／証拠: —／発見: —

#### VLT-D2-02: 未知 URL（カスタム 404 不在）
- 分類: D-2 / 異常（P-3 発見候補）
- 手順: /nonexistent、/documents/（末尾スラッシュ）を開く
- 期待（現状把握）: **カスタム 404 が無い**ため React Router 既定エラーUI が出る。営業デモとして許容かを判定し、意匠付き 404 導入の要否を発見欄へ。
- 結果: —／証拠: —／発見: —（→ hub 仕分け: W3 意匠 or issue 起票）

#### VLT-D3-01: 複数タブ同時編集
- 分類: D-3 / 異常
- 手順: 同一文書を 2 タブで開き、両方で metadata 編集→交互に保存
- 期待: 後勝ち or 競合エラーの挙動を記録。データ破損・不整合が起きない。
- 結果: —／証拠: —／発見: —

#### VLT-D4-01: 低速回線・読み込み中の連打
- 分類: D-4 / 異常
- 手順: DevTools で Slow 3G→一覧/詳細を開き loading 中に連打
- 期待: loading 状態（EmptyState `common.status.loading`）が正しく出て崩れない。多重リクエストが飛ばない。
- 結果: —／証拠: —／発見: —

### E. 表示・国際化・テーマ

#### VLT-E2-01: レスポンシブ
- 分類: E-2
- 手順: 375px / 768px でナビ・DocumentTable・各モーダル・AuditPage を確認
- 期待: 横スクロールが本文で発生しない（テーブルは自身の overflow 内でスクロール）。ナビ・モーダルが破綻しない。
- 結果: —／証拠: —／発見: —

#### VLT-E3-01: 言語切替 ja/en
- 分類: E-3
- 手順: AppShell topbar・Login・Forbidden の LanguageSwitcher で ja↔en 切替。全ページを巡回
- 期待: 未訳キーの**生キー露出が無い**（`t()` は欠落時キーを echo するので露出＝発見）。レイアウト崩れ無し。native date widget の言語が documentElement.lang に追随。localStorage に保存され再訪で維持。
- 結果: —／証拠: —／発見: —

#### VLT-E4-01: 同一文書内の日時 3 系統混在（#228 直結）
- 分類: E-4 / 異常（最重要・#228）
- 前提: 文書詳細（uploaded_at・transaction_date・retention_expires_at を持つ文書）
- 手順: 1. 詳細で uploaded_at（`formatDateTime`）・transaction_date/retention_expires_at（`formatDate`）を確認 2. 一覧 DocumentTable で同文書の uploaded_at（`.slice(0,10)`）を確認 3. UTC 日境界付近の時刻（例 UTC 23:30 = JST 翌 08:30）の文書で日付ズレを観察
- 期待（現状把握）: `formatDateTime` はブラウザローカル TZ、`formatDate`/`.slice` は生 UTC 日付。**UTC 日境界で「詳細の uploaded_at 日付」と「一覧の uploaded_at 日付」が 1 日ズレる**可能性。ズレたら #228 の実データ証拠として記録。
- 結果: —／証拠: —／発見: —（→ **#228 実データ最終GOへ必ず連携**）

#### VLT-E4-02: audit 時刻（ローカルTZ）× 文書日付（UTC）の混在
- 分類: E-4 / 異常（#228）
- 手順: ブラウザ TZ を JST→UTC→米東部に変え、AuditPage created_at（`formatDateTime`）と文書 transaction_date（`formatDate`）を並べて観察
- 期待（現状把握）: audit 時刻は TZ で動き、文書日付は動かない。監査証跡と文書日付の見かけ整合が TZ 依存で崩れうる点を記録。
- 結果: —／証拠: —／発見: —（→ #228）

#### VLT-E4-03: users created_at 表示
- 分類: E-4 / 異常（#228）
- 手順: /users の created_at（`.slice(0,10)`＝UTC日付）を確認
- 期待: UTC 日付ベタ表示であることを確認（他画面の TZ 変換と不統一）。
- 結果: —／証拠: —／発見: —（→ #228）

#### VLT-E5-01: 通貨・数値書式
- 分類: E-5
- 手順: amount に 1,000／1,000,000／マイナス相当／大額を入れ、一覧・詳細・CSV で表示
- 期待: JPY はマイナー単位なし（whole yen）。桁区切り・マイナス表示が一貫。CSV でも同じ書式方針。
- 結果: —／証拠: —／発見: —

#### VLT-E6-01: 長い名前の折返し
- 分類: E-6
- 手順: 長い counterparty/tags/category 値でテーブル・詳細・drawer を表示
- 期待: 折返し or 省略（…）で隣接崩れ・横あふれが起きない。
- 結果: —／証拠: —／発見: —

### F. デモ品質

#### VLT-F1-01: 初見導線（/demo/guided）
- 分類: F-1
- 手順: 初見想定で /demo/guided から「受領文書を探して中身とハッシュを確認する」まで到達
- 期待: ガイド/ラベルで迷わず売り（登録・検索・エクスポート・改竄検知）に到達できる。
- 結果: —／証拠: —／発見: —

#### VLT-F2-01: demo データ品質
- 分類: F-2
- 手順: seed の ~20 件・void/restore 履歴を一巡
- 期待: Lorem ipsum・`test`・`asdf` 等のテスト残骸や不自然な値が露出しない。営業で見せられる品質。
- 結果: —／証拠: —／発見: —

#### VLT-F3-01: エラー文言品質
- 分類: F-3
- 手順: 404（不在ID）・403（権限外）・validation・未知URL(P-3) を一巡
- 期待: いずれも localized（problem.*）でユーザー向け文言。スタックトレース・内部パス・SQL 露出なし。**未知 URL の React Router 既定 UI が営業品質上許容か**を判定。
- 結果: —／証拠: —／発見: —

#### VLT-F4-01: コンソール/ネットワークエラー常時監視
- 分類: F-4
- 手順: DevTools（Console＋Network）を開いたまま **上記全シナリオ**を実行
- 期待: 想定内の 401/403/404 以外に、console error・未処理 4xx/5xx・React 警告（key 重複・act 等）が常時発生していない。
- 結果: —／証拠: —／発見: —

---

## 3. 実行記録（hub 承認後に追記）

| 項目 | 値 |
|---|---|
| 実行日時（TZ 明記） | — |
| 実行者 | Vault リナ |
| ブラウザ/バージョン | — |
| 画面幅 | — |
| デモ URL | https://vault.ayane.co.jp |
| フロント ビルド SHA | — |

**サマリ**: 総数 — ／ ✅ — ／ 🔴 — ／ ⚠️ — ／ 未実行 —（理由）

- 🔴 と ⚠️ は**その場で再現手順を確定**してから次へ進む（後から再現できない報告を作らない）。
- E-4 系の発見は #228 実データ最終GOへ必ず連携する。

---

## 4. 仕分け（実行後・hub が実施）

- 機能バグ → 当該 repo issue 起票・優先度付き
- 意匠・スタイル → W3 台帳送り
- 仕様疑義・判断要 → 施主バンドルへ（例: D-1 喪失警告、C-3-02 見えるが不可、P-3 カスタム404）
- 営業素材（Before/After 映え）→ スクショ保存・SNS キューへ
