<?php
/**
 * Teaching Beauty
 *
 * 各ページは旧サイトの <body> をそのまま出力し、レイアウトも旧サイトの4つの CSS
 * （hpbparts / container_5H_2c_top / main_5H_2c / user）をそのまま読ませている。
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
 * 旧サイトの CSS を元の順序で読む。CSS も画像もサイトルートの元の場所に置いたまま。
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
 * <title> は post_title を生のまま出す。フィルタを通すとハイフンがダッシュに変わったり
 * サイト名が付いたりして、旧サイトとずれる。
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
 * 旧サイトに無かった WordPress の付加出力を止める。
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

		// WordPress 既定のフロント CSS は旧サイトに無いうえ、旧 HTML の表示を変えてしまう。
		// hari.html の壊れた style 属性に :where([style^="border-bottom-width"]) が反応して
		// 下線が出たのが実例。旧サイトの4枚だけで描画させる。
		remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
		remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
		remove_action( 'wp_enqueue_scripts', 'wp_enqueue_classic_theme_styles' );
		remove_action( 'wp_enqueue_scripts', 'wp_enqueue_stored_styles' );
		remove_action( 'wp_footer', 'wp_enqueue_stored_styles', 1 );
	}
);
add_action(
	'wp_enqueue_scripts',
	function () {
		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'wp-block-library-theme' );
		wp_dequeue_style( 'classic-theme-styles' );
		wp_dequeue_style( 'global-styles' );
		wp_dequeue_style( 'wp-img-auto-sizes-contain' );
	},
	100
);
add_filter( 'wp_speculation_rules_configuration', '__return_null' );
// <meta name="robots" content="max-image-preview:large"> は元サイトに無いので出さない（インデックスには無関係）
remove_filter( 'wp_robots', 'wp_robots_max_image_preview_large' );

/**
 * 旧サイトにあったのは固定ページ39枚だけ。
 * WordPress が自動で用意する投稿・カテゴリー・投稿者・検索結果・日付一覧は元サイトに無いので、
 * サイトマップに載せないし、検索結果にも出さない。
 * 放っておくと Google がそれらを拾って「中身が薄い」と判断し、
 * サーチコンソールの未登録理由が増える。
 */
add_filter(
	'wp_sitemaps_post_types',
	function ( $types ) {
		return array_intersect_key( $types, array( 'page' => true ) );
	}
);
add_filter( 'wp_sitemaps_taxonomies', '__return_empty_array' );
add_filter(
	'wp_sitemaps_add_provider',
	function ( $provider, $name ) {
		return 'users' === $name ? false : $provider;
	},
	10,
	2
);
add_filter(
	'wp_robots',
	function ( $robots ) {
		if ( ! is_singular( 'page' ) ) {
			$robots['noindex'] = true;
		}
		return $robots;
	}
);

/**
 * フィードも元サイトに無い。開かれたらトップへ返す。
 */
add_action(
	'template_redirect',
	function () {
		if ( is_feed() ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	}
);

/**
 * Contact Form 7 の JS/CSS はフォームのあるページだけで読む。
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

/**
 * description / keywords の編集欄。_tb_ 始まりのメタはアンダースコア付きなので
 * 標準の「カスタムフィールド」には出てこない。専用の欄を置く。
 */
add_action(
	'add_meta_boxes',
	function () {
		foreach ( array( 'page', 'post' ) as $type ) {
			add_meta_box( 'tb-seo', 'このページの説明文とキーワード（検索エンジン向け）', 'tb_seo_meta_box', $type, 'normal', 'high' );
		}
	}
);
function tb_seo_meta_box( $post ) {
	wp_nonce_field( 'tb_seo_save', 'tb_seo_nonce' );
	$d = (string) get_post_meta( $post->ID, '_tb_description', true );
	$k = (string) get_post_meta( $post->ID, '_tb_keywords', true );
	?>
	<p><label for="tb_description"><b>説明文（description）</b>　検索結果でページ名の下に出る紹介文。全角 80〜120 字くらいが目安です。</label><br>
	<textarea id="tb_description" name="tb_description" rows="3" style="width:100%"><?php echo esc_textarea( $d ); ?></textarea></p>
	<p><label for="tb_keywords"><b>キーワード（keywords）</b>　「,」区切り。今の Google は順位に使いませんが、元サイトの設定をそのまま引き継いでいます。</label><br>
	<input type="text" id="tb_keywords" name="tb_keywords" value="<?php echo esc_attr( $k ); ?>" style="width:100%"></p>
	<p class="description">ブラウザのタブや検索結果の見出しになる「ページのタイトル」は、この画面のいちばん上のタイトル欄がそのまま使われます（サイト名は付きません）。</p>
	<?php
}
add_action(
	'save_post',
	function ( $post_id ) {
		if ( ! isset( $_POST['tb_seo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tb_seo_nonce'] ) ), 'tb_seo_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( isset( $_POST['tb_description'] ) ) {
			update_post_meta( $post_id, '_tb_description', sanitize_textarea_field( wp_unslash( $_POST['tb_description'] ) ) );
		}
		if ( isset( $_POST['tb_keywords'] ) ) {
			update_post_meta( $post_id, '_tb_keywords', sanitize_text_field( wp_unslash( $_POST['tb_keywords'] ) ) );
		}
	}
);
