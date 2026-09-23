<?php
/**
 * 切替用の使い捨てスクリプト。/wp/ に置いて、使ったら消す。
 *   ?k=TOKEN&do=status … 今の設定を見るだけ
 *   ?k=TOKEN&do=root   … home をルートに（ルートに index.php が無ければ何もしない）
 *   ?k=TOKEN&do=wp     … home を /wp/ に戻す
 *
 * siteurl は /wp/ のまま触らない。管理画面とアップロードの URL が動かないように。
 */

header( 'Content-Type: text/plain; charset=UTF-8' );
if ( ! isset( $_GET['k'] ) || '__TB_TOKEN__' !== $_GET['k'] ) {
	http_response_code( 404 );
	exit( 'not found' );
}
$do = isset( $_GET['do'] ) ? $_GET['do'] : 'status';

define( 'WP_USE_THEMES', false );
require_once __DIR__ . '/wp-load.php';

$siteurl = untrailingslashit( get_option( 'siteurl' ) );
$root    = preg_replace( '#/wp$#', '', $siteurl );

if ( 'root' === $do ) {
	if ( ! file_exists( dirname( ABSPATH ) . '/index.php' ) ) {
		exit( "中止: サイトルートに index.php がありません。先に置いてください。\n" );
	}
	update_option( 'home', $root );
	// 本番用の後始末: 検索エンジンに公開（noindex を出さない）、サイト名から検証ラベルを外す
	update_option( 'blog_public', 1 );
	$name = trim( preg_replace( '/\s*[（(]検証[）)]\s*/u', '', (string) get_option( 'blogname' ) ) );
	if ( '' !== $name ) { update_option( 'blogname', $name ); }
	flush_rewrite_rules();
	echo "サイトアドレス(home) をルートに切り替えました。\n";
} elseif ( 'wp' === $do ) {
	update_option( 'home', $siteurl );
	flush_rewrite_rules();
	echo "サイトアドレス(home) を /wp/ に戻しました。\n";
}

$front = (int) get_option( 'page_on_front' );
$c     = get_page_by_path( 'concept' );
echo "siteurl (WordPress アドレス): " . get_option( 'siteurl' ) . "\n";
echo "home    (サイトアドレス)    : " . get_option( 'home' ) . "\n";
echo "front page                   : " . get_permalink( $front ) . "\n";
echo "concept                      : " . ( $c ? get_permalink( $c ) : '(not found)' ) . "\n";
echo "admin                        : " . admin_url() . "\n";
echo "rest                         : " . rest_url() . "\n";
echo "blogname                     : " . get_option( 'blogname' ) . "\n";
echo "blog_public (検索エンジン公開): " . get_option( 'blog_public' ) . "\n";
echo "pages (publish)              : " . count( get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' ) ) ) . "\n";
echo '[STATUS ' . strtoupper( $do ) . ' home=' . get_option( 'home' ) . "]\n";
