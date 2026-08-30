<?php
/**
 * Teaching Beauty theme — 原本HTML完全再現版
 *
 * 各ページは元サイトの <body> 内HTMLをそのまま出力し、
 * レイアウトは元の4つのCSS（hpbparts / container_5H_2c_top / main_5H_2c / user）
 * をそのまま読み込むことで、見た目を完全に一致させる。
 *
 * @package Teaching_Beauty
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
define( 'TB_VERSION', '2.0.0' );

add_action(
	'after_setup_theme',
	function () {
		add_theme_support( 'title-tag' );
		add_theme_support( 'automatic-feed-links' );
	}
);

/**
 * 元サイトの4つのCSSをそのままの順序で読み込む（レイアウト完全再現）。
 * CSS・画像はサイトルート（/wp/ 内）の元の場所に配置されている。
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		$root = home_url( '/' );
		wp_enqueue_style( 'hpbparts', $root . 'hpbparts.css', array(), null );
		wp_enqueue_style( 'hpbcontainer', $root . 'container_5H_2c_top.css', array( 'hpbparts' ), null );
		wp_enqueue_style( 'hpbmain', $root . 'main_5H_2c.css', array( 'hpbcontainer' ), null );
		wp_enqueue_style( 'hpbuser', $root . 'user.css', array( 'hpbmain' ), null );
	}
);

/**
 * <title> は元の値をそのまま（サイト名サフィックスを付けない）。
 */
add_filter(
	'document_title_parts',
	function ( $parts ) {
		return array( 'title' => isset( $parts['title'] ) ? $parts['title'] : get_the_title() );
	}
);

/**
 * 元の meta description / keywords を出力。
 */
add_action(
	'wp_head',
	function () {
		if ( is_singular() ) {
			$d = get_post_meta( get_the_ID(), '_tb_description', true );
			$k = get_post_meta( get_the_ID(), '_tb_keywords', true );
			if ( '' !== (string) $d ) {
				echo '<meta name="description" content="' . esc_attr( $d ) . '">' . "\n";
			}
			if ( '' !== (string) $k ) {
				echo '<meta name="keywords" content="' . esc_attr( $k ) . '">' . "\n";
			}
		}
	},
	1
);

/**
 * 本文の自動整形を無効化（原文HTMLをそのまま保つ）。
 * 出力はテンプレート側で do_shortcode( get_the_content() ) を用いる。
 */
remove_filter( 'the_content', 'wpautop' );
remove_filter( 'the_content', 'wptexturize' );
remove_filter( 'the_content', 'shortcode_unautop' );

/**
 * 元サイトに無い WordPress の付加出力を抑制し、出力を原本に近づける。
 */
add_action(
	'init',
	function () {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'wp_head', 'wp_generator' );
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head' );
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'rest_output_link_wp_head' );
		remove_action( 'wp_head', 'feed_links_extra', 3 );
		remove_action( 'wp_head', 'feed_links', 2 );
		remove_action( 'wp_head', 'wp_resource_hints', 2 );
	}
);
add_filter( 'wp_speculation_rules_configuration', '__return_null' );

/**
 * 原文HTMLをそのまま出力（ショートコードのみ処理）。
 */
function tb_render_raw() {
	echo do_shortcode( get_the_content() );
}
