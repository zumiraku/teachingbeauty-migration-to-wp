<?php
/**
 * 本文の不具合を修正する一時スクリプト（2026-09-21 の全ページ監査で見つかったもの）。実行後は削除すること。
 *
 * 置き場所: /wp/tb-fix-content.php
 *   ?k=（トークン）&dry=1  … 変更予定を表示するだけ
 *   ?k=（トークン）        … 適用
 *
 * 修正内容:
 *  1. newpage23: <a href="https://www.teachingbeauty.jp/newpage13.html>…  （閉じ引用符の欠落・元サイトからの不具合）
 *     → リンク先が本文ごと URL になり 404 になっていた。引用符を補う。
 *  2. home: <script src="http://platform.twitter.com/widgets.js"> （https ページ内の http スクリプト＝ブラウザがブロック）
 *     → 元サイトでも読み込まれておらず表示に影響しないため削除。ツイートボタンのリンクは https に。
 * 変更は上記の文字列だけ。他の本文には触れない。
 */

header( 'Content-Type: text/plain; charset=UTF-8' );
if ( ! isset( $_GET['k'] ) || '__TB_TOKEN__' !== $_GET['k'] ) {
	http_response_code( 404 );
	exit( 'not found' );
}
$dry = ! empty( $_GET['dry'] );
define( 'WP_USE_THEMES', false );
require_once __DIR__ . '/wp-load.php';
global $wpdb;

$fixes = array(
	array(
		'slug'    => 'newpage23',
		'label'   => 'newpage13 リンクの閉じ引用符',
		'search'  => 'href="https://www.teachingbeauty.jp/newpage13.html>突発性難聴</a>',
		'replace' => 'href="https://www.teachingbeauty.jp/newpage13.html">突発性難聴</a>',
	),
	array(
		'slug'    => 'home',
		'label'   => 'Twitter の http スクリプト（混在コンテンツ）を削除',
		'search'  => '<script type="text/javascript" src="http://platform.twitter.com/widgets.js" charset="utf-8"></script>',
		'replace' => '',
	),
	array(
		'slug'    => 'home',
		'label'   => 'ツイートボタンのリンクを https に',
		'search'  => '<a href="http://twitter.com/share"',
		'replace' => '<a href="https://twitter.com/share"',
	),
);

echo $dry ? "== ドライラン（変更しません）==\n\n" : "== 適用 ==\n\n";
$applied = 0; $missing = 0;
foreach ( $fixes as $f ) {
	$page = get_page_by_path( $f['slug'] );
	if ( ! $page ) { echo "! ページが見つかりません: {$f['slug']}\n"; $missing++; continue; }
	$n = substr_count( $page->post_content, $f['search'] );
	echo "[{$f['slug']}] {$f['label']}: " . ( $n ? "{$n} 箇所" : '該当なし（修正済みか）' ) . "\n";
	if ( ! $n ) { continue; }
	if ( ! $dry ) {
		$new = str_replace( $f['search'], $f['replace'], $page->post_content );
		$wpdb->update( $wpdb->posts, array( 'post_content' => $new ), array( 'ID' => $page->ID ) );
		clean_post_cache( $page->ID );
		$applied += $n;
	}
}
echo "\n" . ( $dry ? '（ドライラン：何も変更していません）' : "適用 {$applied} 箇所" ) . "\n";
echo '[STATUS ' . ( $dry ? 'DRY' : 'DONE' ) . " applied={$applied} missing={$missing}]\n";
