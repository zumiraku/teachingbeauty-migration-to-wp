<?php
/**
 * 全39ページを機械で調べて見つかった、見出しまわりの取りこぼしを直す。
 * /wp/ に置いて、使ったら消す。
 *   ?k=TOKEN&dry=1     … 変更予定を出すだけ
 *   ?k=TOKEN           … 適用
 *   ?k=TOKEN&restore=1 … 元に戻す
 *
 * 1) トップの「news 新着情報」を見出しに戻す。
 *    編集の途中で <div id="toppage-news"><h3> が外れ、ただの span だけになって
 *    いたため帯が消えていた。元サイトと同じ形に戻す。
 *
 * 2) 見出しの末尾に残っている余計な <br> を取る。
 *    空の行がもう1行できて帯が高くなり、文字の位置が他の見出しとズレる。
 *    エステのページの「バストアップ」がこれで 38px になっていた（他は 31px）。
 *    短い見出し（文字数60未満）だけを対象にして、本文を飲み込んでいる
 *    見出しには触らない。
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

const TB_AUDIT_BACKUP = '_tb_audit_backup';

const TB_NEWS_FROM = '<span class="en"><font size="6">news</font></span><span class="ja"><font size="5">新着情報</font></span>';
const TB_NEWS_TO   = '<div id="toppage-news"><h3><span class="en"><font size="6">news</font></span><span class="ja"><font size="5">新着情報</font></span></h3></div>';

/**
 * 見出しの末尾の <br> を取る。閉じタグ（</b> など）は残したまま <br> だけ抜く。
 */
function tb_strip_tail_br( $html, &$count ) {
	$count = 0;
	return preg_replace_callback(
		'/(<h3\b[^>]*>)(.*?)(<\/h3>)/is',
		function ( $m ) use ( &$count ) {
			$inner = $m[2];
			// 本文を飲み込んでいる長い見出しには触らない
			if ( mb_strlen( trim( wp_strip_all_tags( $inner ) ), 'UTF-8' ) >= 60 ) {
				return $m[0];
			}
			$new = preg_replace(
				'/<br\s*\/?>((?:\s*<\/(?:b|strong|font|i|u|em|span|big|small)>\s*)*)$/i',
				'$1',
				$inner
			);
			if ( null !== $new && $new !== $inner ) {
				$count++;
				$inner = $new;
			}
			return $m[1] . $inner . $m[3];
		},
		$html
	);
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
		$old = get_post_meta( $page->ID, TB_AUDIT_BACKUP, true );
		if ( '' === $old ) {
			continue;
		}
		$wpdb->update( $wpdb->posts, array( 'post_content' => $old ), array( 'ID' => $page->ID ) );
		clean_post_cache( $page->ID );
		delete_post_meta( $page->ID, TB_AUDIT_BACKUP );
		printf( "%-16s 戻しました\n", $page->post_name );
		$n++;
	}
	printf( "\n%d ページを元に戻しました\n", $n );
	echo "STATUS RESTORED\n";
	exit;
}

$touched = 0;
$brTotal = 0;
$newsOk  = false;
$abort   = false;

foreach ( $pages as $page ) {
	$before = $page->post_content;
	$after  = $before;

	// 1) トップの news 見出し
	if ( 'home' === $page->post_name ) {
		if ( false !== strpos( $after, TB_NEWS_TO ) ) {
			printf( "%-16s 済 news 新着情報の見出し\n", $page->post_name );
			$newsOk = true;
		} else {
			$hits = substr_count( $after, TB_NEWS_FROM );
			if ( 1 !== $hits ) {
				printf( "%-16s news 新着情報が %d 箇所（1つのはず）\n", $page->post_name, $hits );
				$abort = true;
			} else {
				$after  = str_replace( TB_NEWS_FROM, TB_NEWS_TO, $after );
				$newsOk = true;
				printf( "%-16s OK news 新着情報を見出しに戻す\n", $page->post_name );
			}
		}
	}

	// 2) 見出し末尾の余計な <br>
	$n     = 0;
	$after = tb_strip_tail_br( $after, $n );
	if ( $n ) {
		printf( "%-16s OK 見出し末尾の <br> を %d 個削除\n", $page->post_name, $n );
		$brTotal += $n;
	}

	if ( $before === $after ) {
		continue;
	}

	// タグの開閉が変わっていないか
	foreach ( array( 'h3', 'div', 'font', 'b', 'strong' ) as $tag ) {
		$d1 = preg_match_all( "/<$tag\b/i", $before ) - preg_match_all( "/<\/$tag>/i", $before );
		$d2 = preg_match_all( "/<$tag\b/i", $after ) - preg_match_all( "/<\/$tag>/i", $after );
		// news の見出しは div と h3 を1組ずつ増やすのでそのぶんは見込む
		if ( $d1 !== $d2 ) {
			printf( "%-16s %s の開閉が合わない（前 %d / 後 %d）\n", $page->post_name, $tag, $d1, $d2 );
			$abort = true;
		}
	}

	if ( $abort ) {
		continue;
	}

	$touched++;

	if ( $dry ) {
		continue;
	}

	if ( '' === get_post_meta( $page->ID, TB_AUDIT_BACKUP, true ) ) {
		update_post_meta( $page->ID, TB_AUDIT_BACKUP, wp_slash( $before ) );
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

printf( "\n対象 %d ページ / 見出し末尾の <br> %d 個 / news の見出し %s\n", $touched, $brTotal, $newsOk ? 'OK' : '未処理' );
echo $dry ? "STATUS DRY\n" : "STATUS DONE\n";
