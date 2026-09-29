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
<?php // レイアウトは 941px 固定。width=device-width だと「画面の幅＝ページの幅」と宣言することになり、
	// iPhone では 941px が画面に収まらず左右が切れる（iPhone 13 で 390px に対し 941px）。
	// 実寸を宣言すれば、iPhone 側が全体を縮めて画面に収めてくれる。
	// ちょうど 941 だと中身（#hpb-inner 941px 固定）が画面の端に貼りつき、
	// 一文字目が切れているように見える。左右に 12px ずつ足した 965 を宣言して、
	// #hpb-inner が自分で中央に寄るぶんの余白を作る。
	// 拡大禁止も外す。縮んだぶん文字が小さくなるので、指で広げて読めるようにしておく。 ?>
<meta name="viewport" content="width=965">
<link rel="icon" href="<?php echo esc_url( home_url( '/favicon.ico' ) ); ?>">
<link rel="apple-touch-icon" href="<?php echo esc_url( home_url( '/apple-touch-icon.png' ) ); ?>">
<?php
// 検索結果でページ名の上に出るサイト名の手がかり。値は「設定 → 一般 → サイトのタイトル」。
// 各ページの <title> は post_title をそのまま使うので、ここは関係しない。
$tb_site_name = get_bloginfo( 'name' );
if ( '' !== $tb_site_name ) :
?>
<meta property="og:site_name" content="<?php echo esc_attr( $tb_site_name ); ?>">
<?php if ( is_front_page() ) : ?>
<script type="application/ld+json"><?php echo wp_json_encode( array( '@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => $tb_site_name, 'url' => home_url( '/' ) ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?></script>
<?php endif; ?>
<?php endif; ?>
<?php // 旧 index.html にあったスマホ→/sp/index.html のリダイレクトは 2026-09-21 に廃止（先方の希望）。 ?>
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','GTM-T3DMTM6');</script>
<!-- End Google Tag Manager -->
<?php wp_head(); ?>
</head>
<body <?php echo $tb_bodyattr; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
<?php wp_body_open(); ?>
