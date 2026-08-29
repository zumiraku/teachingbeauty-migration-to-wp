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
