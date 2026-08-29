<?php
/**
 * トップページ テンプレート
 *
 * 「固定ページをトップに」設定した場合はそのページ本文を表示し、
 * 続けて最新のお知らせを差し込む。院内でトップ本文を編集できる。
 *
 * @package Teaching_Beauty
 */

get_header();
?>
<div class="tb-inner">
	<main class="tb-main" id="tb-main" role="main">

		<?php if ( have_posts() ) : ?>
			<?php
			while ( have_posts() ) :
				the_post();
				?>
				<div class="tb-content">
					<?php the_content(); ?>
				</div>
				<?php
			endwhile;
		endif;
		?>

		<?php
		// 最新のお知らせ（投稿）を差し込む
		$tb_news = new WP_Query(
			array(
				'post_type'      => 'post',
				'posts_per_page' => 3,
				'no_found_rows'  => true,
			)
		);
		if ( $tb_news->have_posts() ) :
			?>
			<section class="tb-news" aria-label="<?php esc_attr_e( '新着情報', 'teachingbeauty' ); ?>">
				<h2 class="tb-news-head"><span class="en">news</span><span>新着情報</span></h2>
				<?php
				while ( $tb_news->have_posts() ) :
					$tb_news->the_post();
					?>
					<article class="tb-news-item">
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
				wp_reset_postdata();
				?>
				<p class="tb-pagetop"><a href="<?php echo esc_url( home_url( '/news/' ) ); ?>">お知らせ一覧を見る ›</a></p>
			</section>
		<?php endif; ?>

	</main>

	<?php get_sidebar(); ?>
</div>
<?php
get_footer();
