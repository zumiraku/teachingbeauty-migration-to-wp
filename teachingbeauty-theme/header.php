<?php
/**
 * ヘッダー — 元サイトと同じ doctype / head / body タグを出力。
 * ナビ・サイドバー等の可視要素は各ページの本文（原本HTML）側に含まれる。
 *
 * @package Teaching_Beauty
 */

$tb_bodyattr = '';
if ( is_singular() ) {
	$tb_bodyattr = (string) get_post_meta( get_queried_object_id(), '_tb_bodyattr', true );
}
if ( '' === $tb_bodyattr ) {
	$tb_bodyattr = 'id="hpb-template-05-08-01" class="hpb-layoutset-02"';
}
?><!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="ja">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="viewport" content="width=device-width,user-scalable=no,maximum-scale=1">
<link rel="icon" href="<?php echo esc_url( home_url( '/favicon.ico' ) ); ?>">
<link rel="apple-touch-icon" href="<?php echo esc_url( home_url( '/apple-touch-icon.png' ) ); ?>">
<?php // 元サイトの index.html にあった「スマホは /sp/index.html（旧モバイル版）へ」のリダイレクトは、
      // 2026-09-21 のクライアント判断で廃止。スマホにも他ページと同様に WordPress のトップページを表示する。 ?>
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','GTM-T3DMTM6');</script>
<!-- End Google Tag Manager -->
<?php wp_head(); ?>
</head>
<body <?php echo $tb_bodyattr; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
<?php wp_body_open(); ?>
