<?php
/**
 * 汎用テンプレート / お知らせ・患者様の声の一覧（アーカイブの受け皿）
 *
 * @package Teaching_Beauty
 */

get_header();
?>
<div class="tb-pagetitle">
	<h1>
		<?php
		if ( is_home() && ! is_front_page() ) {
			single_post_title();
		} elseif ( is_archive() ) {
			echo esc_html( wp_strip_all_tags( get_the_archive_title() ) );
		} elseif ( is_search() ) {
			printf( esc_html__( '「%s」の検索結果', 'teachingbeauty' ), esc_html( get_search_query() ) );
		} else {
			esc_html_e( 'お知らせ', 'teachingbeauty' );
		}
		?>
	</h1>
</div>

<div class="tb-inner">
	<main class="tb-main" id="tb-main" role="main">
		<?php if ( have_posts() ) : ?>
			<div class="tb-content">
				<?php
				while ( have_posts() ) :
					the_post();
					?>
					<article <?php post_class( 'tb-news-item' ); ?>>
						<?php if ( has_post_thumbnail() ) : ?>
							<div class="thumb"><?php the_post_thumbnail( 'tb-news-thumb' ); ?></div>
						<?php endif; ?>
						<div class="body">
							<span class="date"><time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time></span>
							<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
							<?php the_excerpt(); ?>
						</div>
					</article>
					<?php
				endwhile;
				?>
			</div>
			<?php
			the_posts_pagination(
				array(
					'mid_size'  => 1,
					'prev_text' => __( '前へ', 'teachingbeauty' ),
					'next_text' => __( '次へ', 'teachingbeauty' ),
				)
			);
			?>
		<?php else : ?>
			<div class="tb-content"><p><?php esc_html_e( '該当する記事がありませんでした。', 'teachingbeauty' ); ?></p></div>
		<?php endif; ?>
	</main>

	<?php get_sidebar(); ?>
</div>
<?php
get_footer();
