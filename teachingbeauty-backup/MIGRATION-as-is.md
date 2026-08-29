# Teaching Beauty 現行サイト 1:1 移行手順（as-is）

現行サイトを**そのまま**WordPress化するためのパッケージ。ページの統合・URL変更・デザイン変更は行わない。

## 方針
- 全39ページ（sp版除く）を**1ページずつそのまま**移行（統合しない）。
- **URLは現行の `.html` を維持**（例 `/concept.html` はそのまま `/concept.html`）。→ 検索評価・被リンク・visitor のブックマークがすべて維持される。
- 見た目は Stage 3 の専用テーマ（`teachingbeauty-theme/`）が現行デザインを再現。
- 文字コードは全て UTF-8 に統一（Shift_JIS 19ページを変換済み）。
- 追跡・外部ウィジェット（FC2カウンター/Twitter/ekiten）の**壊れた http スクリプトのみ除去**（表示は不変・混在コンテンツ警告を解消）。

## 同梱ファイル
| ファイル | 用途 |
|---|---|
| `teachingbeauty.wxr` | 全39ページの WordPress インポートファイル（本文はUTF-8・元ファイル名＝スラッグ） |
| `mu-plugin__tb-html-permalinks.php` | `.html` URL を維持する must-use プラグイン |
| `../teachingbeauty-theme/` | 現行デザイン再現テーマ |
| `www/`（バックアップ） | 元の画像一式（同一パスへアップロードして画像を再現） |

## 手順（FTP・WordPress 準備後）
1. **バックアップ**：切替前にサーバー全体をFTPで完全バックアップ。
2. **WordPress設置**：ロリポップにWordPressをインストール（まずはサブディレクトリ `/wp/` で検証推奨）。
3. **テーマ**：`teachingbeauty-theme/` を `wp-content/themes/` に配置し有効化。
4. **画像**：バックアップ `www/` 内の画像（`image/` フォルダ含む）を、**サイトルートの同じパス**へアップロード。
   → 本文中の相対パス（例 `IMG_3831.png`, `image/rogo12.jpg`）がそのまま表示される。WordPress 既定の .htaccess は実ファイルを優先するため、画像は WordPress を経由せず表示される。
5. **インポート**：「ツール → インポート → WordPress」で `teachingbeauty.wxr` を取り込む（全ページが作成される）。
6. **.html 維持**：`mu-plugin__tb-html-permalinks.php` を `wp-content/mu-plugins/` に置く（フォルダが無ければ作成）。
7. **設定**：
   - 「設定 → 表示設定」→ ホームページに固定ページ「home」を指定。
   - 「設定 → パーマリンク」を開いて保存（リライト反映）。
   - 「外観 → メニュー」で現行と同じナビ（トップ/当院について/治療内容・料金/キャンペーン・割引/無料相談/ご予約）を作成。
8. **確認**：`/concept.html` などが現行と同じ URL で表示されることを確認。

## 補足
- ページの統合や新URLは**行っていない**（kokushi.html と newpage10.html も別ページのまま）。
- インデックス重複（`/` と `/index.html`）については、URL を変えずに canonical で正規化する対応を mu-plugin に含めている。将来 URL 整理をご希望の場合のみ別途対応。
- 生成は 2026-08-29、`content-inventory.csv`（41ページ棚卸し）に基づく。
