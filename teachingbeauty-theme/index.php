<?php
/**
 * 汎用フォールバック — 原本HTMLをそのまま出力。
 *
 * @package Teaching_Beauty
 */

get_header();
if ( have_posts() ) {
	while ( have_posts() ) {
		the_post();
		tb_render_raw();
	}
}
get_footer();
