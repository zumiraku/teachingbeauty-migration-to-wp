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

`verify-site.mjs` は通った。39 ページの HTTP・`<title>`・本文・canonical・noindex の有無・WP 既定 CSS の有無、
画像と内部リンクの参照先、リダイレクト、管理画面と REST、静的資産まで、落ちたものは無し。
全ページのスクリーンショット比較も切替前と一致。切替後に管理画面から無編集で更新しても、写真を差し替えても表示は崩れず、
差し替えた写真は `/wp/wp-content/uploads/` から出る。切り戻すと静的サイトに戻り、もう一度切替をやり直しても同じ結果。

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

本番でも `verify-site.mjs --live` は通った。念のため時間を置いてもう一度流したが同じ。

- リダイレクト：`/index.html`→`/`、`/wp/xxx.html`→`/xxx.html`、`/wp/`→`/`、`http`→`https`、非www→www、`/3okushi.html`→`/kokushi.html` すべて 301
- 退避した `/_old-static/` は 403 で外から見えない
- `/sp/index.html`・`sitemap.xml`・`robots.txt`・Search Console の確認ファイル・孤立ページはそのまま 200
- `/wp/wp-login.php` と `/wp-json/` も 200。サイト名から「(検証)」が取れ、home はルート、siteurl は /wp のまま
- 全ページのスクリーンショット比較は一致。ずれた 3 ページは理由がはっきりしている（home は手元で写真を差し替えたまま、
  reserve は本番だけ CF7 のフォームが入る、kanja は最下端の数十 px）

CSS の配信条件だけ注意。本番は `Content-Type: text/css` を charset 無しで返すので CSS 側の `@charset "Shift_JIS"` が効き、
フォント名が旧サイトどおりに解決される。手元の PHP 組み込みサーバーは `charset=UTF-8` を付けるためここが食い違い、
最初の比較で全ページ差が出た。切替前の静的サイトも検証環境も本番と同じ条件なので、合わせれば一致する。

## 切替後の変更（2026-09-21）：スマホ→/sp/ リダイレクトの廃止
クライアントの判断で、トップページのスマホ判定リダイレクト（`/sp/index.html` へ）を外した（`header.php`）。
スマホでも他のページと同様に WordPress のトップページが表示される。`/sp/` フォルダ自体は静的ファイルとしてそのまま残す。
`verify-site.mjs` の該当チェックは「リダイレクト無し」に反転。

## 全ページ監査と修正（2026-09-21）

クライアントからサイドバー「→アクセス」の矢印だけが別書体で小さく表示される（Mac / iPhone）との報告を受け、
**本番の全 URL をブラウザで巡回する監査**（`audit-site.mjs`）と、**元サイトの静的ミラーとの全ページ比較**
（`compare-original.mjs`：装飾レイヤーあり／なしの両方）を実施した。

### 見つかった不具合と対処

| # | 症状 | 原因 | 対処 |
|---|---|---|---|
| 1 | Mac / iPhone で「→アクセス」の矢印だけ小さい | 元サイトの書体指定（ヒラギノ角ゴ **Pro** W3 等）が今の macOS / iOS に存在せず、文字ごとに別の代替書体になる | `enhance.css` の `body` に Hiragino Sans / ProN を追加（Windows はメイリオ先頭のまま不変） |
| 2 | **患者様の声（kanja）など 22 ページ・55 箇所の見出しが横並びに崩れる**、6 ページで横スクロール発生 | 8/30 の「見出しを縦中央に」で h3 を `display:flex` にしたが、元サイトには h3 の中に改行・画像・本文まで入っているものがあり、それらの中身が横一列になっていた | flex を「1行のタイトルだけを含む h3」に限定（`:not(:has(br, img, div, …))`）。装飾ありで全 39 ページの要素の横位置が装飾なしと一致することを確認 |
| 3 | newpage23 のリンクが 404（`newpage13.html>突発性難聴</a>…` という URL） | 元サイトの HTML で `href` の閉じ引用符が欠落 | 本文の該当 1 箇所に引用符を補う（`tb-fix-content.php`） |
| 4 | トップページで混在コンテンツ（`http://platform.twitter.com/widgets.js` がブロック） | 元サイトの Tweet ボタン用スクリプト（https 化以前のもの） | スクリプトを削除（元サイトでもブロックされ表示に影響なし）、リンクを https に |
| 5 | 全ページで `/favicon.ico` が 404 | 元サイトに favicon が無い | ロゴのシンボルから `favicon.ico` / `apple-touch-icon.png` を生成しルートへ。`header.php` で指定 |
| 6 | `/sp/index.html`（旧スマホ版）で jQuery・Twitter・Facebook が http でブロック、存在しない画像 4 件 | 元サイトのまま | https 化、FC2 カウンター削除、存在しない画像の `<li>` を削除。iPhone 相当の表示で console エラー 0 を確認 |

