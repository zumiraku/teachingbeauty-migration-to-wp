<?php
/**
 * 見出しバーのズレを直す。
 * /wp/ に置いて、使ったら消す。
 *   ?k=TOKEN&dry=1     … 変更予定を出すだけ
 *   ?k=TOKEN           … 適用
 *   ?k=TOKEN&restore=1 … 元に戻す
 *
 * 帯の絵（indexBg_5H.png）は 690x33。見出しの文字がこの 33px に収まらないと
 * 帯からはみ出して見える。トップの「不妊症施術」は 32px あって下端が 38px まで
 * 伸びていた。新着情報と同じ 24px / #480a17 に揃える。
 *
 * 「無料相談」「交通事故施術」は </h3> の閉じ忘れで本文まで飲み込んでいる
 * （帯の高さが 121px / 442px になっていた）。飲み込まれた本文は h3 の
 * padding-left 12px ぶん右にズレる。見出しの直後で閉じれば本文は 0px に戻り、
 * 他のページと同じ位置に揃う。
 *
 * ※ 同じ閉じ忘れは全体で 52 本 / 19 ページある。今回はクライアントから
 *    指定のあったこの2本だけ直す。kanja.html の 21 本はクライアント自身が
 *    編集するとのことなので触らない。
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

const TB_BARS2_BACKUP = '_tb_bars2_backup';

$fixes = array(
	array(
		'slug'  => 'home',
		'label' => '「不妊症施術」を新着情報と同じ大きさ・同じ色に',
		'from'  => '<h3><font size="6" color="#0000ff">不妊症施術</font></h3>',
		'to'    => '<h3><font size="5" color="#480a17">不妊症施術</font></h3>',
	),
	array(
		'slug'  => 'home',
		'label' => '【TeachingBeauty…の思い】の色を新着情報に揃える',
		'from'  => '<h3><font size="5" color="#0000cc">【TeachingBeauty鍼灸マッサージ整骨院・整体院の思い】</font></h3>',
		'to'    => '<h3><font size="5" color="#480a17">【TeachingBeauty鍼灸マッサージ整骨院・整体院の思い】</font></h3>',
	),
	array(
		'slug'  => 'muryou',
		'label' => '「無料相談」が本文を飲み込んでいるのを直す',
		'from'  => '<h3><font size="5">無料相談</font><br><font color="#ff0000" size="4">当院に掛かりたい意志のある方に対する無料相談になります。</font><br><br><b><font color="#ff0000" size="4">※当院に関係のないご相談や営業電話はご遠慮ください。<br>また、youtubeの質問はコメント欄での対応のみとなります。<br>ご了承ください。</font></b></h3>',
		'to'    => '<h3><font size="5">無料相談</font></h3><font color="#ff0000" size="4">当院に掛かりたい意志のある方に対する無料相談になります。</font><br><br><b><font color="#ff0000" size="4">※当院に関係のないご相談や営業電話はご遠慮ください。<br>また、youtubeの質問はコメント欄での対応のみとなります。<br>ご了承ください。</font></b>',
	),
	array(
		'slug'  => 'beauty',
		'label' => '「交通事故施術（むちうち施術）」が本文を飲み込んでいるのを直す',
		'from'  => '<h3><font size="5"><a name="koutujiko" id="koutujiko"></a>交通事故施術（むちうち施術）</font><br><font color="#000000" size="4">当院の高い技術が</font><font color="#ff0000" size="7">無料</font><font color="#000000" size="4">（自賠責保険負担）で受けられます。</font><font size="4" color="#000000"><br>お知り合いの方が事故に遭われた際にはご相談下さい。</font><font color="#000000" size="2"><br><br>※但し、他の接骨院からの転院を除く。事故後1ヶ月以内の方。紹介の場合でも当院でお引き受けできない疾患もありますので、必ずお電話にてご相談下さい。<br>尚、紹介者様の施術期間がすべて終了したのちに紹介者様に60分マッサージの施術を提供いたします。</font><br><br><font size="5">TEL:</font><font size="6" color="#000000">03-6413-7803</font><br><br><img class="wp-image-1224" src="https://www.teachingbeauty.jp/wp/wp-content/uploads/2020/01/room2221.jpg" width="306" height="230" border="0" alt="TeachingBeauty鍼灸整骨院の待合室の画像"><img class="wp-image-1225" src="https://www.teachingbeauty.jp/wp/wp-content/uploads/2020/01/IMG_4083.jpg" width="309" height="230" border="0" alt="治療室内の画像"></h3>',
		'to'    => '<h3><font size="5"><a name="koutujiko" id="koutujiko"></a>交通事故施術（むちうち施術）</font></h3><font color="#000000" size="4">当院の高い技術が</font><font color="#ff0000" size="7">無料</font><font color="#000000" size="4">（自賠責保険負担）で受けられます。</font><font size="4" color="#000000"><br>お知り合いの方が事故に遭われた際にはご相談下さい。</font><font color="#000000" size="2"><br><br>※但し、他の接骨院からの転院を除く。事故後1ヶ月以内の方。紹介の場合でも当院でお引き受けできない疾患もありますので、必ずお電話にてご相談下さい。<br>尚、紹介者様の施術期間がすべて終了したのちに紹介者様に60分マッサージの施術を提供いたします。</font><br><br><font size="5">TEL:</font><font size="6" color="#000000">03-6413-7803</font><br><br><img class="wp-image-1224" src="https://www.teachingbeauty.jp/wp/wp-content/uploads/2020/01/room2221.jpg" width="306" height="230" border="0" alt="TeachingBeauty鍼灸整骨院の待合室の画像"><img class="wp-image-1225" src="https://www.teachingbeauty.jp/wp/wp-content/uploads/2020/01/IMG_4083.jpg" width="309" height="230" border="0" alt="治療室内の画像">',
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
		$old = get_post_meta( $page->ID, TB_BARS2_BACKUP, true );
		if ( '' === $old ) {
			continue;
		}
		$wpdb->update( $wpdb->posts, array( 'post_content' => $old ), array( 'ID' => $page->ID ) );
		clean_post_cache( $page->ID );
		delete_post_meta( $page->ID, TB_BARS2_BACKUP );
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
	// h3 と font の開閉の数が変わっていないか念のため確かめる。
	foreach ( array( 'h3', 'font' ) as $tag ) {
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
	if ( '' === get_post_meta( $id, TB_BARS2_BACKUP, true ) ) {
		update_post_meta( $id, TB_BARS2_BACKUP, wp_slash( $before ) );
	}
	// wp_update_post() は使わない。KSES が旧サイトの閉じ忘れタグを書き換えるし、
	// 渡した値を wp_unslash() するので本文中のバックスラッシュも消える。
	$wpdb->update( $wpdb->posts, array( 'post_content' => $after ), array( 'ID' => $id ) );
	clean_post_cache( $id );
}

printf( "\n直した見出し %d 本\n", $total );
echo $dry ? "STATUS DRY\n" : "STATUS DONE\n";
