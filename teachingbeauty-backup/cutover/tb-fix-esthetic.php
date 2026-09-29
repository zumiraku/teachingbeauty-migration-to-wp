<?php
/**
 * エステのページの見出し3本を、1行目のタイトルだけにする。
 * /wp/ に置いて、使ったら消す。
 *   ?k=TOKEN&dry=1     … 変更予定を出すだけ
 *   ?k=TOKEN           … 適用
 *   ?k=TOKEN&restore=1 … 元に戻す
 *
 * 美脚リンパマッサージ・ボディオプション・ブライダルエステの3本は、
 * タイトルのあとに <br> を挟んで料金や説明文まで見出しに入っている。
 * そのせいで文字を揃える指定の対象から外れ、この3本だけ 18px のまま
 * 他の6本（24px）と揃っていなかった。
 * 1行目で見出しを閉じ、残りは本文として出す。
 * 無料相談・交通事故施術でやったのと同じ直し方。
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

const TB_ESTHETIC_BACKUP = '_tb_esthetic_backup';

$fixes = array(
	array(
		'slug'  => 'esthetichtml',
		'label' => '「美脚リンパマッサージ」の見出しを1行目だけにする',
		'from'  => '<h3><font size="1"><font size="4">美脚リンパマッサージ　</font><font size="2">※10分のフットバス含む　　　　　　　</font><font size="4">　　　　　　　Reflexology</font><font size="2">　　　　　　　　　　　　　　　　</font></font><br><font size="3">【40分】</font><font size="5">￥4,500</font><font size="2">（税別）</font><font size="4">/</font><font size="3">【70分】</font><font size="5">￥9,000</font><font size="2">（税別）</font></h3>',
		'to'    => '<h3><font size="1"><font size="4">美脚リンパマッサージ　</font><font size="2">※10分のフットバス含む　　　　　　　</font><font size="4">　　　　　　　Reflexology</font><font size="2">　　　　　　　　　　　　　　　　</font></font></h3><font size="3">【40分】</font><font size="5">￥4,500</font><font size="2">（税別）</font><font size="4">/</font><font size="3">【70分】</font><font size="5">￥9,000</font><font size="2">（税別）</font>',
	),
	array(
		'slug'  => 'esthetichtml',
		'label' => '「ボディオプション」の見出しを1行目だけにする',
		'from'  => '<h3><font size="4">ボディオプション　</font>　　　　　　　　　　　　　　　　　　　　　　　　　　　　<font size="4">Body Option</font><br><br><font size="3">【角質ケア／１パーツ】</font><font size="5">￥2,000</font>（税別）</h3>',
		'to'    => '<h3><font size="4">ボディオプション　</font>　　　　　　　　　　　　　　　　　　　　　　　　　　　　<font size="4">Body Option</font></h3><br><font size="3">【角質ケア／１パーツ】</font><font size="5">￥2,000</font>（税別）',
	),
	array(
		'slug'  => 'esthetichtml',
		'label' => '「ブライダルエステ」の見出しを1行目だけにする',
		'from'  => '<h3><font size="4">ブライダルエステ　　　　　　　　　　　　　　　　　　　　Bridal Esthetic</font><br><br><font size="3">一生に一度の結婚式、最も美しい状態で式を迎えたくありませんか？ <br>胸元、背中、首筋など、どの角度から写真を撮られても美しく見えるように究極のエステを<br>お届けします。 <br>肌の色を白くし、お肌の状態を整えることにより最高の美を手に入れます。 <br>大切な方へのプレゼントとしてもご利用頂けます。</font>
          <br></h3>',
		'to'    => '<h3><font size="4">ブライダルエステ　　　　　　　　　　　　　　　　　　　　Bridal Esthetic</font></h3><br><font size="3">一生に一度の結婚式、最も美しい状態で式を迎えたくありませんか？ <br>胸元、背中、首筋など、どの角度から写真を撮られても美しく見えるように究極のエステを<br>お届けします。 <br>肌の色を白くし、お肌の状態を整えることにより最高の美を手に入れます。 <br>大切な方へのプレゼントとしてもご利用頂けます。</font>
          <br>',
	),
);

$pages = get_posts(
	array(
		'post_type'      => 'page',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'orderby'        => 'ID',
		'order'          => 'ASC',
	)
);

if ( $restore ) {
	$n = 0;
	foreach ( $pages as $page ) {
		$old = get_post_meta( $page->ID, TB_ESTHETIC_BACKUP, true );
		if ( '' === $old ) {
			continue;
		}
		$wpdb->update( $wpdb->posts, array( 'post_content' => $old ), array( 'ID' => $page->ID ) );
		clean_post_cache( $page->ID );
		delete_post_meta( $page->ID, TB_ESTHETIC_BACKUP );
		printf( "%-16s 戻しました\n", $page->post_name );
		$n++;
	}
	printf( "\n%d ページを元に戻しました\n", $n );
	echo "STATUS RESTORED\n";
	exit;
}

$by_slug = array();
foreach ( $pages as $page ) {
	$by_slug[ $page->post_name ] = $page;
}

$work  = array();
$abort = false;
$total = 0;

foreach ( $fixes as $fix ) {
	$slug = $fix['slug'];
	if ( ! isset( $by_slug[ $slug ] ) ) {
		printf( "%-16s ページが見つからない\n", $slug );
		$abort = true;
		continue;
	}
	$id  = $by_slug[ $slug ]->ID;
	$cur = isset( $work[ $id ] ) ? $work[ $id ] : $by_slug[ $slug ]->post_content;

	$hits = substr_count( $cur, $fix['from'] );
	if ( 0 === $hits ) {
		if ( false !== strpos( $cur, $fix['to'] ) ) {
			printf( "%-16s 済 %s\n", $slug, $fix['label'] );
			continue;
		}
		printf( "%-16s 見つからない: %s\n", $slug, $fix['label'] );
		$abort = true;
		continue;
	}
	if ( $hits > 1 ) {
		printf( "%-16s %d 箇所あって絞れない: %s\n", $slug, $hits, $fix['label'] );
		$abort = true;
		continue;
	}

	$work[ $id ] = str_replace( $fix['from'], $fix['to'], $cur );
	printf( "%-16s OK %s\n", $slug, $fix['label'] );
	$total++;
}

if ( $abort ) {
	echo "\nSTATUS ABORT\n";
	exit;
}

foreach ( $work as $id => $after ) {
	$before = get_post_field( 'post_content', $id );
	if ( $before === $after ) {
		continue;
	}
	foreach ( array( 'h3', 'font', 'div' ) as $tag ) {
		$d1 = preg_match_all( "/<$tag\b/i", $before ) - preg_match_all( "/<\/$tag>/i", $before );
		$d2 = preg_match_all( "/<$tag\b/i", $after ) - preg_match_all( "/<\/$tag>/i", $after );
		if ( $d1 !== $d2 ) {
			printf( "%s の開閉が合わない（前 %d / 後 %d）\n", $tag, $d1, $d2 );
			echo "\nSTATUS ABORT\n";
			exit;
		}
	}
	if ( $dry ) {
		continue;
	}
	if ( '' === get_post_meta( $id, TB_ESTHETIC_BACKUP, true ) ) {
		update_post_meta( $id, TB_ESTHETIC_BACKUP, wp_slash( $before ) );
	}
	// wp_update_post() は使わない。KSES が旧サイトの閉じ忘れタグを書き換えるし、
	// 渡した値を wp_unslash() するので本文中のバックスラッシュも消える。
	$wpdb->update( $wpdb->posts, array( 'post_content' => $after ), array( 'ID' => $id ) );
	clean_post_cache( $id );
}

printf( "\n見出しバー %d 本\n", $total );
echo $dry ? "STATUS DRY\n" : "STATUS DONE\n";