### 監査で確認した「問題なし」
- 装飾レイヤー無しの WordPress は元サイトと **35 / 39 ページでピクセル一致**（差があった 4 ページ：home はクライアントが写真を差し替えたもの、reserve は予約フォームが CF7、menu / beauty は 2px の縦ずれ）
- 39 ページの `<title>`・canonical・本文・画像 308 参照・内部リンク 827 参照：異常なし
- 外部リンクで応答が無いもの（`privacy.html` の相互リンク先 3 件：kenkounavi.jp / shinq-compass.jp / aroeseitai.homeip.net）は**リンク先が閉鎖・拒否**しているもので、サイト側の不具合ではない。掲載を続けるかはクライアント判断
- 移行対象外の孤立静的ページ（`newpage15.html` 等 18 件）には元サイトの時点から存在しない画像への参照がある。どこからもリンクされていないページのため今回は手を入れていない（削除するかはクライアント判断）

### 適用
`fix-round1.local.ps1`（Git 管理外）で、テーマ・favicon・`/sp/index.html`・本文修正・検証まで一括。

### 適用結果（2026-09-21 06:58 JST）
`fix-round1.local.ps1` を実行。テーマ・favicon・`/sp/index.html`・本文修正（3 箇所）を適用。
- 混在コンテンツ・壊れた href・favicon の検査を足したうえで `verify-site.mjs` は通った
- ブラウザで全 URL を巡回しても、console エラー・失敗リクエスト・壊れた画像・横スクロール・文字化けとも無し
- 装飾あり／なしで要素の横位置を比べ、崩れが残っていないことを確認。reserve だけ差が出るが CF7 の要素の並びによるもので表示は正常
- サイドバー「→アクセス」の書体が `メイリオ, Meiryo, Hiragino Sans, …` で解決されることを確認（Apple 実機は先方確認待ち）
- 応答の無い外部リンク先（privacy.html の 3 件）は警告として出すだけにした。こちらの不具合ではないため

### 訂正と追加修正（2026-09-21・第2弾）：サイドバー電話番号のはみ出し

上記 #1「→アクセスの矢印が小さい」の原因分析は**誤りだった**。書体の問題ではなく、**トップページ（と newpage13）の
サイドバーだけ元サイトの時点で HTML が他ページと違っていた**：
- `<a href="access.html">→</a><b><font size="5"><a>アクセス</a>` — 矢印が size=5 の外にある別リンク → 矢印だけ小さく、アクセスは太字で大きい
- `<a href="tel:…"><font size="6">番号</font></a>` — `<font>` が `<a>` の内側 → `<a>` に当てた 22px が効かず、番号は 32px のまま

第1弾で body の書体に Hiragino Sans を足したため、Mac / iPhone ではこの 32px の数字が Helvetica より広い Hiragino の
数字になり、枠（内側 203px）から **はみ出した**（Helvetica 32px ≒ 199px でぎりぎり収まっていたものが ≒ 221px に）。
つまり、はみ出しは第1弾の変更が引き起こしたもの。

対処：
- `enhance.css`：番号のルールを `a[href^="tel:"]` と `a[href^="tel:"] font` の両方に当て（入れ子がどちらでも 22px）、
  数字の書体を `メイリオ → Helvetica → Arial` に固定（Windows は不変、Mac は元サイトの見た目）、枠内で iOS の自動文字拡大を無効化。
  `.tb-enhanced`（JS）に依存しない素の規則にした
- `enhance.js`：読込後・フォント読込後・リサイズ時に番号の描画幅を実測し、枠に収まらなければ 1px ずつ縮める（最後の保険）
- `tb-fix-content.php`：home / newpage13 / taiban のサイドバー TEL／→アクセス ブロックを、他 36 ページと同じ標準の形に統一
- 検証（本番 HTML に同じ置換を施して新 CSS/JS で描画）：3 ページとも番号 22px・余白 66px、幅広書体（Verdana）でも余白 43px、
  「→アクセス」は 18px で矢印とアクセスが同じ大きさ。CSS が効かない／JS が動かない／両方の場合も枠内（余白 35px 以上）

適用（`fix-round2.local.ps1`）。本文の直しは 3 箇所。検証もブラウザ巡回も通った。
本番で測り直したところ home / newpage13 / taiban / concept とも番号は 22px・幅 137px で、枠に 66px 余る（PC・iPhone 相当とも）。
サイドバーの HTML は 39 ページすべて同じ形になった。
