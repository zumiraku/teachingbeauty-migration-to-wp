<?php
/**
 * フッター（共通パーツ）
 *
 * @package Teaching_Beauty
 */
?>
	<footer class="tb-footer" role="contentinfo">
		<nav class="tb-footer-nav" aria-label="<?php esc_attr_e( 'フッターナビゲーション', 'teachingbeauty' ); ?>">
			<?php
			if ( has_nav_menu( 'footer' ) ) {
				wp_nav_menu(
					array(
						'theme_location' => 'footer',
						'container'      => false,
						'menu_class'     => 'menu',
						'depth'          => 1,
					)
				);
			} else {
				echo '<ul class="menu">';
				echo '<li><a href="' . esc_url( home_url( '/kokushi.html' ) ) . '">国家試験予備校</a></li>';
				echo '<li><a href="' . esc_url( home_url( '/kyuujin.html' ) ) . '">求人募集</a></li>';
				echo '<li><a href="' . esc_url( home_url( '/privacy.html' ) ) . '">プライバシーポリシー</a></li>';
				echo '</ul>';
			}
			?>
		</nav>
		<p class="tb-copyright">copyright&copy;2013 Teaching Beauty all rights reserved.</p>
	</footer>

</div><!-- .tb-container -->

<?php wp_footer(); ?>
</body>
</html>
