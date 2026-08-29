<?php
/**
 * サイドバー（共通パーツ）
 * ウィジェットが未設定でも、現行のバナー＋店舗情報を再現して表示する。
 *
 * @package Teaching_Beauty
 */
?>
<aside class="tb-aside" role="complementary" aria-label="<?php esc_attr_e( 'サイド情報', 'teachingbeauty' ); ?>">

<?php if ( is_active_sidebar( 'sidebar-main' ) ) : ?>
	<?php dynamic_sidebar( 'sidebar-main' ); ?>
<?php else : ?>

	<ul class="tb-banners">
		<li><a href="<?php echo esc_url( home_url( '/kanja.html' ) ); ?>"><span class="en">voice</span>患者様の声</a></li>
		<li><a href="<?php echo esc_url( home_url( '/nhk.html' ) ); ?>"><span class="en">media</span>メディア出演</a></li>
		<li><a href="<?php echo esc_url( home_url( '/incho.html' ) ); ?>"><span class="en">director</span>院長紹介</a></li>
		<li><a href="<?php echo esc_url( home_url( '/access.html' ) ); ?>"><span class="en">access</span>アクセス</a></li>
		<li><a href="<?php echo esc_url( home_url( '/sinkyu.html' ) ); ?>"><span class="en">flow</span>治療の流れ</a></li>
		<li><a href="<?php echo esc_url( home_url( '/beauty.html' ) ); ?>"><span class="en">accident</span>交通事故・自賠責</a></li>
		<li><a href="<?php echo esc_url( home_url( '/reserve.html' ) ); ?>"><span class="en">reserve</span>ご予約</a></li>
		<li><a href="<?php echo esc_url( home_url( '/kokushi.html' ) ); ?>"><span class="en">school</span>国家試験予備校</a></li>
		<li><a href="<?php echo esc_url( home_url( '/esthetichtml.html' ) ); ?>"><span class="en">esthetic</span>エステティック</a></li>
		<li><a href="https://ameblo.jp/teaching-beauty/" rel="noopener"><span class="en">blog</span>スタッフブログ</a></li>
	</ul>

	<div class="tb-shopinfo">
		<h3>Teaching Beauty</h3>
		<div class="logo"><img src="<?php echo esc_url( get_template_directory_uri() . '/images/rogo12.jpg' ); ?>" width="200" height="119" alt="Teaching Beauty 鍼灸マッサージ整骨院"></div>
		<p class="tel"><a href="tel:0364137803">03-6413-7803</a></p>
		<p style="text-align:center;font-size:.85rem;margin:0 0 8px">〒154-0014 東京都世田谷区新町3-21-1<br>さくらウェルガーデン301号室</p>
		<dl>
			<dt>診療時間</dt>
			<dd>
				月・火・金・土　午前 09:00–13:00／午後 15:00–19:00<br>
				木　午後 15:00–19:00（午前休診）
			</dd>
			<dt class="closed">休診日</dt>
			<dd class="closed">水・木(午前)・日・祝</dd>
			<dt>夜間診療</dt>
			<dd>月火木金土 19:00–21:00（通常の1.5倍・当日19時までの予約）</dd>
			<dt>早朝診療</dt>
			<dd>月火木金土 07:00–09:00（通常の2倍・前日19時までの予約）</dd>
			<dt>取扱い</dt>
			<dd>交通事故・労災・各種保険</dd>
		</dl>
	</div>

<?php endif; ?>
</aside>
