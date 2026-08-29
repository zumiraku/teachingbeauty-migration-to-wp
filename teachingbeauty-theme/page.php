<?php
/**
 * 固定ページ テンプレート（当院について・施術・症状別 など）
 *
 * @package Teaching_Beauty
 */

get_header();
?>
<div class="tb-pagetitle">
	<h1><?php the_title(); ?></h1>
</div>

<div class="tb-inner">
	<main class="tb-main" id="tb-main" role="main">
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article <?php post_class( 'tb-content' ); ?>>
				<?php the_content(); ?>
				<?php
				wp_link_pages(
					array(
						'before' => '<div class="tb-pagination">' . esc_html__( 'ページ:', 'teachingbeauty' ),
						'after'  => '</div>',
					)
				);
				?>
				<div class="tb-pagetop"><a href="#tb-nav">▲ このページの先頭へ</a></div>
			</article>
			<?php
		endwhile;
		?>
	</main>

	<?php get_sidebar(); ?>
</div>
<?php
get_footer();
