<?php
/**
 * newpage27 のサイドバーを他のページと同じ形にし、求人ページの表の列幅を揃える。
 * /wp/ に置いて、使ったら消す。
 *   ?k=TOKEN&dry=1     … 変更予定を出すだけ
 *   ?k=TOKEN           … 適用
 *   ?k=TOKEN&restore=1 … 元に戻す
 *
 * newpage27: 旧サイトの時点でサイドバーにバナーが2個しか入っていなかった。
 *   他の38ページと同じ10個＋店舗情報（住所・電話・診療時間）に差し替える。
 *   本文には改行が入っているので、文字列そのままではなく形（正規表現）で探す。
 *
 * kyuujin: 表が3つあり、2つ目以降は左のセルに幅の指定が無く右が 531px。
 *   1つ目の表（63px / 579px）に揃えると縦線が合う。
 *
 * 何度流しても平気。差し替え済みなら飛ばす。
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

const TB_R3_BACKUP    = '_tb_round3_backup';
const TB_R3_BANNER_RE = '/<div id="banner">[\s\S]*?<\/ul>\s*<\/div>/';

// 他の38ページと同じサイドバー（本番の Meniere から取得）
$p27_to = <<<'TBSIDEBAR'
<div id="banner">
        <h3 class="hpb-c-index">バナースペース</h3>
        <ul>
          <li><a href="kanja.html" id="koe" style="color : #91384b;background-image : url(banner_5H_kanjasama1.gif);">患者様の声</a></li>
          <li><a href="nhk.html" id="media" style="color : #91384b;background-image : url(banner_5H_031.jpg);">media</a></li>
          <li><a href="incho.html" id="banner-incho">院長紹介</a></li>
          <li><a href="access.html" id="banner-trip">アクセス</a></li>
          <li><a href="sinkyu.html" id="banner-sinkyu">治療の流れ</a></li>
          <li><a href="beauty.html" id="banner-beauty">自賠責</a></li>
          <li><a href="reserve.html" id="banner-reserve">ご予約</a></li>
          <li><a href="kokushi.html" id="kokushi-trip" style="color : #91384b;background-image : url(banner_5H_211.jpg);">国家試験対策予備校</a></li>
          <li><a href="esthetichtml.html" id="esthetic" style="color : #91384b;background-image : url(banner_5H_211115.jpg);">ｴｽﾃﾃｨｯｸ</a></li>
          <li><a href="https://ameblo.jp/teaching-beauty/">スタッフブログ</a></li>
        </ul>
      </div>
      <div id="shopinfo">
		<h3><span class="en"></span>Ｔｅａｃｈｉｎｇ　Ｂｅａｕｔｙ</h3>
		<img class="wp-image-1111" src="https://www.teachingbeauty.jp/wp/wp-content/uploads/2020/01/rogo12.jpg" border="0" style="border-top-width : 0px;border-left-width : 0px;border-right-width : 0px;border-bottom-width : 0px;" width="219" height="130" alt="ＴｅａｃｈｉｎｇＢｅａｕｔｙ鍼灸マッサージ整骨院"><br><br><br><br><font size="3">〒154-0014<br>東京都世田谷区新町3-21-1<br>さくらウェルガーデン3F<br><br><br>TEL：</font><br><font size="5" color="#91384b"><a href="tel:0364137803">03-6413-7803</a><br><br><a href="access.html"><font size="4" color="#91384b">→アクセス</font></a></font><br><br><br><br><font size="3"><font size="3"><b><font size="4">診療時間</font></b><br>【通常診療】<br><b>月火金土</b><br>午前：09:00～13:00<br>午後：15:00～19:00<br><b>木曜日</b><br>午前：休診<br>午後：15:00～19:00<br>※当日予約可<br><br><font color="#ff0000">
        休診日</font><br>水、木(午前)、日、祭日<br><br>夜間診療<br>月火木金土／19:00～21:00<br>※通常価格の1.5倍料金<br><font color="#0000ff">※当日の19時までにご予約の場合のみ</font><br>※往診の場合も19時までにご予約下さい<br><br>早朝診療<br>月火木金土／07:00～09:00<br>※通常価格の2倍料金<br><font color="#0000ff">※前日の19時までにご予約の場合のみ</font><br><br>交通事故・労災・<br>各種保険取扱い</font></font>
      </div>
TBSIDEBAR;

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
		$old = get_post_meta( $page->ID, TB_R3_BACKUP, true );
		if ( '' === $old ) {
			continue;
		}
		$wpdb->update( $wpdb->posts, array( 'post_content' => $old ), array( 'ID' => $page->ID ) );
		clean_post_cache( $page->ID );
		delete_post_meta( $page->ID, TB_R3_BACKUP );
		printf( "%-16s 戻しました\n", $page->post_name );
		$n++;
	}
	printf( "\n%d ページを元に戻しました\n", $n );
	echo "STATUS RESTORED\n";
	exit;
}

$touched = 0;

foreach ( $pages as $page ) {
	$before = $page->post_content;
	$after  = $before;
	$hits   = array();

	if ( 'newpage27' === $page->post_name ) {
		if ( false !== strpos( $after, 'id="shopinfo"' ) ) {
			$hits[] = 'サイドバーは差し替え済み';
		} elseif ( ! preg_match( TB_R3_BANNER_RE, $after ) ) {
			echo "newpage27: サイドバーが見つかりません\n";
			echo "STATUS ABORT\n";
			exit( 1 );
		} else {
			$after  = preg_replace_callback(
				TB_R3_BANNER_RE,
				function () use ( $p27_to ) {
					return $p27_to;
				},
				$after,
				1
			);
			$hits[] = 'サイドバーを他のページと同じ形に（バナー2個 → 10個＋店舗情報）';
		}
	}

	if ( 'kyuujin' === $page->post_name ) {
		$a = 0;
		$after = preg_replace( '/(<tr[^>]*>\s*)<td>/i', '$1<td width="63">', $after, -1, $a );
		if ( $a ) {
			$hits[] = sprintf( '表の左のセルに幅 63px を指定（%d 箇所）', $a );
		}
		$b = 0;
		$after = preg_replace( '/<td width="531">/i', '<td width="579">', $after, -1, $b );
		if ( $b ) {
			$hits[] = sprintf( '表の右のセルを 531px → 579px に（%d 箇所）', $b );
		}
	}

	if ( $before === $after ) {
		if ( $hits ) {
			printf( "%-16s %s\n", $page->post_name, $hits[0] );
		}
		continue;
	}

	$touched++;
	printf( "%s\n", $page->post_name );
	foreach ( $hits as $h ) {
		printf( "    %s\n", $h );
	}

	if ( $dry ) {
		continue;
	}

	if ( '' === get_post_meta( $page->ID, TB_R3_BACKUP, true ) ) {
		update_post_meta( $page->ID, TB_R3_BACKUP, wp_slash( $before ) );
	}
	// wp_update_post() は使わない。KSES が旧サイトの閉じ忘れタグを書き換えるし、
	// 渡した値を wp_unslash() するので本文中のバックスラッシュも消える。
	$wpdb->update( $wpdb->posts, array( 'post_content' => $after ), array( 'ID' => $page->ID ) );
	clean_post_cache( $page->ID );
}

printf( "\n対象 %d ページ\n", $touched );
echo $dry ? "STATUS DRY\n" : "STATUS DONE\n";
