# クライアントが自分で編集できる環境（レイアウトを壊さない）— 設置手順

`validation/EDITOR-EDIT-RISK.md` で判明した「編集画面から保存するとレイアウトが崩れる」問題への対応。
**サイトの見た目は現状のまま**、クライアントが WordPress の中で**文章の修正と写真の差し替え**を行えるようにする。
全39ページで検証済み（`validation/EDITING-VERIFICATION.md`：無編集保存・画像登録ともピクセル差ゼロ）。

## 何をするか

| 部品 | 役割 |
|---|---|
| `../teachingbeauty-theme/functions.php`（修正） | WordPress 既定のフロント用CSS（`global-styles` 等）を読み込まない。原本に無い下線を描く・登録後の画像の高さを変える、という副作用があった。 |
| `mu-plugin__tb-editor.php` | ブロックエディターをやめて**クラシックエディター**（従来の編集画面）を使う。TinyMCE を「見たままを、そのまま保存する」設定にし、設定で止められない改変（空ブロックへの `<br>` 挿入など）は読込時に目印を付け保存時に戻して無効化する。 |
| `tb-register-images.php` | 元サイト由来の画像（`<img src="IMG_xxx.png">`）をメディアライブラリに登録し、`<img>` に `class="wp-image-ID"` を付与。これで画像詳細に**「置換」ボタンが出る**。変更は `<img>` の class と src だけ。元ファイルはサーバーに残す。 |

### 崩れの原因と対策（要点）
- 標準の TinyMCE は保存時に `<p>`/`<br>` を剥がし、表示時の `wpautop` に任せる。本テーマは原本忠実のため `wpautop` を使わない → 剥がされっぱなしで行間・段落が消える。→ **`wpautop: false`**
- `<font size/color>` を `<span style>` に変換する。→ **`convert_fonts_to_spans: false`**
- 裸のテキストを `<p>` で包む・スキーマ検証で要素を並べ替える。→ **`forced_root_block: false` / `verify_html: false` / `valid_elements: *[*]`**
- 閉じ忘れタグの解釈違い、空ブロックへの `<br>` 挿入、WP側の `<p>&nbsp;</p>` 変換など → **mu-plugin の `setup` で読込時／保存時に補正**（詳細はファイル内コメント）

## 設置手順（ステージング `/wp/`）

**一括実行**：`deploy-editing.local.ps1`（Git 管理外・FTP パスワードを含む）を PowerShell で実行すると、下記 1〜3 に加えてドライラン・25件ずつの画像登録・スクリプト削除・結果確認まで自動で行う。
```powershell
powershell -ExecutionPolicy Bypass -File "（リポジトリ）\teachingbeauty-backup\deploy-editing.local.ps1"
```
以下は手動で行う場合の手順。順番どおりに。**3 → 1 → 2** の順でも動くが、画像登録（2）は必ずテーマ修正（1）の後に行う。

### 1. テーマの functions.php を差し替える
※ サーバー上のテーマフォルダ名は `teachingbeauty`（リポジトリの `teachingbeauty-theme` とは異なる）。
```powershell
curl.exe -T "（リポジトリ）\teachingbeauty-theme\functions.php" "ftp://main.jp-teachingbeauty:（FTPパスワード）@ftp.lolipop.jp/wp/wp-content/themes/teachingbeauty/functions.php"
```
確認：任意のページを開き、ソースに `id="global-styles-inline-css"` が**無い**こと。表示は変わらない（`hari.html` の本文中の下線だけが原本どおり消える）。

### 2. mu-plugin を置く
`mu-plugin__tb-editor.php` を **`/wp/wp-content/mu-plugins/tb-editor.php`** として置く（ファイル名を変える）。置いた時点で有効。
```powershell
curl.exe -T "（リポジトリ）\teachingbeauty-backup\mu-plugin__tb-editor.php" "ftp://main.jp-teachingbeauty:（FTPパスワード）@ftp.lolipop.jp/wp/wp-content/mu-plugins/tb-editor.php"
```
確認：管理画面で固定ページを開くと、従来型の編集画面（ビジュアル／テキストのタブ）になり、上部に青い案内が出る。

