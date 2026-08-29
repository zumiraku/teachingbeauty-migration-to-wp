<?php
/**
 * 404 テンプレート
 *
 * @package Teaching_Beauty
 */

get_header();
?>
<div class="tb-pagetitle"><h1>ページが見つかりません</h1></div>
<div class="tb-inner">
	<main class="tb-main" id="tb-main" role="main">
		<div class="tb-content">
			<p>お探しのページは移動または削除された可能性があります。お手数ですが、下記よりお進みください。</p>
			<div class="tb-cta">
				<a class="tb-btn tb-btn-primary" href="<?php echo esc_url( home_url( '/' ) ); ?>">トップページへ</a>
				<a class="tb-btn tb-btn-tel" href="tel:0364137803">お電話でのお問い合わせ</a>
			</div>
			<?php get_search_form(); ?>
		</div>
	</main>
	<?php get_sidebar(); ?>
</div>
<?php
get_footer();
