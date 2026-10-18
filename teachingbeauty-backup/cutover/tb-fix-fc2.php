<?php
/**
 * ご予約ページのフォームを、元サイトと同じ FC2 メールフォームに戻す。
 * /wp/ に置いて、使ったら消す。
 *   ?k=TOKEN&dry=1     … 変更予定を出すだけ
 *   ?k=TOKEN           … 適用
 *   ?k=TOKEN&restore=1 … 元に戻す（Contact Form 7 に戻る）
 *
 * 移行のときに元サイトの FC2 フォームを Contact Form 7 に置き換えたが、
 * 納品後は院長がご自分で管理されるため、FC2 のほうが扱いやすいとのことで戻す。
 *
 * 差し替えるのはショートコード1か所だけ。前後の本文には触らない。
 * 入れる中身は元サイト（www/reserve.html）のものをそのまま使う。
 *
 * 注意: <noscript> の中の [FC2メールフォーム] は角かっこだが、登録されていない
 * ショートコードは WordPress がそのまま出すだけなので問題ない。
 *
 * 注意: 本文に <script> が入る。管理者は unfiltered_html を持つので編集画面で
 * 保存しても消えないが、ビジュアルタブで触ると TinyMCE が動かすことがある。
 * このページを直すときはテキストタブで編集すること。
 */

$cli = ( PHP_SAPI === 'cli' );
if ( ! $cli ) {
	header( 'Content-Type: text/plain; charset=UTF-8' );
	if ( ! isset( $_GET['k'] ) || '__TB_TOKEN__' !== $_GET['k'] ) {
		http_response_code( 404 );
		exit( 'not found' );
	}
	$dry     = ! empty( $_GET['dry'] );
	$restore = ! empty( $_GET['restore'] );
	define( 'WP_USE_THEMES', false );
	require_once __DIR__ . '/wp-load.php';
} else {
	$dry     = in_array( '--dry', $argv, true );
	$restore = in_array( '--restore', $argv, true );
	$root = getenv( 'TB_WP_ROOT' ) ?: __DIR__;
	$_SERVER['HTTP_HOST']   = getenv( 'TB_HOST' ) ?: '127.0.0.1:8765';
	$_SERVER['REQUEST_URI'] = '/';
	require_once $root . '/wp-load.php';
}
global $wpdb;

const TB_FC2_BACKUP = '_tb_fc2_backup';
const TB_FC2_SLUG   = 'reserve';
// ショートコードは id が変わっている可能性があるので属性は緩く見る。
const TB_CF7_RE = '#\[contact-form-7[^\]]*\]#';

$fc2 = "<!-- FC2メールフォーム Start -->\n"
	. '<script type="text/javascript" src="https://form1ssl.fc2.com/parts/?id=858290"></script>' . "\n"
	. '<noscript><a href="https://form1ssl.fc2.com/form/?id=6c82e89b62069752" target="_blank">[FC2メールフォーム]</a></noscript>' . "\n"
	. '<!-- FC2メールフォーム End -->';

$page = get_page_by_path( TB_FC2_SLUG, OBJECT, 'page' );
if ( ! $page ) {
	echo "ご予約ページ（" . TB_FC2_SLUG . "）が見つからない\n";
	echo "STATUS ABORT\n";
	exit;
}

if ( $restore ) {
	$old = get_post_meta( $page->ID, TB_FC2_BACKUP, true );
	if ( '' === $old ) {
		echo "差し替え前の控えが無い\n";
		echo "STATUS ABORT\n";
		exit;
	}
	$wpdb->update( $wpdb->posts, array( 'post_content' => $old ), array( 'ID' => $page->ID ) );
	clean_post_cache( $page->ID );
	delete_post_meta( $page->ID, TB_FC2_BACKUP );
	echo "Contact Form 7 に戻しました\n";
	echo "STATUS RESTORED\n";
	exit;
}

$before = $page->post_content;

if ( false !== strpos( $before, 'form1ssl.fc2.com' ) && ! preg_match( TB_CF7_RE, $before ) ) {
	echo "済 すでに FC2 のフォームになっている\n";
	echo "STATUS DONE\n";
	exit;
}

$hits = preg_match_all( TB_CF7_RE, $before, $m );
if ( 1 !== $hits ) {
	printf( "Contact Form 7 のショートコードが %d 個（1つのはず）\n", $hits );
	echo "STATUS ABORT\n";
	exit;
}
printf( "見つけたショートコード: %s\n", $m[0][0] );

$n     = 0;
$after = preg_replace( TB_CF7_RE, $fc2, $before, -1, $n );
if ( null === $after || 1 !== $n ) {
	echo "置き換えに失敗\n";
	echo "STATUS ABORT\n";
	exit;
}

// 入れ替えた箇所以外がひとつも変わっていないことを確かめる。
$a = str_replace( $m[0][0], '', $before );
$b = str_replace( $fc2, '', $after );
if ( $a !== $b ) {
	echo "差し替えた場所以外も変わってしまう\n";
	echo "STATUS ABORT\n";
	exit;
}
if ( false === strpos( $after, 'form1ssl.fc2.com/parts/?id=858290' ) ) {
	echo "FC2 のスクリプトが入っていない\n";
	echo "STATUS ABORT\n";
	exit;
}
if ( false === strpos( $after, 'form1ssl.fc2.com/form/?id=6c82e89b62069752' ) ) {
	echo "スクリプトが動かないときの予備リンクが入っていない\n";
	echo "STATUS ABORT\n";
	exit;
}
if ( false !== strpos( $after, 'contact-form-7' ) ) {
	echo "Contact Form 7 のショートコードが残っている\n";
	echo "STATUS ABORT\n";
	exit;
}

printf( "%-16s OK FC2 のフォームに戻す（%d 文字 → %d 文字）\n", $page->post_name, strlen( $before ), strlen( $after ) );

if ( $dry ) {
	echo "STATUS DRY\n";
	exit;
}

if ( '' === get_post_meta( $page->ID, TB_FC2_BACKUP, true ) ) {
	// update_post_meta() は渡した値を wp_unslash() するので、控えは wp_slash() して入れる。
	update_post_meta( $page->ID, TB_FC2_BACKUP, wp_slash( $before ) );
}
// wp_update_post() は使わない。KSES が <script> を落とすし、旧サイトの閉じ忘れタグも書き換える。
$wpdb->update( $wpdb->posts, array( 'post_content' => $after ), array( 'ID' => $page->ID ) );
clean_post_cache( $page->ID );

echo "STATUS DONE\n";