### 3. 画像をメディアライブラリに登録する
`tb-register-images.php` を `/wp/` 直下に置き、ブラウザで開く。
```powershell
curl.exe -T "（リポジトリ）\teachingbeauty-backup\tb-register-images.php" "ftp://main.jp-teachingbeauty:（FTPパスワード）@ftp.lolipop.jp/wp/tb-register-images.php"
```
1. まず **ドライラン**（何も変えない・一覧だけ表示）
   `https://www.teachingbeauty.jp/wp/tb-register-images.php?k=k7Qm2xR9vTd4&dry=1`
   → 末尾が「imgタグ 308 / 新規登録 238 / 対象外 0 / ファイル無し 0」になるはず（ローカルと同じ）。
2. 問題なければ **実行**。共用サーバーの実行時間制限に当たらないよう **25件ずつ**処理する。
   `https://www.teachingbeauty.jp/wp/tb-register-images.php?k=k7Qm2xR9vTd4&n=25`
   → 「続きがあります」と出たら同じURLをもう一度開く。「完了」が出るまで繰り返す（10回程度）。
   → 「登録失敗: teion」が1件出るのは想定内（拡張子の無いファイル。表示はそのまま）。
   → 何度実行しても安全（登録済みはスキップ、同じファイルは再利用して重複登録しない）。
3. 終わったら削除
   ```powershell
   curl.exe -Q "DELE /wp/tb-register-images.php" "ftp://main.jp-teachingbeauty:（FTPパスワード）@ftp.lolipop.jp/"
   ```

### 4. 確認
- トップページ・任意のページの表示が変わっていないこと。
- 固定ページを開き、写真をクリック → えんぴつ → 「画像詳細」の右側に**「置換」**があること。
- 何も変えずに「更新」を押しても表示が変わらないこと。

## クライアントの操作（案内用・実機確認済み）

**文章を直す**：固定ページ → ページを開く →「ビジュアル」タブで文章を直す → 右上「更新」。

**検索エンジン向けの説明文・キーワード**：編集画面の本文欄のすぐ下「このページの説明文とキーワード」で直す → 右上「更新」。ページの `<title>` は画面いちばん上のタイトル欄そのもの（サイト名は付かない）。「設定 → 一般」の「サイトのタイトル」は各ページの `<title>` には付かない（元サイトにサイト共通のタイトルは無く、全ページが個別の `<title>` を持つため）。ただし Google の検索結果でページ名の上に出る「サイト名」の手がかり（`og:site_name` と WebSite 構造化データ）として出力しているので、「Teaching Beauty鍼灸整骨院」のように院名を入れておく。「キャッチフレーズ」は未使用。

**写真を差し替える**：
1. 写真を1回クリック → 写真の上に小さなボタン列 → **えんぴつ**をクリック
2. 「画像詳細」の右側にある**「置換」**をクリック
3. 「画像の置き換え」画面で「ファイルをアップロード」→ 新しい写真をドラッグ（または「ファイルを選択」）
4. アップロードが終わったら、右側の設定欄を下にスクロールして**「サイズ」を「中」**にする
5. 右下の**「置換」** → その場で写真が入れ替わる
6. 右上の**「更新」**で保存

**文字の色・マーカー**：文字を選んで、編集欄の上の右端「ツールバー切り替え」で2段目を出す → 「A」（テキスト色）でコードによらず色変更、その右隣の「A」（背景色）でマーカー。コードは不要。

**写真を追加する**：本文の入れたい場所をクリック → 編集欄の上の**「メディアを追加」** → アップロード → 「投稿に挿入」→「更新」。

**注意**：「テキスト」タブ（生HTML）は触らない。壊れても「固定ページ → リビジョン」から戻せる。

## 本番切替時の注意
- 登録後の画像 `src` は `https://www.teachingbeauty.jp/wp/wp-content/uploads/...` の絶対URLになる。`/wp/` からルートへ移す際は、本文内の `/wp/` を含むURLを一括置換する（WP-CLI `wp search-replace` または同等のSQL）。CF7 などの他の絶対URLも同様に扱うため、既存の切替手順に含める。
- 元の画像ファイル（`/IMG_3831.png` など）はサーバーに残す。外部からの直リンクや、登録対象外の参照を壊さないため。
