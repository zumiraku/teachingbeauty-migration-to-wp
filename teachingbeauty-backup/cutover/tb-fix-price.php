<?php
/**
 * 施術料金の表記を 49,500円（税込）に揃える。/wp/ に置いて、使ったら消す。
 *   ?k=TOKEN&dry=1     … 変更予定を出すだけ
 *   ?k=TOKEN           … 適用
 *   ?k=TOKEN&restore=1 … 元に戻す
 *
 * ふたつのことをやる。
 *
 * 1) newpage24（突発性難聴）の値上げ
 *    30,000円（税別）→ 49,500円（税込）、回数券 300,000円 → 594,000円。
 *    594,000 ÷ 12 = 49,500 で回数券の割引が無くなるため、「66,000円お安く」
 *    「1回あたり25,000円」の2行は消す。残すと嘘になる。
 *    病院の治療費の記載も見本ページ（newpage23）に合わせる。
 *
 * 2) 45,000円（税別／税抜）→ 49,500円（税込）、540,000円 → 594,000円
 *    金額は変わらない。45,000 の税込がちょうど 49,500 なので、書き方だけ揃える。
 *    金額のすぐ後ろにある「税別」「税抜」も「税込」に直す。括弧は全角・半角そのまま。
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

const TB_PRICE_BACKUP = '_tb_price_backup';

// newpage24 だけの差し替え。見つからなければ止める。
$p24 = array(
	array(
		'label'  => '1回 30,000円（税別）→ 49,500円（税込）',
		'from'   => '<font size="5">\30,000</font><font size="2" color="#000000">(税別)</font>',
		'to'     => '<font size="5">\49,500</font><font size="2" color="#000000">(税込)</font>',
	),
	array(
		'label'  => '回数券12回 300,000円（税別）→ 594,000円（税込）',
		'from'   => '<FONT size="5">\300,000</FONT><FONT size="2">（税別）</FONT>',
		'to'     => '<FONT size="5">\594,000</FONT><FONT size="2">（税込）</FONT>',
	),
	array(
		'label'  => '割引の2行を削除（回数券に割引が無くなるため）',
		'from'   => '<FONT size="4">回数券の場合</FONT><FONT size="5"><B>66,000円お安く</B></FONT><FONT size="4">なります。</FONT><BR><FONT size="4">(回数券の場合1回あたり</FONT><FONT size="5"><B>\25,000</B></FONT><FONT size="4">となります。)<br></FONT>',
		'to'     => '',
	),
	array(
		'label'  => '人工内耳手術の金額を見本に合わせる',
		'from'   => '・人工内耳手術　　　　　　400万円',
		'to'     => '・人工内耳手術　　　　　　400～500万円',
	),
	array(
		'label'  => '高酸素療法の記載を見本に合わせる',
		'from'   => '・高酸素療法治療　　　　　30~40万（治癒率30％）',
		'to'     => '・高酸素療法　　　　　　　30~40万',
	),
);

// 金額と、その直後にある税の表記をまとめて直す。
const TB_PRICE_RE = '/(45,000|540,000)((?:(?!45,000|540,000)[\s\S]){0,200}?)([（(])(税別|税抜)([）)])/u';

function tb_price_new_amount( $n ) {
	return '45,000' === $n ? '49,500' : '594,000';
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

if ( $restore ) {
	$n = 0;
	foreach ( $pages as $page ) {
		$old = get_post_meta( $page->ID, TB_PRICE_BACKUP, true );
		if ( '' === $old ) {
			continue;
		}
		$wpdb->update( $wpdb->posts, array( 'post_content' => $old ), array( 'ID' => $page->ID ) );
		clean_post_cache( $page->ID );
		delete_post_meta( $page->ID, TB_PRICE_BACKUP );
		printf( "%-16s 戻しました\n", $page->post_name );
		$n++;
	}
	printf( "\n%d ページを元に戻しました\n", $n );
	echo "STATUS RESTORED\n";
	exit;
}

$touched = 0;
$total   = 0;
$left    = array();

foreach ( $pages as $page ) {
	$before = $page->post_content;
	$after  = $before;
	$hits   = array();

	if ( 'newpage24' === $page->post_name ) {
		foreach ( $p24 as $f ) {
			if ( false === strpos( $after, $f['from'] ) ) {
				printf( "newpage24: 見つかりません → %s\n", $f['label'] );
				echo "STATUS ABORT\n";
				exit( 1 );
			}
			$after = str_replace( $f['from'], $f['to'], $after );
			$hits[] = $f['label'];
		}
	}

	$after = preg_replace_callback(
		TB_PRICE_RE,
		function ( $m ) use ( &$hits ) {
			$new = tb_price_new_amount( $m[1] );
			$hits[] = sprintf( '%s（%s）→ %s（税込）', $m[1], $m[4], $new );
			return $new . $m[2] . $m[3] . '税込' . $m[5];
		},
		$after
	);

	// 税の表記が離れていて直せなかったものは報告する。黙って残さない。
	if ( preg_match_all( '/45,000|540,000/u', $after, $m2 ) ) {
		$left[ $page->post_name ] = count( $m2[0] );
	}

	if ( $before === $after ) {
		continue;
	}

	$touched++;
	$total += count( $hits );
	printf( "%-16s %d 箇所\n", $page->post_name, count( $hits ) );
	foreach ( $hits as $h ) {
		printf( "    %s\n", $h );
	}

	if ( $dry ) {
		continue;
	}

	if ( '' === get_post_meta( $page->ID, TB_PRICE_BACKUP, true ) ) {
		update_post_meta( $page->ID, TB_PRICE_BACKUP, wp_slash( $before ) );
	}
	// wp_update_post() は使わない。KSES が旧サイトの閉じ忘れタグを書き換えるし、
	// 渡した値を wp_unslash() するので金額の「\49,500」のバックスラッシュも消える。
	$wpdb->update( $wpdb->posts, array( 'post_content' => $after ), array( 'ID' => $page->ID ) );
	clean_post_cache( $page->ID );
}

if ( $left ) {
	echo "\n直せずに残った金額（税の表記が離れている等）:\n";
	foreach ( $left as $slug => $n ) {
		printf( "    %-16s %d 箇所\n", $slug, $n );
	}
}

printf( "\n対象 %d ページ / 合計 %d 箇所\n", $touched, $total );
echo $dry ? "STATUS DRY\n" : "STATUS DONE\n";
