<?php
/**
 * <br> の直後に残っている改行とインデントを取り除く。/wp/ に置いて、使ったら消す。
 *   ?k=TOKEN&dry=1 … 変更予定を出すだけ
 *   ?k=TOKEN       … 適用
 *
 * 旧サイトの HTML はホームページビルダーが吐いたもので、行頭に半角スペースが10個ほど入っている。
 * HTML は空白をまとめるので Chrome では消えるが、Safari は <br> の直後の空白を1文字分描画する。
 * そのため iPhone で見ると行の頭が揃わない。空白そのものを消せばどのブラウザでも揃う。
 *
 * 消すのは <br> の直後の空白だけ。文中の空白や &nbsp; には触れない。
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

/**
 * <br> の直後の空白を落とす。
 */
function tb_strip_space_after_br( $html ) {
	return preg_replace( '/(<br\s*\/?>)[ \t\r\n]+/i', '$1', $html );
}

$pages = get_posts(
	array(
		'post_type'      => 'page',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'orderby'        => 'ID',
		'order'          => 'ASC',
	)
);

// 直す前の本文を postmeta に取っておく。やり直したくなったら ?restore=1 で戻せる。
const TB_BR_BACKUP = '_tb_br_space_backup';

$touched = 0;
$total   = 0;

if ( $restore ) {
	foreach ( $pages as $page ) {
		$old = get_post_meta( $page->ID, TB_BR_BACKUP, true );
		if ( '' === $old ) {
			continue;
		}
		$wpdb->update( $wpdb->posts, array( 'post_content' => $old ), array( 'ID' => $page->ID ) );
		clean_post_cache( $page->ID );
		delete_post_meta( $page->ID, TB_BR_BACKUP );
		printf( "%-16s 戻しました\n", $page->post_name );
		$touched++;
	}
	printf( "\n%d ページを元に戻しました\n", $touched );
	echo "STATUS RESTORED\n";
	exit;
}

foreach ( $pages as $page ) {
	$before = $page->post_content;
	$after  = tb_strip_space_after_br( $before );
	if ( $before === $after ) {
		continue;
	}

	preg_match_all( '/(<br\s*\/?>)[ \t\r\n]+/i', $before, $m );
	$n      = count( $m[0] );
	$total += $n;
	$touched++;

	printf( "%-16s %4d 箇所  %d → %d バイト\n", $page->post_name, $n, strlen( $before ), strlen( $after ) );

	if ( ! $dry ) {
		if ( '' === get_post_meta( $page->ID, TB_BR_BACKUP, true ) ) {
			update_post_meta( $page->ID, TB_BR_BACKUP, wp_slash( $before ) ); // メタも wp_unslash() されるので先に付けておく
		}
		// wp_update_post() は使わない。ログインしていない状態だと KSES が働いて
		// 旧サイトの閉じ忘れタグ（<font ... <br> のような形）を書き換えてしまうし、
		// 渡した値を wp_unslash() するので料金の「\45,000」のバックスラッシュも消える。
		$wpdb->update( $wpdb->posts, array( 'post_content' => $after ), array( 'ID' => $page->ID ) );
		clean_post_cache( $page->ID );
	}
}

printf( "\n対象 %d ページ / 合計 %d 箇所\n", $touched, $total );
echo $dry ? "STATUS DRY\n" : "STATUS DONE\n";
