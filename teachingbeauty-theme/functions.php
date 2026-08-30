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

		// 装飾・アニメーション強化レイヤー（レイアウトは不変）。
		wp_enqueue_style( 'tb-enhance', get_template_directory_uri() . '/enhance.css', array( 'hpbuser' ), TB_VERSION );
		wp_enqueue_script( 'tb-enhance', get_template_directory_uri() . '/inc/enhance.js', array(), TB_VERSION, true );
	}
);

/**
 * <title> は元ページの <title> をそのまま出力する。
 * post_title（生値）を使うことで、WordPress の自動整形（ハイフン→ダッシュ等）や
 * サイト名サフィックス、フロントページのサイト名置換を回避し、原本と完全一致させる。
 */
add_filter(
	'pre_get_document_title',
	function () {
		$id = get_queried_object_id();
		if ( $id ) {
			$post = get_post( $id );
			if ( $post && '' !== (string) $post->post_title ) {
				return $post->post_title; // 生値（フィルタ未適用）
			}
		}
		return get_bloginfo( 'name' );
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
 * Contact Form 7 の JS/CSS・フォーム専用スタイルは、フォームを含むページのみ読み込む。
 * 他ページには不要な出力をせず、原本に近い軽量な出力にする。
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( is_singular() ) {
			$post     = get_post( get_queried_object_id() );
			$has_form = $post && false !== strpos( (string) $post->post_content, '[contact-form-7' );
			if ( $has_form ) {
				wp_enqueue_style( 'tb-form', get_template_directory_uri() . '/form.css', array( 'hpbuser' ), TB_VERSION );
			} else {
				wp_dequeue_script( 'contact-form-7' );
				wp_dequeue_script( 'swv' );
				wp_dequeue_style( 'contact-form-7' );
			}
		}
	},
	100
);

/**
 * CF7 の select 空白オプションの文言を日本語にする（include_blank）。
 */
add_filter(
	'gettext',
	function ( $translation, $text, $domain ) {
		if ( 'contact-form-7' === $domain && '&#8212;Please choose an option&#8212;' === $text ) {
			return '選択してください';
		}
		return $translation;
	},
	10,
	3
);

/**
 * 原文HTMLをそのまま出力（ショートコードのみ処理）。
 */
function tb_render_raw() {
	echo do_shortcode( get_the_content() );
}
