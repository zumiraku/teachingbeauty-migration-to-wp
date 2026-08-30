<?php
/**
 * 投稿 — 原本HTMLをそのまま出力（本サイトでは通常未使用）。
 *
 * @package Teaching_Beauty
 */

get_header();
while ( have_posts() ) {
	the_post();
	tb_render_raw();
}
get_footer();
