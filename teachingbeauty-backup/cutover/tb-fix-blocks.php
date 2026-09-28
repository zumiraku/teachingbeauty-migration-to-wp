<?php
/**
 * トップページの巨大なカタマリを、編集しやすい単位に切り分ける。
 * /wp/ に置いて、使ったら消す。
 *   ?k=TOKEN&dry=1     … 変更予定を出すだけ
 *   ?k=TOKEN           … 適用
 *   ?k=TOKEN&restore=1 … 元に戻す
 *
 * 旧サイトは段落の区切りがほとんど無く、【H30/10/13放送】から不妊症施術の先まで
 * 1つの <div>（本文1811文字）に入っている。編集画面でエンターを押しても中に <br> が
 * 入るだけでカタマリは割れないので、「見出し3」を押すとカタマリ全体が見出しになり、
 * 帯は先頭の【H30/10/13放送】側に出る。クライアントの「別のところだけ見出しが入る」はこれ。
 *
 * そこで <br> を1つ食べる形で </div><div> に置き換え、カタマリを3つに割る。
 * 切れ目は <font> が開きっぱなしでない場所だけを選んである（入れ子を壊さないため）。
 * 見た目は変わらない（#hpb-main の画像を前後で比較して完全一致・高さ4340pxで同じ）。
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

const TB_BLOCKS_BACKUP = '_tb_blocks_backup';

// slug => 切れ目の一覧（label, from, to）。from はページ内でちょうど1回だけ出てくること。
$targets = array(
	'home' => array(
		array(
			'label' => '【全国初！！NHKでのレギュラー番組】の前で切る',
			'from'  => '</section><b><font size="5" color="#0000ff">【全国初',
			'to'    => '</section></div><div><b><font size="5" color="#0000ff">【全国初',
		),
		array(
			'label' => '「不妊症施術」の前で切る',
			'from'  => '</font></b><br><br><font style="font-size: 150%;" size="+0">',
			'to'    => '</font></b><br><br></div><div><font style="font-size: 150%;" size="+0">',
		),
		array(
			'label' => '「不妊症施術」の後ろで切る',
			'from'  => '<b>不妊症施術<br></b></font></font><br><font size="7" color="#480a17">',
			'to'    => '<b>不妊症施術<br></b></font></font><br></div><div><font size="7" color="#480a17">',
		),
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
		$old = get_post_meta( $page->ID, TB_BLOCKS_BACKUP, true );
		if ( '' === $old ) {
			continue;
		}
		$wpdb->update( $wpdb->posts, array( 'post_content' => $old ), array( 'ID' => $page->ID ) );
		clean_post_cache( $page->ID );
		delete_post_meta( $page->ID, TB_BLOCKS_BACKUP );
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

$touched = 0;
$total   = 0;
$abort   = false;

foreach ( $targets as $slug => $cuts ) {
	if ( ! isset( $by_slug[ $slug ] ) ) {
		printf( "%-16s ページが見つからない\n", $slug );
		$abort = true;
		continue;
	}
	$page   = $by_slug[ $slug ];
	$before = $page->post_content;
	$after  = $before;
	$done   = 0;

	foreach ( $cuts as $cut ) {
		$hits = substr_count( $after, $cut['from'] );
		if ( 0 === $hits ) {
			// すでに切ってあるなら黙って飛ばす（何度流しても同じ結果になるように）。
			if ( false !== strpos( $after, $cut['to'] ) ) {
				printf( "%-16s 済 %s\n", $slug, $cut['label'] );
				continue;
			}
			printf( "%-16s 見つからない: %s\n", $slug, $cut['label'] );
			$abort = true;
			continue;
		}
		if ( $hits > 1 ) {
			printf( "%-16s %d 箇所あって絞れない: %s\n", $slug, $hits, $cut['label'] );
			$abort = true;
			continue;
		}
		$after = str_replace( $cut['from'], $cut['to'], $after );
		printf( "%-16s OK %s\n", $slug, $cut['label'] );
		$done++;
	}

	if ( $abort ) {
		continue;
	}

	// 割ったぶん <div> と </div> の数が同じだけ増えているか念のため数える。
	$d1 = preg_match_all( '/<div\b/i', $before ) - preg_match_all( '/<\/div>/i', $before );
	$d2 = preg_match_all( '/<div\b/i', $after ) - preg_match_all( '/<\/div>/i', $after );
	if ( $d1 !== $d2 ) {
		printf( "%-16s div の開閉が合わない（前 %d / 後 %d）\n", $slug, $d1, $d2 );
		$abort = true;
		continue;
	}

	if ( ! $done || $before === $after ) {
		continue;
	}

	$touched++;
	$total += $done;

	if ( $dry ) {
		continue;
	}

	if ( '' === get_post_meta( $page->ID, TB_BLOCKS_BACKUP, true ) ) {
		update_post_meta( $page->ID, TB_BLOCKS_BACKUP, wp_slash( $before ) );
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

printf( "\n対象 %d ページ / 切れ目 %d 箇所\n", $touched, $total );
echo $dry ? "STATUS DRY\n" : "STATUS DONE\n";
