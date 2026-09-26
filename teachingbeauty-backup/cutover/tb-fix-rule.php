<?php
/**
 * 「ーーーーー…」と長音記号を並べて引いてある区切り線を <hr> に置き換える。/wp/ に置いて、使ったら消す。
 *   ?k=TOKEN&dry=1    … 変更予定を出すだけ
 *   ?k=TOKEN          … 適用
 *   ?k=TOKEN&restore=1 … 元に戻す
 *
 * 文字を並べた線は長さが固定なので、画面が狭いと途中で折り返して2行になる。
 * <hr> なら中身の幅に合わせて1本で引かれる。見た目は他のページの区切り線と同じになる。
 *
 * 長音記号（ー）が8個以上並んでいるものだけを対象にする。
 * newpage10 の設問にある「１.Ｇ細胞　―――――　ガストリン」は別の文字（―）で、
 * 答えを埋めるための線なので触らない。
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

const TB_RULE_BACKUP = '_tb_rule_backup';
const TB_RULE_RE     = '/\x{30FC}{8,}/u';

$pages = get_posts(
	array(
		'post_type'      => 'page',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'orderby'        => 'ID',
		'order'          => 'ASC',
	)
);

$touched = 0;
$total   = 0;

if ( $restore ) {
	foreach ( $pages as $page ) {
		$old = get_post_meta( $page->ID, TB_RULE_BACKUP, true );
		if ( '' === $old ) {
			continue;
		}
		$wpdb->update( $wpdb->posts, array( 'post_content' => $old ), array( 'ID' => $page->ID ) );
		clean_post_cache( $page->ID );
		delete_post_meta( $page->ID, TB_RULE_BACKUP );
		printf( "%-16s 戻しました\n", $page->post_name );
		$touched++;
	}
	printf( "\n%d ページを元に戻しました\n", $touched );
	echo "STATUS RESTORED\n";
	exit;
}

foreach ( $pages as $page ) {
	$before = $page->post_content;
	if ( ! preg_match_all( TB_RULE_RE, $before, $m ) ) {
		continue;
	}

	$n      = count( $m[0] );
	$total += $n;
	$touched++;
	printf( "%-16s %d 本\n", $page->post_name, $n );
	foreach ( $m[0] as $hit ) {
		printf( "    長音記号 %d 個\n", mb_strlen( $hit, 'UTF-8' ) );
	}

	if ( $dry ) {
		continue;
	}

	$after = preg_replace( TB_RULE_RE, '<hr>', $before );
	if ( '' === get_post_meta( $page->ID, TB_RULE_BACKUP, true ) ) {
		update_post_meta( $page->ID, TB_RULE_BACKUP, wp_slash( $before ) );
	}
	// wp_update_post() は使わない。KSES が旧サイトの閉じ忘れタグを書き換えるし、
	// 渡した値を wp_unslash() するので本文中のバックスラッシュも消える。
	$wpdb->update( $wpdb->posts, array( 'post_content' => $after ), array( 'ID' => $page->ID ) );
	clean_post_cache( $page->ID );
}

printf( "\n対象 %d ページ / 合計 %d 本\n", $touched, $total );
echo $dry ? "STATUS DRY\n" : "STATUS DONE\n";
