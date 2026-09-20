# Teaching Beauty — WordPress 移行プロジェクト

桜新町 Teaching Beauty 鍼灸整骨院・整体院（[teachingbeauty.jp](https://www.teachingbeauty.jp/)）を、**現行デザイン・現行URLのまま WordPress 化**するためのリポジトリ。

## 方針
現行サイト（ホームページビルダー製・全39ページ）を **1:1 でそのまま** WordPress へ移行する。ページ統合・URL変更・デザイン変更は行わない。既存の `.html` URL をそのまま維持し、検索評価・被リンク・ブックマークを保つ。

## リポジトリ構成

```
.
├─ teachingbeauty-theme/           現行デザインを再現する専用WordPressテーマ
│   ├─ style.css / functions.php   トークン駆動CSS＋テーマ機能
│   ├─ header/footer/sidebar.php   共通パーツ
│   ├─ front-page/page/single/...  テンプレート
│   ├─ inc/nav.js  images/         スクリプト・現行デザイン素材(28点)
│   └─ README.md
│
└─ teachingbeauty-backup/          現状バックアップ＋移行パッケージ
    ├─ www/                        公開サイトの完全ミラー(344ファイル)
    ├─ teachingbeauty.wxr          全39ページのWordPressインポート(UTF-8)
    ├─ mu-plugin__tb-html-permalinks.php   .html URL維持プラグイン
    ├─ MIGRATION-as-is.md          1:1移行の手順書
    ├─ content-inventory.csv       全41ページの棚卸し表
    ├─ redirect-map.csv            （将来URL整理する場合のみ／現状は未使用）
    ├─ manifest.csv                全ファイルのSHA256
    └─ README-backup.txt           バックアップ取得条件
```

## 移行手順
`teachingbeauty-backup/MIGRATION-as-is.md` を参照。

## 進捗
- [x] Stage 1：現状把握・完全バックアップ・ベースライン計測
- [x] Stage 2：情報設計（※as-is方針により最小化・URL維持）
- [x] Stage 3：現行デザイン再現テーマの実装
- [x] as-is 移行パッケージ（WXR＋mu-plugin）作成
- [x] Stage 4：検証環境（サーバー `/wp/`）に設置・表示検証
- [x] 編集環境：クライアントが編集画面から写真・文章を直せるようにし、全39ページで崩れないことを検証（`teachingbeauty-backup/EDITING-SETUP.md`）
- [x] 編集環境のステージング適用（2026-09-20：functions.php 差し替え・mu-plugin 設置・画像237件登録。公開側で確認済み）
- [ ] 公開切替・301確認・公開後モニタリング

## 状態
現時点でサーバー未設置のためローカルで作成。ロリポップ！の FTP・WebDAV アクセス情報の受領後、検証環境で取り込み・確認を行う。
