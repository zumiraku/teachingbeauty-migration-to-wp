<?php
/**
 * 元サイト由来の <img src="相対パス"> を WordPress メディアライブラリに登録し、
 * 本文の <img> に class="wp-image-ID" を付けて src をアップロード先URLへ書き換える。
 *
 * 目的: エディターの「画像詳細」に「置換」ボタンを出す（メディア登録済みの画像にしか出ない）。
 *       → クライアントが「写真をクリック → えんぴつ → 置換」で写真を差し替えられる。
 *
 * 変更するのは <img> タグの class と src だけ。width / height / alt / border 等はそのまま。
 * 元の画像ファイルはサーバー上に残す（他からの参照や外部リンクを壊さない）。
 *
 * 使い方:
 *   1. WordPress ルート（/wp/）直下にこのファイルを置く
 *   2. ブラウザで  .../tb-register-images.php?k=k7Qm2xR9vTd4&dry=1     → 変更予定の一覧だけ表示（何も変えない）
 *   3. 問題なければ  .../tb-register-images.php?k=k7Qm2xR9vTd4&n=25     → 25件ずつ登録。
 *      「続きがあります」と出たら同じURLをもう一度開く。「完了」が出るまで繰り返す。
 *      （共用サーバーの実行時間制限に当たらないよう小分けにする。n を省略すると一気に処理）
 *   4. このファイルを削除
 *
 * 何度実行しても安全:
 *   - 登録済み（wp-image- クラス付き）の <img> はスキップ
 *   - 同じ元ファイルは、添付の _tb_source_file メタで見つけて再利用（重複登録しない）
 */

if ( PHP_SAPI !== 'cli' ) {
	header( 'Content-Type: text/plain; charset=UTF-8' );
	if ( ! isset( $_GET['k'] ) || 'k7Qm2xR9vTd4' !== $_GET['k'] ) {
		http_response_code( 404 );
		exit( 'not found' );
	}
	$dry   = ! empty( $_GET['dry'] );
	$limit = isset( $_GET['n'] ) ? (int) $_GET['n'] : 0; // 1回の実行で新規登録する上限（0=無制限）
} else {
	$_SERVER['HTTP_HOST']   = getenv( 'TB_HOST' ) ?: '127.0.0.1:8765';
	$_SERVER['REQUEST_URI'] = '/';
	$dry   = in_array( '--dry', $argv, true );
	$limit = 0;
	foreach ( $argv as $a ) {
		if ( preg_match( '/^--n=(\d+)$/', $a, $lm ) ) {
			$limit = (int) $lm[1];
		}
	}
}

define( 'WP_USE_THEMES', false );
require_once __DIR__ . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
set_time_limit( 0 );

// 大きな画像でも「-scaled」版を作らず、元ファイルそのものを本体にする（表示のピクセルを変えない）
add_filter( 'big_image_size_threshold', '__return_false' );

global $wpdb;
echo $dry ? "== ドライラン（変更しません） ==\n\n" : "== 実行" . ( $limit ? "（今回は最大 {$limit} 件）" : '' ) . " ==\n\n";

/**
 * 同じ元ファイルを既に登録済みなら、その添付IDを返す（過去の実行分も含む）。
 */
function tb_find_registered( $rel ) {
	global $wpdb;
	$id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_tb_source_file' AND meta_value = %s LIMIT 1", $rel ) );
	return $id ? (int) $id : 0;
}

$pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) );
$registered = array(); // 相対パス => 添付ID（この実行内のキャッシュ）
$total_tags = 0; $total_new = 0; $total_reuse = 0; $total_skip = 0; $total_missing = 0; $more = false;

