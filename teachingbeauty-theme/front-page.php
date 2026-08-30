<?php
/**
 * トップページ — 原本HTML（index.html の body）をそのまま出力。
 *
 * @package Teaching_Beauty
 */

get_header();
while ( have_posts() ) {
	the_post();
	tb_render_raw();
}
get_footer();
