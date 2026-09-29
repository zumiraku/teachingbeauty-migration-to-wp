<?php
/**
 * トップページに「テレビ・メディア出演実績」の見出しバーを入れる。
 * /wp/ に置いて、使ったら消す。
 *   ?k=TOKEN&dry=1     … 変更予定を出すだけ
 *   ?k=TOKEN           … 適用
 *   ?k=TOKEN&restore=1 … 元に戻す
 *
 * 大きさと色は他のバーと同じ size="5" / #480a17。
 * enhance.css 側で、この形の見出しは帯の目印の中心に自動で揃うようにしてある。
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

const TB_MEDIABAR_BACKUP = '_tb_mediabar_backup';

$fixes = array(
	array(
		'slug'  => 'home',
		'label' => '「テレビ・メディア出演実績」の見出しバーを入れる',
		'from'  => '</div><div><font size="4"><strong><font size="5" color="#0000ff">【H31/2/21放送】',
		'to'    => '</div><h3><font size="5" color="#480a17">テレビ・メディア出演実績</font></h3><div><font size="4"><strong><font size="5" color="#0000ff">【H31/2/21放送】',
	),
);

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
		$old = get_post_meta( $page->ID, TB_MEDIABAR_BACKUP, true );
		if ( '' === $old ) {
			continue;
		}
		$wpdb->update( $wpdb->posts, array( 'post_content' => $old ), array( 'ID' => $page->ID ) );
		clean_post_cache( $page->ID );
		delete_post_meta( $page->ID, TB_MEDIABAR_BACKUP );
		printf( "%-16s 戻しました\n", $page->post_name );
		$n++;
	}
	printf( "\n%d ページを元に戻しました\n", $n );
	echo "STATUS RESTORED\n";
	exit;
}

$by_slug = array();
foreach ( $pages as $page ) {
	$by_slug[ $page->post_name ] = $page;
}

$work  = array();
$abort = false;
$total = 0;

foreach ( $fixes as $fix ) {
	$slug = $fix['slug'];
	if ( ! isset( $by_slug[ $slug ] ) ) {
		printf( "%-16s ページが見つからない\n", $slug );
		$abort = true;
		continue;
	}
	$id  = $by_slug[ $slug ]->ID;
	$cur = isset( $work[ $id ] ) ? $work[ $id ] : $by_slug[ $slug ]->post_content;

	$hits = substr_count( $cur, $fix['from'] );
	if ( 0 === $hits ) {
		if ( false !== strpos( $cur, $fix['to'] ) ) {
			printf( "%-16s 済 %s\n", $slug, $fix['label'] );
			continue;
		}
		printf( "%-16s 見つからない: %s\n", $slug, $fix['label'] );
		$abort = true;
		continue;
	}
	if ( $hits > 1 ) {
		printf( "%-16s %d 箇所あって絞れない: %s\n", $slug, $hits, $fix['label'] );
		$abort = true;
		continue;
	}

	$work[ $id ] = str_replace( $fix['from'], $fix['to'], $cur );
	printf( "%-16s OK %s\n", $slug, $fix['label'] );
	$total++;
}

if ( $abort ) {
	echo "\nSTATUS ABORT\n";
	exit;
}

foreach ( $work as $id => $after ) {
	$before = get_post_field( 'post_content', $id );
	if ( $before === $after ) {
		continue;
	}
	foreach ( array( 'h3', 'font', 'div' ) as $tag ) {
		$d1 = preg_match_all( "/<$tag\b/i", $before ) - preg_match_all( "/<\/$tag>/i", $before );
		$d2 = preg_match_all( "/<$tag\b/i", $after ) - preg_match_all( "/<\/$tag>/i", $after );
		if ( $d1 !== $d2 ) {
			printf( "%s の開閉が合わない（前 %d / 後 %d）\n", $tag, $d1, $d2 );
			echo "\nSTATUS ABORT\n";
			exit;
		}
	}
	if ( $dry ) {
		continue;
	}
	if ( '' === get_post_meta( $id, TB_MEDIABAR_BACKUP, true ) ) {
		update_post_meta( $id, TB_MEDIABAR_BACKUP, wp_slash( $before ) );
	}
	// wp_update_post() は使わない。KSES が旧サイトの閉じ忘れタグを書き換えるし、
	// 渡した値を wp_unslash() するので本文中のバックスラッシュも消える。
	$wpdb->update( $wpdb->posts, array( 'post_content' => $after ), array( 'ID' => $id ) );
	clean_post_cache( $id );
}

printf( "\n見出しバー %d 本\n", $total );
echo $dry ? "STATUS DRY\n" : "STATUS DONE\n";
