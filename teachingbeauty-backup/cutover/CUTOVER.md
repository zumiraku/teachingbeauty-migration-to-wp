# 本番切替（/wp/ → サイトルート）手順と検証記録

## 方式：WordPress 本体は /wp/ のまま、表示アドレスだけルートにする

WordPress 公式の「WordPress を専用ディレクトリに配置する」構成。ファイルを移動せず、
サイトルートに `index.php`（3行）を置いて `home` オプションをルートにするだけなので、**変更点が少なく、数十秒で切り戻せる**。

| 項目 | 切替後 |
|---|---|
| 公開URL | `https://www.teachingbeauty.jp/`、`/concept.html` など**現行と同一** |
| 管理画面 | `https://www.teachingbeauty.jp/wp/wp-admin/`（変更なし・クライアントの操作も同じ） |
| 画像・テーマ | `/wp/wp-content/...` のまま（本文中の URL 書き換え不要） |
| 元サイトの CSS・画像・`/sp/`・孤立ページ | ルートに残す。トップページのスマホ→`/sp/index.html` リダイレクトは切替時は原本どおり再現し、2026-09-21 にクライアント判断で廃止（スマホにも WordPress のトップを表示） |
| 移行済み 39 ページの静的 `.html` | `/_old-static/` へ退避（外部からは閲覧不可）。WordPress が同じ URL で配信 |
| 既存の 301（https / www / funin_aen） | ルート `.htaccess` に**そのまま**残し、WordPress のルールをその後ろに追加 |
| 旧 `/wp/xxx.html` | ルートへ 301（重複 URL を残さない） |

## 変更するもの（サーバー）

| # | 操作 | ファイル |
|---|---|---|
| 0 | バックアップ（ローカルへ取得） | ルート `.htaccess`、`/wp/.htaccess`、SQLite DB、静的 `.html` ×39 |
| 1 | ルートの画像を最適化版で上書き（同名・見た目同一・約62%軽量。CSS は同一のため対象外） | `www-optimized/` の 273 ファイル |
| 2 | テーマ・プラグイン更新 | `header.php`（スマホ→/sp/）、`functions.php`（robots meta 除去）、`tb-html-permalinks.php`（`Meniere.html` 表記・`3okushi.html` 301） |
| 3a | ルートに `index.php` | `cutover/index.php` |
| 3b | `home` をルートへ（`blog_public=1`、サイト名の「(検証)」除去、リライト再構築） | `cutover/tb-cutover.php?do=root` |
| 3c | ルート `.htaccess` を差し替え | `cutover/htaccess-root.txt` |
| 3d | 39 ページの静的 `.html` を `/_old-static/` へ（最初に `concept.html` を退避して WordPress 配信を確認してから残り） | — |
| 3e | `/wp/.htaccess` を差し替え | `cutover/htaccess-wp.txt` |
| 4 | 本番検証 | `cutover/verify-site.mjs https://www.teachingbeauty.jp --live` |

この順序だと、どの時点でも「見えなくなるページ」が無い（3c まで静的サイトが優先され、3d で 1 ページずつ WordPress 配信に切り替わる）。

## 実行

```powershell
powershell -ExecutionPolicy Bypass -File "（リポジトリ）\teachingbeauty-backup\cutover.local.ps1"
```
失敗したらその場で停止し、以降の操作は行わない。切り戻し：
```powershell
powershell -ExecutionPolicy Bypass -File "（リポジトリ）\teachingbeauty-backup\cutover-rollback.local.ps1"
```
（どちらも FTP パスワードを含むため Git 管理外）

## 事前検証（ローカル再現環境・2026-09-21）

サーバーと同じ配置（ルート＝静的サイト 345 ファイル、`/wp/`＝WordPress 7.1 + 本テーマ + 39 ページ + 登録画像）を
ローカルに作り、Apache の `.htaccess` の挙動（実在ファイル優先・DirectoryIndex・301）を再現するルーターで、
**上記と同じ順序で切替 → 検証 → 切り戻し → 再切替** を実施。

| 検証 | 結果 |
|---|---|
| `verify-site.mjs`（39 ページの 200・`<title>`・本文・canonical・noindex 無し・WP 既定 CSS 無し、画像 308 参照、内部リンク 743 参照、リダイレクト、管理画面・REST、静的資産） | **PASS 1647 / FAIL 0** |
| 全 39 ページのピクセル比較（ルート表示 vs 切替前の検証済み表示） | **39 / 39 差分ゼロ** |
| 切替後の編集（`/wp/wp-admin/` でログイン → 無編集で更新 → 写真を置換 → 更新） | 表示差分 0 px、新しい写真は `/wp/wp-content/uploads/` で 200 |
| 切り戻し → 再切替 | 静的サイトが復帰（FC2 カウンタ検出）→ 再切替後 PASS 1647 / FAIL 0 |

