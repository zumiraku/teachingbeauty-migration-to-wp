# QAレポート（リンク・画像・整合性の全数チェック）

ローカルWordPress上で全39ページをクロールし、画像・内部リンク・PHPエラーを全数検査した結果。公開前の品質ゲート。

## 結果サマリー

| 項目 | 数量 | 結果 |
|---|---|---|
| ページHTTP応答 | 39/39 | ✅ 全て200 |
| PHP警告・エラー | 0 | ✅ なし |
| 画像チェック | 309参照 | ✅ 破損0 |
| 内部リンクチェック | 847参照 | ✅ 破損0（下記1件は301で解決） |

ページ別の明細は `qa-pages.csv` を参照。

## QAで発見し修正した不具合

移行方針を「新IA（Stage 2案）」から「as-is」に切り替えた際、テーマ側に新IA向けのリンクが残っていた。全数チェックで検出し、以下を is-as（現行 `.html`）に修正。

| 箇所 | 修正前（誤） | 修正後（as-is） |
|---|---|---|
| サイドバー バナー | `/voice/` `/media/` `/director/` `/flow/` `/jiko/` … | `kanja.html` `nhk.html` `incho.html` `sinkyu.html` `beauty.html` … |
| フッター ナビ | `/recruit/` `/privacy/` | `kyuujin.html` `privacy.html` |
| ナビ フォールバック | `/about/` `/menu/` … | `concept.html` `menu.html` … |

さらに、本文中の旧URLリンクを 301 で救済（URLは変えず、リンク切れを解消）：

- `/index.html` → `/`（トップ一本化・`/` と `/index.html` の重複も正規化）
- `/funin aen.html`（空白入りの旧不正URL）→ `/funin.html`

これらは `mu-plugin__tb-html-permalinks.php` に実装済み。

## 検査方法
`http://localhost:8080` の全ページを取得し、各ページの `<img src>`（309）と内部 `<a href>`（847）を HEAD/GET で実測。PHP出力に `Warning/Notice/Fatal/Deprecated` が含まれないことを確認。単一スレッドの開発サーバーの特性上、リダイレクト追従のHEADが稀に瞬間的404を返すことがあるが、対象URL（`funin%20aen.html`）は再検証で3/3が200（→ funin.html へ301）であることを確認済み。

## 判定
**PASS** — 公開前の品質ゲートを通過。破損リンク・破損画像・PHPエラーはいずれも0。
