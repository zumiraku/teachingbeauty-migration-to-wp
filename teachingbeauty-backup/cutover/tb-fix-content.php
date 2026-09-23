<?php
/**
 * 本文の細かい直しを流すための使い捨てスクリプト。/wp/ に置いて、使ったら消す。
 *   ?k=TOKEN&dry=1 … 変更予定を出すだけ
 *   ?k=TOKEN       … 適用
 *
 * 下の $fixes に書いた文字列だけを置き換える。他の本文には触らない。
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

// サイドバーの TEL／→アクセスの書き方が、旧サイトの時点で home / newpage13 / taiban だけ他と違う。
// 矢印が size の外に出ていたり <a> と <font> の入れ子が逆だったりする。他のページと同じ形に揃える。
$std_tel_block = '<font size="6" color="#91384b"><a href="tel:0364137803">03-6413-7803</a><br>'
	. "\n" . '<br>'
	. "\n" . '<a href="access.html"><font size="4" color="#91384b">→アクセス</font></a></font>';

$fixes = array(
	array(
		'slug'    => 'home',
		'label'   => 'サイドバーの TEL／→アクセス を標準の形に',
		'search'  => '<a href="tel:03-6413-7803"> <font size="6" color="#91384b">03-6413-7803</font></a> <br><br><a href="access.html">→</a><b><font size="5"><a href="access.html">アクセス</a> </font></b>',
		'replace' => $std_tel_block,
	),
	array(
		'slug'    => 'newpage13',
		'label'   => 'サイドバーの TEL／→アクセス を標準の形に',
		'search'  => "<a href=\"tel:03-6413-7803\">\n        <font size=\"6\" color=\"#91384b\">03-6413-7803</font></a>\n<br>\n        <br>\n        <a href=\"access.html\">→</a><b><font size=\"5\"><a href=\"access.html\">アクセス</a> </font></b>",
		'replace' => $std_tel_block,
	),
	array(
		'slug'    => 'taiban',
		'label'   => 'サイドバーの TEL／→アクセス を標準の形に',
		'search'  => "<font size=\"3\" color=\"#91384b\"><a href=\"tel:0364137803\"><font size=\"5\" color=\"#91384b\"><b>03-6413-7803</b></font></a><br>\n          <br>\n          <a href=\"access.html\"><font color=\"#91384b\">→アクセス</font></a></font>",
		'replace' => $std_tel_block,
	),
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