事前検証で見つけて直したもの：
- 元 `index.html` のスマホ→`/sp/index.html` リダイレクトがテーマに無かった → `header.php` にフロントページ限定で再現
- `Meniere.html`（大文字）の canonical が `meniere.html` になっていた → プラグインで元の表記を維持
- `menu.html` 内の誤字リンク `3okushi.html`（元サイトでも 404）→ `kokushi.html` へ 301
- WordPress が付ける `<meta name="robots" content="max-image-preview:large">` → 元サイトに無いので除去

## 本番の事前確認（読み取りのみ・2026-09-21）
- ルートに移行対象 39 ページの静的 `.html` がすべて存在。孤立ページ 25 件は静的のまま残す
- `/wp/` の WordPress：39 ページ・画像・内部リンクとも異常なし（`verify-site.mjs https://www.teachingbeauty.jp/wp`：ページ・画像・リンクの失敗 0。失敗はベースが `/wp/` であることによる想定内の項目のみ）
- `wp-config.php` に `WP_HOME` / `WP_SITEURL` の固定なし（オプションで切替可能）
- `blog_public = 1`（noindex なし）、`robots.txt` / `sitemap.xml` はルートの静的ファイルのまま
- FTP の RNFR/RNTO（退避に使用）と quote コマンドが動作することを確認

## 切替後に残る作業（任意）
- `/_old-static/` は 1〜2 週間問題が無ければ削除してよい（リポジトリの `www/` に同じものがある）
- `/wp/` 直下にある元サイトの画像・CSS のコピー（検証時代のもの）は不要だが害もない
- Search Console：URL は変わらないため再登録不要。確認ファイル `googlee9db13e7722f4047.html` はルートに残している

## 本番切替の実施結果（2026-09-20 16:33 JST）

`cutover.local.ps1` を実行。0/4〜4/4 まで停止なしで完了。

| 検証 | 結果 |
|---|---|
| スクリプト内の本番検証（`verify-site.mjs https://www.teachingbeauty.jp --live`） | **PASS 1737 / FAIL 0**（39 ページ、画像 308 参照、内部リンク 827 参照、資産 285 種） |
| 別セッションからの独立検証（同スクリプト） | **PASS 1737 / FAIL 0** |
| リダイレクト | `/index.html`→`/`、`/wp/xxx.html`→`/xxx.html`、`/wp/`→`/`、`http`→`https`、非www→www、`/3okushi.html`→`/kokushi.html` すべて 301 |
| 退避フォルダ `/_old-static/` | 403（閲覧不可） |
| 静的資産 | `/sp/index.html`・`/sitemap.xml`・`/robots.txt`・Search Console 確認ファイル・孤立ページ 200 |
| 管理画面・REST | `/wp/wp-login.php` 200、`/wp-json/` 200、サイト名「Teaching Beauty」（検証ラベル除去）、home=ルート・url=/wp |
| 全 39 ページのピクセル比較（本番 vs 事前検証済みのローカル再現） | **36 / 39 差分ゼロ**。残り 3 件は説明可能：home（ローカル側の編集テストで写真を差し替えたまま）、reserve（本番のみ Contact Form 7 のフォームあり）、kanja（ページ最下端の 48 px） |

補足：本番サーバーは CSS を `Content-Type: text/css`（charset 指定なし）で配信するため、CSS 内の `@charset "Shift_JIS"` が効き、
フォント名（'メイリオ' 等）が元サイトどおりに解決される。ローカル再現では PHP の組み込みサーバーが `charset=UTF-8` を付けるため
初回比較で全ページに差が出たが、配信条件を本番に合わせると上記のとおり一致した（切替前の静的サイト・検証環境とも同じ配信条件）。

## 切替後の変更（2026-09-21）：スマホ→/sp/ リダイレクトの廃止
クライアントの判断で、トップページのスマホ判定リダイレクト（`/sp/index.html` へ）を外した（`header.php`）。
スマホでも他のページと同様に WordPress のトップページが表示される。`/sp/` フォルダ自体は静的ファイルとしてそのまま残す。
`verify-site.mjs` の該当チェックは「リダイレクト無し」に反転。
