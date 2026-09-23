<?php
/**
 * Plugin Name: Teaching Beauty – .html パーマリンク維持
 * Description: /concept.html のような旧サイトの URL をそのまま使い続けるための mu-plugin。
 * Version: 1.0.0
 *
 * wp-content/mu-plugins/ に置いたあと、「設定 → パーマリンク」を一度保存してリライトを反映する。
 *
 * @package Teaching_Beauty
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress はスラッグを小文字にしてしまうので、旧サイトの表記に戻すものだけここで持つ。
 * 今は Meniere.html だけ。URL 解決は大小どちらでも通るので canonical とリンク生成用。
 */
function tb_original_html_name( $slug ) {
	static $map = array( 'meniere' => 'Meniere' );
	return isset( $map[ $slug ] ) ? $map[ $slug ] : $slug;
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
 * 自前の .html パーマリンクだと redirect_canonical が誤作動して空応答を返す。
 * 固定ページと投稿では切り、重複対策は下の canonical タグに任せる。
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
 * /index.html はトップへ 301。被リンクは生かしたまま / との重複を潰す。
 */
add_action(
	'template_redirect',
	function () {
		$req = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path = rawurldecode( (string) wp_parse_url( $req, PHP_URL_PATH ) );
		// 本番は空、検証環境は /wp。home を基準に見る。
		$home = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		if ( $path === $home . '/index.html' ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
		// 旧サイトの不正URL（空白入り）funin aen.html を funin.html へ 301。
		if ( $path === $home . '/funin aen.html' ) {
			wp_safe_redirect( home_url( '/funin.html' ), 301 );
			exit;
		}
		// 旧サイトの誤字リンク（menu.html 内の 3okushi.html）を kokushi.html へ 301。
		if ( $path === $home . '/3okushi.html' ) {
			wp_safe_redirect( home_url( '/kokushi.html' ), 301 );
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
		return home_url( '/' . tb_original_html_name( $post->post_name ) . '.html' );
	},
	10,
	2
);

/**
 * 旧ドメインからの流入や末尾スラッシュの違いで重複扱いされないよう canonical を明示する。
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
					$url = home_url( '/' . tb_original_html_name( $post->post_name ) . '.html' );
				}
				echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";
			}
		}
	},
	1
);
