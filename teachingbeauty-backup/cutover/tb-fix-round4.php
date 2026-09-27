<?php
/**
 * クライアント確認ぶんの細かい直し。/wp/ に置いて、使ったら消す。
 *   ?k=TOKEN&dry=1     … 変更予定を出すだけ
 *   ?k=TOKEN           … 適用
 *   ?k=TOKEN&restore=1 … 元に戻す
 *
 * 1) newpage27: 【患者様の喜びの声】が青い文字のままだったので、他と同じ見出しバーにする。
 * 2) menu: 推拿療法の60分・90分と初診料が税抜のまま残っていた。税込に揃える。
 *          90,000→99,000 / 135,000→148,500 / 3,000→3,300。金額は変わらない。
 * 3) miminari: 49,500円に「(税抜)」と付いていた。49,500 は税込の額なので誤り。
 * 4) newpage23: 「原因、特徴について」だけ文字が他のバーより6px下にある。
 *    見出しの直後に <font size="5"> が開いていて、その中の <br> が1行目の高さを
 *    押し上げていた。<br> を font の外に出して、1行目を見出しだけにする。
 *
 * 何度流しても平気。すでに直っていれば飛ばす。
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

const TB_R4_BACKUP = '_tb_round4_backup';

$fixes = array(
	array(
		'slug'  => 'newpage27',
		'label' => '【患者様の喜びの声】を見出しバーに',
		'from'  => '<b><font color="#0000ff" size="6">【患者様の喜びの声】<br></font></b>',
		'to'    => '<h3><b><font size="4">【患者様の喜びの声】</font></b></h3>',
	),
	array(
		'slug'  => 'menu',
		'label' => '推拿療法60分 90,000円（税抜）→ 99,000円（税込）',
		'from'  => '<font size="4">￥90,000</font><font size="2">（税抜）</font>',
		'to'    => '<font size="4">￥99,000</font><font size="2">（税込）</font>',
	),
	array(
		'slug'  => 'menu',
		'label' => '推拿療法90分 135,000円（税抜）→ 148,500円（税込）',
		'from'  => '<font size="4">￥135,000</font><font size="2">（税抜）</font>',
		'to'    => '<font size="4">￥148,500</font><font size="2">（税込）</font>',
	),
	array(
		'slug'  => 'menu',
		'label' => '初診料 3,000円（税抜）→ 3,300円（税込）',
		'from'  => '<font size="4">￥3,000</font><font size="2">（税抜）</font>',
		'to'    => '<font size="4">￥3,300</font><font size="2">（税込）</font>',
	),
	array(
		'slug'  => 'miminari',
		'label' => '49,500円の「(税抜)」は誤り。(税込) に直す',
		'from'  => '<font size="5">\49,500</font><font size="2" color="#000000">(税抜)</font>',
		'to'    => '<font size="5">\49,500</font><font size="2" color="#000000">(税込)</font>',
	),
	array(
		'slug'  => 'newpage23',
		'label' => '「原因、特徴について」の文字が6px下にズレるのを直す',
		'from'  => '<b>原因、特徴について</b></font><font size="5" color="#000000"><br>',
		'to'    => '<b>原因、特徴について</b></font><br><font size="5" color="#000000">',
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
		$old = get_post_meta( $page->ID, TB_R4_BACKUP, true );
		if ( '' === $old ) {
			continue;
		}
		$wpdb->update( $wpdb->posts, array( 'post_content' => $old ), array( 'ID' => $page->ID ) );
		clean_post_cache( $page->ID );
		delete_post_meta( $page->ID, TB_R4_BACKUP );
		printf( "%-16s 戻しました\n", $page->post_name );
		$n++;
	}
	printf( "\n%d ページを元に戻しました\n", $n );
	echo "STATUS RESTORED\n";
	exit;
}

$touched = 0;
$done    = 0;
$skipped = 0;

foreach ( $pages as $page ) {
	$before = $page->post_content;
	$after  = $before;
	$hits   = array();

	foreach ( $fixes as $f ) {
		if ( $f['slug'] !== $page->post_name ) {
			continue;
		}
		if ( false !== strpos( $after, $f['to'] ) && false === strpos( $after, $f['from'] ) ) {
			printf( "%-16s 済 %s\n", $page->post_name, $f['label'] );
			$skipped++;
			continue;
		}
		if ( false === strpos( $after, $f['from'] ) ) {
			printf( "%-16s 見つかりません → %s\n", $page->post_name, $f['label'] );
			echo "STATUS ABORT\n";
			exit( 1 );
		}
		$after  = str_replace( $f['from'], $f['to'], $after );
		$hits[] = $f['label'];
		$done++;
	}

	if ( $before === $after ) {
		continue;
	}

	$touched++;
	printf( "%s\n", $page->post_name );
	foreach ( $hits as $h ) {
		printf( "    %s\n", $h );
	}

	if ( $dry ) {
		continue;
	}

	if ( '' === get_post_meta( $page->ID, TB_R4_BACKUP, true ) ) {
		update_post_meta( $page->ID, TB_R4_BACKUP, wp_slash( $before ) );
	}
	// wp_update_post() は使わない。KSES が旧サイトの閉じ忘れタグを書き換えるし、
	// 渡した値を wp_unslash() するので金額の「\49,500」のバックスラッシュも消える。
	$wpdb->update( $wpdb->posts, array( 'post_content' => $after ), array( 'ID' => $page->ID ) );
	clean_post_cache( $page->ID );
}

printf( "\n対象 %d ページ / 直した %d 箇所 / 済で飛ばした %d 箇所\n", $touched, $done, $skipped );
echo $dry ? "STATUS DRY\n" : "STATUS DONE\n";
