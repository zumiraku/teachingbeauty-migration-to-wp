# 検証レポート：WordPress 編集画面からの編集は現行ページを壊す（2026-09-17）

クライアントから「トップページの写真2枚を自分で差し替えたい」との相談を受け、
ローカルに **ステージングと同一構成（WordPress 7.1 / PHP 8.3 / SQLite / 本テーマ / 原本HTML）** を再現し、
Chrome の自動操作（Playwright）で実際の編集フローを検証した。

## 結論

| 検証項目 | 結果 |
|---|---|
| 画像詳細ダイアログに「置換」ボタンが出るか | 出ない。原本の `<img src="IMG_3831.png">` はメディアライブラリ未登録のため（`data.attachment` が無い） |
| クラシックエディターに「メディアを追加」があるか | 無い。クラシックブロックのモーダルは `mediaButtons` 無しで TinyMCE を初期化している |
| 画像をメディア登録した上で「置換」→保存 | 画像は差し替わるが、**保存時にページ全体が TinyMCE により再整形される** |
| 文章を3文字（「テスト」）足して保存 | **同じくページ全体が再整形される** |

再整形の内容：`<font>` 146個 → すべて `<span style>` に変換、`<br>` 連続・全角スペースによる余白が消失、
インデント変更。**表示上、見出しと本文が同じ行に繋がり、ヘッダーの電話番号が巨大化して折り返し、
ページの高さが 7094px → 4806px に縮む**（`editor-save-before.png` / `editor-save-after.png`）。

→ **現行の as-is 方式（原本HTMLをそのまま出力）では、WordPress の編集画面から保存した時点でレイアウトが壊れる。
写真の差し替えも文章の修正も、編集画面からは行えない。**

## 根拠（WordPress 本体のソース）

- `wp-includes/media-template.php`：
  `<# if ( data.attachment && window.imageEdit ) { #> … replace-attachment …`
  → 「置換」はメディアライブラリの添付ファイルにのみ表示。
- `wp-admin/js/editor.js` `wp.editor.initialize()`：
  「メディアを追加」は `settings.tinymce && settings.quicktags` かつ `settings.mediaButtons` の時のみ描画。
- Gutenberg `packages/block-library/src/freeform/modal.jsx`：
  `wp.oldEditor.initialize( id, { tinymce: {...} } )` → quicktags / mediaButtons 指定なし。
- 同 modal.jsx：「保存」は `onChange( wp.oldEditor.getContent( id ) )` → TinyMCE が再シリアライズした HTML で本文を上書き。

## 検証手順（再現可能）

1. WordPress 7.1 日本語版 + sqlite-database-integration 3.0.2 をローカルに設置（PHP 8.3 / SQLite 3.53）。
2. `teachingbeauty-theme` を有効化、`teachingbeauty-fullbody.wxr` の `home` を投入（本文 21,806 バイト・原本と一致を確認）。
3. Playwright（Chrome）で：ログイン → 固定ページ → クラシックブロック「編集」→ 画像 → 鉛筆 → 画像詳細
   → `.replace-attachment` の有無を確認（0件）。
4. 2枚を `media_handle_sideload` で登録し `<img>` に `class="wp-image-ID"` を付与 → 「置換」表示を確認（1件）。
5. 「置換」→ アップロード → サイズ「中」→ 置換 → モーダル保存 → ページ保存 → 本文を取得し diff。
6. 別途、文章を3文字追加して保存 → 同様に diff とスクリーンショット比較。

## 影響と対応方針

- **当面**：写真の差し替え・文章の修正は、すべて開発側で行う。
  - 写真：元と同じファイル名・同じ寸法（`IMG_3831.png` 300×303、`0C9974F4-….jpg` 330×302）で
    サーバー上のファイルを置き換える。HTML には触れないため崩れない。
  - 文章：WXR 生成と同じ要領で `$wpdb` 直書き（`fix-form2.php` / `fix-titles.php` と同じ手法）。
- **クライアントへの案内を訂正**：「固定ページから文章を直せます」は現状では誤り。編集画面からの保存は行わないよう依頼。
- **次の一手（提案）**：編集画面を経由せずに写真だけ差し替えられる専用の管理画面（mu-plugin）を用意する。
  ページ内の画像を一覧表示 → 新しい写真をアップロード → 元の寸法にリサイズして同名で上書き。
  HTML を一切変更しないため、as-is の忠実性を保ったまま自己完結できる。

## 参考画像

- `editor-image-details-no-replace.png` … 画像詳細に「置換」が無い状態（クライアントの報告と一致）
- `editor-save-before.png` … 原本HTMLのトップページ（上部1400px）
- `editor-save-after.png` … 編集画面から一度保存した後のトップページ（同範囲）
