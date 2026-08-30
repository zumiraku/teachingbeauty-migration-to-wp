<?php
/**
 * 6ページのタイトルを原本の<title>そのまま（全角スペース含む）に修正する。
 */
$backup = 'C:\\Users\\Administrator\\Documents\\New folder\\teachingbeauty-backup\\www\\';
$slugs  = array( 'esthetichtml', 'kyuujin', 'kyuu', 'seitai', 'kairopura', 'newpage22' );
global $wpdb;
foreach ( $slugs as $slug ) {
	$file = $backup . $slug . '.html';
	if ( ! file_exists( $file ) ) { echo "MISS file $slug\n"; continue; }
	$raw = file_get_contents( $file );
	if ( preg_match( '/charset\s*=\s*["\']?\s*shift_jis/i', $raw ) ) {
		$raw = mb_convert_encoding( $raw, 'UTF-8', 'SJIS-win' );
	}
	if ( ! preg_match( '/<title>(.*?)<\/title>/is', $raw, $m ) ) { echo "MISS title $slug\n"; continue; }
	$title = trim( $m[1] );
	$page  = get_page_by_path( $slug );
	if ( ! $page ) { echo "MISS page $slug\n"; continue; }
	$wpdb->update( $wpdb->posts, array( 'post_title' => $title ), array( 'ID' => $page->ID ) );
	clean_post_cache( $page->ID );
	echo "updated {$slug} (id {$page->ID}): {$title}\n";
}
