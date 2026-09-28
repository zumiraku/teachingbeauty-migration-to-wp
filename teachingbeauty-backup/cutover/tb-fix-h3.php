<?php
/**
 * トップページの「不妊症施術」と【TeachingBeauty…の思い】を見出しバーにする。
 * /wp/ に置いて、使ったら消す。
 *   ?k=TOKEN&dry=1     … 変更予定を出すだけ
 *   ?k=TOKEN           … 適用
 *   ?k=TOKEN&restore=1 … 元に戻す
 *
 * 見出し3を当てるとまわりを巻き込んで変なところに帯が出る、という件。
 * <h3> はそれ自体がひとつのカタマリになるので、</div><div> で割らなくても
 * <h3> で囲んだ時点でその行だけが独立する。だから囲むのがいちばん確実。
 *
 * 文字の大きさは元の指定（font-size:200% + size="+3"）のままだと帯からはみ出す。
 * 実測して、
 *   不妊症施術            size="6"（32px）… 新着情報とぴったり同じ。帯46pxに収まる
 *   【…の思い】           size="5"（24px）… size="6" だと4行に折り返すので1行に収まる方
 * にしてある。
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

const TB_H3_BACKUP = '_tb_h3_backup';

$fixes = array(
	array(
		'slug'  => 'home',
		'label' => '「不妊症施術」を見出しバーに',
		'from'  => '<div><font style="font-size: 150%;" size="+0"><font style="font-size: 200%;" size="+3" color="#0000ff"><b>不妊症施術<br></b></font></font><br></div>',
		'to'    => '<h3><font size="6" color="#0000ff">不妊症施術</font></h3>',
	),
	array(
		'slug'  => 'home',
		'label' => '【TeachingBeauty…の思い】を見出しバーに',
		'from'  => '</font></a><b><br>【TeachingBeauty鍼灸マッサージ整骨院・整体院の思い】</b><br></font><font size="4" face="AR Roman1 Bold">',
		'to'    => '</font></a></font><h3><font size="5" color="#0000cc">【TeachingBeauty鍼灸マッサージ整骨院・整体院の思い】</font></h3><font size="4" face="AR Roman1 Bold">',
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
		$old = get_post_meta( $page->ID, TB_H3_BACKUP, true );
		if ( '' === $old ) {
			continue;
		}
		$wpdb->update( $wpdb->posts, array( 'post_content' => $old ), array( 'ID' => $page->ID ) );
		clean_post_cache( $page->ID );
		delete_post_meta( $page->ID, TB_H3_BACKUP );
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
	// 開いたままのタグを作っていないか、<font> と <div> の数で念のため確かめる。
	foreach ( array( 'font', 'div' ) as $tag ) {
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
	if ( '' === get_post_meta( $id, TB_H3_BACKUP, true ) ) {
		update_post_meta( $id, TB_H3_BACKUP, wp_slash( $before ) );
	}
	// wp_update_post() は使わない。KSES が旧サイトの閉じ忘れタグを書き換えるし、
	// 渡した値を wp_unslash() するので本文中のバックスラッシュも消える。
	$wpdb->update( $wpdb->posts, array( 'post_content' => $after ), array( 'ID' => $id ) );
	clean_post_cache( $id );
}

printf( "\n見出しバー %d 箇所\n", $total );
echo $dry ? "STATUS DRY\n" : "STATUS DONE\n";
