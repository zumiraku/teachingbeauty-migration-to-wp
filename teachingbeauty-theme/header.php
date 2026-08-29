<?php
/**
 * ヘッダー（共通パーツ）
 * ロゴ・電話・住所・グローバルナビ。1箇所の変更で全ページに反映される。
 *
 * @package Teaching_Beauty
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="tb-skip" href="#tb-main"><?php esc_html_e( '本文へスキップ', 'teachingbeauty' ); ?></a>

<div class="tb-container">

	<header class="tb-header" role="banner">
		<p class="tb-site-h1"><?php echo esc_html( get_theme_mod( 'tb_catch', '前置胎盤・突発性難聴・不妊症・美容鍼・整体・交通事故でお困りの方は Teaching Beauty 鍼灸整骨院へ' ) ); ?></p>

		<?php if ( has_custom_logo() ) : ?>
			<div class="tb-logo"><?php the_custom_logo(); ?></div>
		<?php else : ?>
			<p class="tb-logo">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
					<span class="en">Teaching Beauty</span>
					<?php echo esc_html( get_bloginfo( 'name' ) ? get_bloginfo( 'name' ) : '鍼灸マッサージ整骨院・整体院' ); ?>
				</a>
			</p>
		<?php endif; ?>

		<div class="tb-header-contact">
			<p class="tel">
				<small><?php esc_html_e( '電話でのご予約・お問い合わせ', 'teachingbeauty' ); ?></small>
				<a href="tel:0364137803">03-6413-7803</a>
			</p>
			<p class="address">〒154-0014 東京都世田谷区新町3-21-1 さくらウェルガーデン301号室</p>
		</div>
	</header>

	<nav class="tb-nav" id="tb-nav" aria-label="<?php esc_attr_e( 'グローバルナビゲーション', 'teachingbeauty' ); ?>">
		<button class="tb-nav-toggle" aria-expanded="false" aria-controls="tb-nav">
			<span aria-hidden="true">☰</span> <?php esc_html_e( 'メニュー', 'teachingbeauty' ); ?>
		</button>
		<?php
		wp_nav_menu(
			array(
				'theme_location' => 'primary',
				'container'      => false,
				'menu_class'     => 'menu',
				'fallback_cb'    => 'tb_primary_menu_fallback',
				'depth'          => 2,
			)
		);
		?>
	</nav>

	<?php tb_breadcrumb(); ?>
