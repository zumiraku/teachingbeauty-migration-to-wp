<?php
/**
 * Teaching Beauty theme functions
 *
 * 現行デザインを再現する専用テーマの中核。
 * テーマサポート・メニュー・ウィジェット・アセット読み込みを定義する。
 *
 * @package Teaching_Beauty
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'TB_VERSION' ) ) {
	define( 'TB_VERSION', '1.0.0' );
}

/**
 * テーマの基本サポート
 */
function tb_setup() {
	load_theme_textdomain( 'teachingbeauty', get_template_directory() . '/languages' );

	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 60,
			'width'       => 320,
			'flex-width'  => true,
			'flex-height' => true,
		)
	);

	register_nav_menus(
		array(
			'primary' => __( 'グローバルナビ', 'teachingbeauty' ),
			'footer'  => __( 'フッターナビ', 'teachingbeauty' ),
		)
	);

	// お知らせ・患者様の声を院内で更新できるよう、コンテンツ用の画像サイズ。
	add_image_size( 'tb-news-thumb', 120, 120, true );
}
add_action( 'after_setup_theme', 'tb_setup' );

/**
 * ウィジェットエリア（サイドバー）
 */
function tb_widgets_init() {
	register_sidebar(
		array(
			'name'          => __( 'サイドバー（バナー・店舗情報）', 'teachingbeauty' ),
			'id'            => 'sidebar-main',
			'description'   => __( '各ページ右側に表示されます。', 'teachingbeauty' ),
			'before_widget' => '<section id="%1$s" class="tb-widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h3 class="tb-widget-title">',
			'after_title'   => '</h3>',
		)
	);
}
add_action( 'widgets_init', 'tb_widgets_init' );

/**
 * スタイル・スクリプトの読み込み
 */
function tb_assets() {
	wp_enqueue_style( 'teachingbeauty', get_stylesheet_uri(), array(), TB_VERSION );

	// Google Fonts（明朝＋ゴシック）。現行のヒラギノ系を優先し、無い環境の代替として。
	wp_enqueue_style(
		'tb-fonts',
		'https://fonts.googleapis.com/css2?family=Shippori+Mincho+B1:wght@500;700&family=Zen+Kaku+Gothic+New:wght@400;500;700&display=swap',
		array(),
		null
	);

	wp_enqueue_script( 'tb-nav', get_template_directory_uri() . '/inc/nav.js', array(), TB_VERSION, true );
}
add_action( 'wp_enqueue_scripts', 'tb_assets' );

/**
 * パンくずリスト（構造化データは Stage 4 で JSON-LD 実装）
 */
function tb_breadcrumb() {
	if ( is_front_page() ) {
		return;
	}
	echo '<nav class="tb-breadcrumb" aria-label="' . esc_attr__( 'パンくずリスト', 'teachingbeauty' ) . '"><ol>';
	echo '<li><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'ホーム', 'teachingbeauty' ) . '</a></li>';

	if ( is_page() ) {
		$ancestors = array_reverse( get_post_ancestors( get_the_ID() ) );
		foreach ( $ancestors as $ancestor ) {
			echo '<li><a href="' . esc_url( get_permalink( $ancestor ) ) . '">' . esc_html( get_the_title( $ancestor ) ) . '</a></li>';
		}
		echo '<li aria-current="page">' . esc_html( get_the_title() ) . '</li>';
	} elseif ( is_singular( 'post' ) ) {
		echo '<li aria-current="page">' . esc_html( get_the_title() ) . '</li>';
	} elseif ( is_archive() ) {
		echo '<li aria-current="page">' . esc_html( wp_strip_all_tags( get_the_archive_title() ) ) . '</li>';
	} elseif ( is_search() ) {
		echo '<li aria-current="page">' . esc_html__( '検索結果', 'teachingbeauty' ) . '</li>';
	}
	echo '</ol></nav>';
}

/**
 * グローバルナビが未設定でも壊れないフォールバック
 */
function tb_primary_menu_fallback() {
	echo '<ul class="menu">';
	echo '<li class="current-menu-item"><a href="' . esc_url( home_url( '/' ) ) . '"><span class="en">top</span>ホーム</a></li>';
	echo '<li><a href="' . esc_url( home_url( '/about/' ) ) . '"><span class="en">about</span>当院について</a></li>';
	echo '<li><a href="' . esc_url( home_url( '/menu/' ) ) . '"><span class="en">menu</span>施術メニュー</a></li>';
	echo '<li><a href="' . esc_url( home_url( '/news/' ) ) . '"><span class="en">news</span>お知らせ</a></li>';
	echo '<li><a href="' . esc_url( home_url( '/muryou/' ) ) . '"><span class="en">free</span>無料相談</a></li>';
	echo '<li><a href="' . esc_url( home_url( '/reserve/' ) ) . '"><span class="en">reserve</span>ご予約</a></li>';
	echo '</ul>';
}

/**
 * 抜粋の省略記号を日本語向けに
 */
function tb_excerpt_more() {
	return '…';
}
add_filter( 'excerpt_more', 'tb_excerpt_more' );
