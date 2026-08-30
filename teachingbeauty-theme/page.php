<?php
/**
 * 固定ページ — 原本HTMLをそのまま出力。
 *
 * @package Teaching_Beauty
 */

get_header();
while ( have_posts() ) {
	the_post();
	tb_render_raw();
}
get_footer();
