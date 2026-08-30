<?php
/**
 * Plugin Name: Teaching Beauty – .html パーマリンク維持
 * Description: 現行サイトの .html URL（例 /concept.html）をそのまま維持するための must-use プラグイン。1:1 移行で URL を一切変えないために使用する。
 * Version: 1.0.0
 *
 * 設置方法: wp-content/mu-plugins/ に置く（mu-plugins が無ければフォルダを作成）。
 * 設置後、「設定 → パーマリンク」を一度開いて保存し、リライトを反映する。
 *
 * @package Teaching_Beauty
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * /{slug}.html を固定ページに解決するリライトルールを追加。
 * 現行は全ページがルート直下の .html なので、フラットな pagename で解決できる。
 */
add_action(
	'init',
	function () {
		add_rewrite_rule( '^([^/]+)\.html$', 'index.php?pagename=$matches[1]', 'top' );
	}
);

/**
 * 自作の .html パーマリンクに対して WordPress の正規化リダイレクト
 * （redirect_canonical）が誤作動し、空応答を返すのを防ぐ。
 * 固定ページ・投稿では canonical リダイレクトを無効化し、
 * 重複対策は wp_head の canonical タグに任せる。
 */
add_filter(
	'redirect_canonical',
	function ( $redirect_url ) {
		if ( is_singular() || is_page() || is_front_page() ) {
			return false;
		}
		return $redirect_url;
	},
	10,
	1
);

/**
 * 現行サイトの /index.html はトップ（/）へ 301。
 * 旧サイトの index.html への内部リンク・被リンクを維持しつつ、
 * / と /index.html の重複（インデックス重複の主因）を正規化する。
 */
add_action(
	'template_redirect',
	function () {
		$req = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path = rawurldecode( (string) wp_parse_url( $req, PHP_URL_PATH ) );
		if ( '/index.html' === $path ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
		// 旧サイトの不正URL（空白入り）funin aen.html を funin.html へ 301。
		if ( '/funin aen.html' === $path ) {
			wp_safe_redirect( home_url( '/funin.html' ), 301 );
			exit;
		}
	}
);

/**
 * 固定ページのパーマリンクを .html 付きで出力し、内部リンクを現行と一致させる。
 * フロントページ（home）は「/」のまま。
 */
add_filter(
	'page_link',
	function ( $link, $post_id ) {
		if ( (int) get_option( 'page_on_front' ) === (int) $post_id ) {
			return home_url( '/' );
		}
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return $link; // 下書き等は既定の ?page_id= を維持
		}
		// 子ページを作らない前提でフラットに slug.html を返す
		return home_url( '/' . $post->post_name . '.html' );
	},
	10,
	2
);

/**
 * 旧ドメイン main.jp からの流入や、末尾スラッシュ差異による重複を避けるため
 * canonical を明示（インデックス重複対策・URL は変えない）。
 */
add_action(
	'wp_head',
	function () {
		if ( is_page() ) {
			$post = get_queried_object();
			if ( $post && ! empty( $post->post_name ) ) {
				if ( (int) get_option( 'page_on_front' ) === (int) $post->ID ) {
					$url = home_url( '/' );
				} else {
					$url = home_url( '/' . $post->post_name . '.html' );
				}
				echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";
			}
		}
	},
	1
);
