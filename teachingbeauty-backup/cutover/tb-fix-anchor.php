<?php
/**
 * ページ内リンクの目印（<a name="...">）が文字を囲んでいるのを、空のまま置く形に直す。
 * /wp/ に置いて、使ったら消す。
 *   ?k=TOKEN&dry=1     … 変更予定を出すだけ
 *   ?k=TOKEN           … 適用
 *   ?k=TOKEN&restore=1 … 元に戻す
 *
 * <a name="kafun">鼻疾患（…）</a> のように、目印が見出しの文字ごと囲んでいる。
 * 編集画面のエディタは <a name> を「9px の小さな目印」として描くので、
 * 中の文字がその幅に押し込められて縦一列になる（menu の見出しで31行・幅11px）。
 *
 * <a name="kafun"></a>鼻疾患（…） にすれば、リンクの飛び先はそのままで文字が外に出る。
 * 表示側は変わらない（menu の本文全体をピクセル比較して一致を確認済み）。
 */

$cli = ( PHP_SAPI === 'cli' );
if ( ! $cli ) {
	header( 'Content-Type: text/plain; charset=UTF-8' );
	if ( ! isset( $_GET['k'] ) || '__TB_TOKEN__' !== $_GET['k'] ) {
		http_response_code( 404 );
		exit( 'not found' );
	}
	$dry     = ! empty( $_GET['dry'] );
	$restore = ! empty( $_GET['restore'] );
	define( 'WP_USE_THEMES', false );
	require_once __DIR__ . '/wp-load.php';
} else {
	$dry     = in_array( '--dry', $argv, true );
	$restore = in_array( '--restore', $argv, true );
	$root = getenv( 'TB_WP_ROOT' ) ?: __DIR__;
	$_SERVER['HTTP_HOST']   = getenv( 'TB_HOST' ) ?: '127.0.0.1:8765';
	$_SERVER['REQUEST_URI'] = '/';
	require_once $root . '/wp-load.php';
}
global $wpdb;

const TB_ANCHOR_BACKUP = '_tb_anchor_backup';
// name はあるが href が無いもの＝ページ内リンクの目印。中に文字が入っているものだけを拾う。
const TB_ANCHOR_RE = '/<a name="([^"]*)"((?![^>]*href)[^>]*)>([^<]+)<\/a>/i';

$pages = get_posts(
	array(
		'post_type'      => 'page',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'orderby'        => 'ID',
		'order'          => 'ASC',
	)
);

if ( $restore ) {
	$n = 0;
	foreach ( $pages as $page ) {
		$old = get_post_meta( $page->ID, TB_ANCHOR_BACKUP, true );
		if ( '' === $old ) {
			continue;
		}
		$wpdb->update( $wpdb->posts, array( 'post_content' => $old ), array( 'ID' => $page->ID ) );
		clean_post_cache( $page->ID );
		delete_post_meta( $page->ID, TB_ANCHOR_BACKUP );
		printf( "%-16s 戻しました\n", $page->post_name );
		$n++;
	}
	printf( "\n%d ページを元に戻しました\n", $n );
	echo "STATUS RESTORED\n";
	exit;
}

$touched = 0;
$total   = 0;

foreach ( $pages as $page ) {
	$before = $page->post_content;
	$n      = 0;
	$after  = preg_replace( TB_ANCHOR_RE, '<a name="$1"$2></a>$3', $before, -1, $n );

	if ( ! $n || $before === $after ) {
		continue;
	}

	preg_match_all( TB_ANCHOR_RE, $before, $m );
	$touched++;
	$total += $n;
	printf( "%-16s %d 箇所\n", $page->post_name, $n );
	foreach ( array_slice( $m[1], 0, 20 ) as $i => $name ) {
		printf( "    #%-14s %s\n", $name, mb_substr( trim( $m[3][ $i ] ), 0, 24, 'UTF-8' ) );
	}

	if ( $dry ) {
		continue;
	}

	if ( '' === get_post_meta( $page->ID, TB_ANCHOR_BACKUP, true ) ) {
		update_post_meta( $page->ID, TB_ANCHOR_BACKUP, wp_slash( $before ) );
	}
	// wp_update_post() は使わない。KSES が旧サイトの閉じ忘れタグを書き換えるし、
	// 渡した値を wp_unslash() するので本文中のバックスラッシュも消える。
	$wpdb->update( $wpdb->posts, array( 'post_content' => $after ), array( 'ID' => $page->ID ) );
	clean_post_cache( $page->ID );
}

printf( "\n対象 %d ページ / 合計 %d 箇所\n", $touched, $total );
echo $dry ? "STATUS DRY\n" : "STATUS DONE\n";
