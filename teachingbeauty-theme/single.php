<?php
/**
 * 投稿 テンプレート（お知らせ・患者様の声の個別）
 *
 * @package Teaching_Beauty
 */

get_header();
?>
<div class="tb-pagetitle">
	<h1><?php single_post_title(); ?></h1>
</div>

<div class="tb-inner">
	<main class="tb-main" id="tb-main" role="main">
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article <?php post_class( 'tb-content' ); ?>>
				<p class="tb-post-meta"><time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time></p>
				<?php the_content(); ?>
				<div class="tb-pagetop"><a href="#tb-nav">▲ このページの先頭へ</a></div>
			</article>
			<nav class="tb-postnav" aria-label="<?php esc_attr_e( '前後の記事', 'teachingbeauty' ); ?>">
				<?php previous_post_link( '<span class="prev">%link</span>' ); ?>
				<?php next_post_link( '<span class="next">%link</span>' ); ?>
			</nav>
			<?php
		endwhile;
		?>
	</main>

	<?php get_sidebar(); ?>
</div>
<?php
get_footer();
