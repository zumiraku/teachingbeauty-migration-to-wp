# Teaching Beauty — WordPress 移行

> **Service.01　Web・アプリ開発**ーーWordPress（専用テーマ）/ PHP

桜新町 Teaching Beauty 鍼灸整骨院・整体院（[teachingbeauty.jp](https://www.teachingbeauty.jp/)）を、**現行デザイン・現行URLのまま WordPress 化**した案件です。

## 課題

現行サイトはホームページビルダー製の静的HTML、全39ページ。更新のたびに制作者の手が要る状態でした。

ただし、このサイトには**予約の導線が通っています。** リニューアルを機にページを統合したりURLを整理したりすると、検索からの流入・被リンク・患者さんのブックマークがそこで切れます。治療院にとっては、それがそのまま予約数に出ます。

## やったこと

**1対1で、そのまま移しました。** ページ統合・URL変更・デザイン変更は行っていません。既存の `.html` URL をそのまま維持し、検索評価・被リンク・ブックマークを保っています。

WordPress は通常 `.html` で終わるURLを前提にしていないため、URLを維持する mu-plugin を用意しました。

あわせて、**クライアントが管理画面から写真と文章を直せる状態**にしています。全39ページで崩れないことを確認済みです。ここを詰めておかないと、結局また制作者に連絡が来ることになります。

## 構成

```
teachingbeauty-theme/           現行デザインを再現する専用WordPressテーマ
  style.css / functions.php     トークン駆動CSS＋テーマ機能
  header/footer/sidebar.php     共通パーツ
  front-page/page/single/...    テンプレート
  inc/nav.js  images/           スクリプト・現行デザイン素材（28点）

teachingbeauty-backup/          現状バックアップ＋移行パッケージ
  www/                          公開サイトの完全ミラー（344ファイル）
  teachingbeauty.wxr            全39ページのWordPressインポート（UTF-8）
  mu-plugin__tb-html-permalinks.php   .html URL維持プラグイン
  MIGRATION-as-is.md            1対1移行の手順書
  content-inventory.csv         全41ページの棚卸し表
  redirect-map.csv              （将来URL整理する場合のみ／現状は未使用）
  manifest.csv                  全ファイルのSHA256
  README-backup.txt             バックアップ取得条件
```

移行手順は `teachingbeauty-backup/MIGRATION-as-is.md` を参照。

## 進捗

- [x] Stage 1：現状把握・完全バックアップ・ベースライン計測
- [x] Stage 2：情報設計（※as-is方針により最小化・URL維持）
- [x] Stage 3：現行デザイン再現テーマの実装
- [x] as-is 移行パッケージ（WXR＋mu-plugin）作成
- [x] Stage 4：検証環境（サーバー `/wp/`）に設置・表示検証
- [x] 編集環境：クライアントが編集画面から写真・文章を直せるようにし、全39ページで崩れないことを検証（`teachingbeauty-backup/EDITING-SETUP.md`）
- [x] 編集環境のステージング適用（2026-09-20：functions.php 差し替え・mu-plugin 設置・画像237件登録。公開側で確認済み）
- [x] 公開切替（2026-09-20：`/wp/` → ルート。本番検証 1737/1737 PASS、301 確認済み。`teachingbeauty-backup/cutover/CUTOVER.md`）
- [ ] 公開後モニタリング（1〜2週間後に `/_old-static/` を削除）

---

## 制作

**図美楽（ずみらく / zumiraku）** — システム・アプリのAIエンジニア

Web・アプリ開発／システム開発・インフラ／AI活用・セキュリティ。設計から運用まで、ひとりで通しています。実務12年。

| 媒体 | リンク | 表示名 |
|---|---|---|
| note | [note.com/zumiraku](https://note.com/zumiraku) | 図美楽 |
| Qiita | [qiita.com/zumiraku](https://qiita.com/zumiraku) | ずみらく |
| Zenn | [zenn.dev/zumiraku](https://zenn.dev/zumiraku) | ズミラク |
| YOUTRUST | [youtrust.jp/users/zumiraku](https://youtrust.jp/users/zumiraku) | 図ミラク |

表記は媒体によって揺れていますが、どれも同じ人間です。英字は zumiraku で統一しています。

返信は平日なら4時間以内、土日祝でも12時間以内。お仕事のご相談は、要件が固まっていない段階からでも構いません。NDAも対応します。