foreach ( $pages as $page ) {
	$content = $page->post_content;
	$changed = 0;
	$new = preg_replace_callback(
		'/<img\b([^>]*)>/i',
		function ( $m ) use ( &$registered, &$changed, &$total_tags, &$total_new, &$total_reuse, &$total_skip, &$total_missing, &$more, $page, $dry, $limit ) {
			$attrs = $m[1];
			$total_tags++;
			if ( preg_match( '/\bclass="[^"]*\bwp-image-\d+/', $attrs ) ) { $total_skip++; return $m[0]; } // 登録済み
			if ( ! preg_match( '/\bsrc="([^"]+)"/i', $attrs, $sm ) ) { $total_skip++; return $m[0]; }
			$src = $sm[1];
			if ( preg_match( '#^(https?:)?//|^/|^data:#i', $src ) ) { $total_skip++; return $m[0]; } // 相対パスのみ対象
			$rel  = ltrim( rawurldecode( $src ), './' );
			$path = ABSPATH . $rel;
			if ( ! file_exists( $path ) ) {
				echo "  ! 見つからない: {$src}\n";
				$total_missing++;
				return $m[0];
			}
			$alt = preg_match( '/\balt="([^"]*)"/i', $attrs, $am ) ? html_entity_decode( $am[1], ENT_QUOTES, 'UTF-8' ) : '';

			// 1) この実行内で登録済み → 2) 過去の実行で登録済み → 3) 新規登録
			if ( isset( $registered[ $rel ] ) ) {
				$id = $registered[ $rel ];
			} elseif ( ( $id = tb_find_registered( $rel ) ) ) {
				$registered[ $rel ] = $id;
				$total_reuse++;
			} elseif ( $limit > 0 && $total_new >= $limit ) {
				$more = true; // 今回の上限に達した。次回の実行で続きから登録される
				return $m[0];
			} elseif ( $dry ) {
				$id = 0;
				$registered[ $rel ] = 0;
				$total_new++;
			} else {
				$tmp = wp_tempnam( basename( $rel ) );
				copy( $path, $tmp );
				$id = media_handle_sideload( array( 'name' => basename( $rel ), 'tmp_name' => $tmp ), $page->ID, basename( $rel ) );
				if ( is_wp_error( $id ) ) {
					@unlink( $tmp );
					echo "  ! 登録失敗: {$src}: " . $id->get_error_message() . "\n";
					return $m[0];
				}
				update_post_meta( $id, '_tb_source_file', $rel );
				if ( '' !== $alt ) { update_post_meta( $id, '_wp_attachment_image_alt', $alt ); }
				$registered[ $rel ] = $id;
				$total_new++;
			}
			$changed++;
			if ( $dry ) {
				echo "  + {$src}" . ( $id ? '（登録済みを再利用）' : '' ) . "\n";
				return $m[0];
			}
			$url = wp_get_attachment_url( $id );
			// src を差し替え、class に wp-image-ID を追加（既存 class があれば末尾に足す）
			$attrs2 = preg_replace( '/\bsrc="[^"]*"/i', 'src="' . esc_url( $url ) . '"', $attrs, 1 );
			if ( preg_match( '/\bclass="([^"]*)"/i', $attrs2, $cm ) ) {
				$attrs2 = preg_replace( '/\bclass="[^"]*"/i', 'class="' . trim( $cm[1] . ' wp-image-' . $id ) . '"', $attrs2, 1 );
			} else {
				$attrs2 = ' class="wp-image-' . $id . '"' . $attrs2;
			}
			echo "  + {$src} → 添付 {$id}\n";
			return '<img' . $attrs2 . '>';
		},
		$content
	);

	if ( $changed ) {
		echo "[{$page->post_name}] {$changed} 件\n";
		if ( ! $dry && $new !== $content ) {
			$wpdb->update( $wpdb->posts, array( 'post_content' => $new ), array( 'ID' => $page->ID ) );
			clean_post_cache( $page->ID );
		}
	}
}

echo "\n合計: imgタグ {$total_tags} / 新規登録 {$total_new} / 再利用 {$total_reuse} / 対象外 {$total_skip} / ファイル無し {$total_missing}\n";
if ( $dry ) {
	echo "（ドライラン：何も変更していません）\n";
} elseif ( $more ) {
	echo "続きがあります（同じURLをもう一度開いてください）\n";
} else {
	echo "完了\n";
}
// 自動実行用の目印（文字コードに依存しない英数字）
echo '[STATUS ' . ( $dry ? 'DRY' : ( $more ? 'MORE' : 'DONE' ) ) . " tags={$total_tags} new={$total_new} reuse={$total_reuse} skip={$total_skip} missing={$total_missing}]\n";
