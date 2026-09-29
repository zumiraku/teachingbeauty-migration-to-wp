<?php
/**
 * サイドバーからアメブロ（スタッフブログ）のリンクを外す。
 * /wp/ に置いて、使ったら消す。
 *   ?k=TOKEN&dry=1     … 変更予定を出すだけ
 *   ?k=TOKEN           … 適用
 *   ?k=TOKEN&restore=1 … 元に戻す
 *
 * このリンクだけバナー画像が無く、他の9本（231x76 の画像）と違って
 * 空っぽの箱として出るため、サイドバーの下に余白があるように見えていた。
 *
 * 書き方がページによって違う（改行やインデントが入る）ので、空白を
 * ゆるく見る正規表現で拾う。全39ページに1本ずつある。
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

const TB_SIDEBAR_BACKUP = '_tb_sidebar_backup';

// アメブロの <li> を丸ごと1つ取る。
// 書き方がページによってばらばらなので、閉じタグや属性をあてにしない。
//   <li><a href="https://...">スタッフブログ</a></li>
//   <li><a href="https://...">スタッフブログ</a>          ← </li> が無い（多数派）
//   <li><font size="3" color="#000000"><a href="https://...">…  ← font が挟まる
//   <li><a href="http://..." id="banner-blog">…              ← http で id つき
// なので「<li> から、次の <li> か </ul> の手前まで」を取る形にする。
// 全39ページで1回ずつ当たること、タグの数が壊れないことを手元で確認済み。
const TB_AMEBLO_RE = '#[\t ]*<li>(?:(?!</?li|</ul)[\s\S])*?<a href="https?://ameblo\.jp/teaching-beauty/"[^>]*>[^<]*</a>(?:(?!<li|</ul)[\s\S])*?(?=<li|</ul)#i';

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
		$old = get_post_meta( $page->ID, TB_SIDEBAR_BACKUP, true );
		if ( '' === $old ) {
			continue;
		}
		$wpdb->update( $wpdb->posts, array( 'post_content' => $old ), array( 'ID' => $page->ID ) );
		clean_post_cache( $page->ID );
		delete_post_meta( $page->ID, TB_SIDEBAR_BACKUP );
		printf( "%-16s 戻しました\n", $page->post_name );
		$n++;
	}
	printf( "\n%d ページを元に戻しました\n", $n );
	echo "STATUS RESTORED\n";
	exit;
}

$touched = 0;
$total   = 0;
$abort   = false;

foreach ( $pages as $page ) {
	$before = $page->post_content;
	$n      = 0;
	$after  = preg_replace( TB_AMEBLO_RE, '', $before, -1, $n );

	if ( null === $after ) {
		printf( "%-16s 置換に失敗\n", $page->post_name );
		$abort = true;
		continue;
	}
	if ( ! $n ) {
		// すでに外してあるページはそのまま
		if ( false === strpos( $before, 'ameblo.jp/teaching-beauty' ) ) {
			printf( "%-16s 済 すでに外れている\n", $page->post_name );
		} else {
			printf( "%-16s 見つけられない形で残っている\n", $page->post_name );
			$abort = true;
		}
		continue;
	}
	if ( $n > 1 ) {
		printf( "%-16s %d 箇所あった（1つのはず）\n", $page->post_name, $n );
		$abort = true;
		continue;
	}

	// タグの数が想定どおり変わっているか。
	// <li> はちょうど1つ減るはず。ul・font・a は増減してはいけない
	// （font や a まで巻き込んで消していたら、ここで止まる）。
	$li1 = preg_match_all( '#<li\b#i', $before );
	$li2 = preg_match_all( '#<li\b#i', $after );
	if ( $li1 - 1 !== $li2 ) {
		printf( "%-16s li の数が合わない（%d→%d、1つ減るはず）\n", $page->post_name, $li1, $li2 );
		$abort = true;
		continue;
	}
	$ng = false;
	foreach ( array( 'ul', 'font', 'a' ) as $tag ) {
		$c1 = preg_match_all( "#<$tag\b#i", $before ) - preg_match_all( "#</$tag>#i", $before );
		$c2 = preg_match_all( "#<$tag\b#i", $after ) - preg_match_all( "#</$tag>#i", $after );
		if ( $c2 !== $c1 ) {
			printf( "%-16s %s の開閉が合わない（%d→%d）\n", $page->post_name, $tag, $c1, $c2 );
			$ng = true;
		}
	}
	if ( $ng ) {
		$abort = true;
		continue;
	}
	if ( false !== strpos( $after, 'ameblo.jp/teaching-beauty' ) ) {
		printf( "%-16s まだ残っている\n", $page->post_name );
		$abort = true;
		continue;
	}

	printf( "%-16s OK アメブロのリンクを外す\n", $page->post_name );
	$touched++;
	$total += $n;

	if ( $dry ) {
		continue;
	}

	if ( '' === get_post_meta( $page->ID, TB_SIDEBAR_BACKUP, true ) ) {
		update_post_meta( $page->ID, TB_SIDEBAR_BACKUP, wp_slash( $before ) );
	}
	// wp_update_post() は使わない。KSES が旧サイトの閉じ忘れタグを書き換えるし、
	// 渡した値を wp_unslash() するので本文中のバックスラッシュも消える。
	$wpdb->update( $wpdb->posts, array( 'post_content' => $after ), array( 'ID' => $page->ID ) );
	clean_post_cache( $page->ID );
}

if ( $abort ) {
	echo "\nSTATUS ABORT\n";
	exit;
}

printf( "\n対象 %d ページ / 外したリンク %d 本\n", $touched, $total );
echo $dry ? "STATUS DRY\n" : "STATUS DONE\n";
