<?php
/**
 * 検索フォーム
 *
 * @package Teaching_Beauty
 */
?>
<form role="search" method="get" class="tb-searchform" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="tb-s"><?php esc_html_e( 'サイト内検索', 'teachingbeauty' ); ?></label>
	<input type="search" id="tb-s" name="s" placeholder="<?php esc_attr_e( 'キーワードで検索', 'teachingbeauty' ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>">
	<button type="submit" class="tb-btn tb-btn-primary"><?php esc_html_e( '検索', 'teachingbeauty' ); ?></button>
</form>
