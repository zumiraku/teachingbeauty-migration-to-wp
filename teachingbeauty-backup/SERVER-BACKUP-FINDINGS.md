# サーバー完全バックアップ 結果と重要な発見（2026-08-29）

ロリポップ！のFTP（`ftp.lolipop.jp` / `main.jp-teachingbeauty`）に接続し、**サーバー全体を完全バックアップ**した。公開前の必須作業。

## バックアップ内容
| 項目 | 値 |
|---|---|
| 取得方法 | WinSCP（FTP・Shift_JISファイル名対応） |
| ファイル数 | **642** |
| サイズ | **84.1 MB** |
| 保存先（ローカル） | `teachingbeauty-server-backup/`（フォルダ）＋ `teachingbeauty-server-backup_2026-08-29.zip` |
| 照合 | `server-manifest.csv`（全642件のSHA256） |
| サーバー | spd121 / フルパス `/home/users/2/main.jp-teachingbeauty/...` |

HTTPミラー（344件/21.9MB）では取得できなかった **サーバー側ファイル**（`.htaccess`・孤立ページ・旧フォルダ）を含む。

## 重要発見①：既存 .htaccess の 301 ルール（要保持）
公開切替時、WordPress は `.htaccess` を上書きするため、以下の既存ルールを**必ずマージ**する（`server-htaccess-original.txt` に原本保存）。

```apache
RewriteEngine On
# http -> https
RewriteCond %{HTTPS} !=on
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
# main.jp -> www.teachingbeauty.jp
RewriteCond %{HTTP_HOST} ^(www\.)?teachingbeauty\.main\.jp$ [NC]
RewriteRule ^ https://www.teachingbeauty.jp%{REQUEST_URI} [R=301,L]
# ssl-lolipop -> www
RewriteCond %{HTTP_HOST} ^main-teachingbeauty\.ssl-lolipop\.jp$ [NC]
RewriteRule ^ https://www.teachingbeauty.jp%{REQUEST_URI} [R=301,L]
# 非www -> www
RewriteCond %{HTTP_HOST} ^teachingbeauty\.jp$ [NC]
RewriteRule ^ https://www.teachingbeauty.jp%{REQUEST_URI} [R=301,L]
DirectoryIndex index.html index.php
Redirect 301 /funin_aen.html https://www.teachingbeauty.jp/funin.html
```
→ WordPress の `.htaccess` の**前**にこれらを配置。`DirectoryIndex` は WordPress 用に `index.php` を優先へ。`funin_aen.html`（アンダースコア）の既存301も維持。

## 重要発見②：サイトマップ外の孤立ページ（67 vs 39）
サーバーには **67 の .html** が存在するが、サイトマップは 39。差分の約23ページは、**39ページのどこからもリンクされていない孤立ページ**（内部リンク・サイトマップに無い）。

- 実コンテンツ（古い/実験）：`newpage3/6/9/11/15/18/20/21/26`、`este`、`nanchoo`、`b_01_koshi`・`b_02_onaka`・`b_03_ityou`・`b_koshi01`、日本語名ページ数点。`newpage26` は 2025-06-06 更新と比較的新しい。
- テスト/システム：`index-test`、`taiban-test`、`welcome`、`taiban20180827.html`(+`.bk_files`)、`index_old/`、`googlee9db13e7722f4047.html`（Search Console 確認ファイル）。

### 方針（as-is・低リスク）
移行対象の **39ページは WordPress 固定ページ**にする。**孤立している静的 .html はサーバー上に静的ファイルのまま残す**。
→ WordPress 既定の `.htaccess` は実ファイルを優先するため、これら孤立ページの URL は**そのまま生き続ける**（変換不要・リンク切れ無し・完全 as-is）。Search Console 確認ファイルも残す。
→ 明らかなテスト/バックアップ（`*-test`、`*20180827*`、`index_old/`）の削除は任意。現状維持でも可（クライアント判断）。

## 次のステップ
1. サーバーの `/wp/`（検証用サブフォルダ）に WordPress を設置し、本パッケージを取り込み → クライアント確認（本番は無停止）。
2. 承認後、本番位置へ切替。`.htaccess` は上記既存ルール＋WordPress＋`.html`維持ルールをマージ。孤立静的ファイルは残置。
