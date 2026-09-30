<?php
/**
 * サイドバーの店舗情報を全ページで揃える。
 * /wp/ に置いて、使ったら消す。
 *   ?k=TOKEN&dry=1     … 変更予定を出すだけ
 *   ?k=TOKEN           … 適用
 *   ?k=TOKEN&restore=1 … 元に戻す
 *
 * サイドバーは各ページの本文の中にそれぞれ書き込まれているため、
 * 更新のたびにバラバラになっていた。39ページで19通りあった。
 * 違いは余白だけでなく中身にも及ぶ:
 *   部屋番号  301号室 / 3F / 3階
 *   見出し    Ｔｅａｃｈｉｎｇ Ｂｅａｕｔｙ / TeachingBeauty鍼灸整骨院 / shop info.店舗情報
 *   診療時間  5通り（夜間 21時まで33ページ / 23時まで / 24時間診療 / 日曜の有無）
 *
 * 一番数の多いトップページのものに揃える（診療時間はこの形が33ページ）。
 * 部屋番号は 301号室。アクセスのページの道案内も「3階の301」なので矛盾しない。
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

const TB_SHOPINFO_BACKUP = '_tb_shopinfo_backup';
// 店舗情報の箱をまるごと1つ。中に div の入れ子は無いので最初の </div> まで。
const TB_SHOPINFO_RE = '#<div id="shopinfo">.*?</div>#s';

$canon = <<<'TBSHOPINFO'
<div id="shopinfo"><h3>Ｔｅａｃｈｉｎｇ　Ｂｅａｕｔｙ</h3><img class="wp-image-1111" src="https://www.teachingbeauty.jp/wp/wp-content/uploads/2020/01/rogo12.jpg" style="border-width: 0px;" alt="ＴｅａｃｈｉｎｇＢｅａｕｔｙ鍼灸マッサージ整骨院" width="219" height="130" border="0"><br><br><br><font size="3">〒154-0014<br>東京都世田谷区新町3-21-1<br>さくらウェルガーデン301号室<br><br>TEL：</font><br><font size="6" color="#91384b"><a href="tel:0364137803">03-6413-7803</a><br><a href="access.html"><font size="4" color="#91384b">→アクセス</font></a></font><br><br><br><b><font size="4">診療時間</font></b><font size="3"><br>【通常診療】<br><b>月火金土</b><br>午前：09:00～13:00<br>午後：15:00～19:00<br><b>木曜日</b><br>午前：休診<br>午後：15:00～19:00</font><br><font size="1">※当日予約可</font><br><font size="3"><br><font size="3" color="#ff0000"> 休診日</font><br></font><font size="3" color="#ff0000">水</font><font size="3">、</font><font size="3" color="#ff0000">木(午前)</font><font size="3">、</font><font size="3" color="#ff0000">日</font><font size="3">、</font><font size="3" color="#ff0000">祭日</font><font size="3"><br><br>夜間診療<br>月火木金土／19:00～21:00<br></font><font size="1">※通常価格の1.5倍料金<br><font color="#0000ff">※当日の19時までにご予約の場合のみ</font><br>※往診の場合も19時までにご予約下さい<br></font><font size="1" color="#0000ff"><br></font><font size="3">早朝診療<br>月火木金土／07:00～09:00<br></font><font size="1">※通常価格の2倍料金<br><font color="#0000ff">※前日の19時までにご予約の場合のみ</font></font><font size="1" color="#0000ff"><br></font><font size="3"><br><br>交通事故・労災・<br>各種保険取扱い</font><br></div>
TBSHOPINFO;

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
		$old = get_post_meta( $page->ID, TB_SHOPINFO_BACKUP, true );
		if ( '' === $old ) {
			continue;
		}
		$wpdb->update( $wpdb->posts, array( 'post_content' => $old ), array( 'ID' => $page->ID ) );
		clean_post_cache( $page->ID );
		delete_post_meta( $page->ID, TB_SHOPINFO_BACKUP );
		printf( "%-16s 戻しました\n", $page->post_name );
		$n++;
	}
	printf( "\n%d ページを元に戻しました\n", $n );
	echo "STATUS RESTORED\n";
	exit;
}

$touched = 0;
$already = 0;
$abort   = false;

foreach ( $pages as $page ) {
	$before = $page->post_content;
	$hits   = preg_match_all( TB_SHOPINFO_RE, $before );

	if ( 1 !== $hits ) {
		printf( "%-16s 店舗情報が %d 個（1つのはず）\n", $page->post_name, $hits );
		$abort = true;
		continue;
	}

	$n     = 0;
	$after = preg_replace( TB_SHOPINFO_RE, $canon, $before, -1, $n );
	if ( null === $after || 1 !== $n ) {
		printf( "%-16s 置き換えに失敗\n", $page->post_name );
		$abort = true;
		continue;
	}
	if ( $before === $after ) {
		printf( "%-16s 済 すでに同じ\n", $page->post_name );
		$already++;
		continue;
	}
	// 電話とアクセスのリンクが入っているか
	if ( false === strpos( $after, 'tel:0364137803' ) || false === strpos( $after, 'access.html' ) ) {
		printf( "%-16s 電話かアクセスのリンクが消える\n", $page->post_name );
		$abort = true;
		continue;
	}

	printf( "%-16s OK 店舗情報を揃える\n", $page->post_name );
	$touched++;

	if ( $dry ) {
		continue;
	}

	if ( '' === get_post_meta( $page->ID, TB_SHOPINFO_BACKUP, true ) ) {
		update_post_meta( $page->ID, TB_SHOPINFO_BACKUP, wp_slash( $before ) );
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

printf( "\n揃えた %d ページ / すでに同じ %d ページ\n", $touched, $already );
echo $dry ? "STATUS DRY\n" : "STATUS DONE\n";
