<?php
/**
 * 404 — 元サイトのレイアウトを保ったシンプルな案内。
 *
 * @package Teaching_Beauty
 */

get_header();
?>
<div id="hpb-container">
	<div id="hpb-inner">
		<div id="hpb-wrapper">
			<div id="hpb-main">
				<h2><span class="ja">ページが見つかりません</span></h2>
				<p style="padding:1.5em;">お探しのページは移動または削除された可能性があります。<br>
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>">トップページへ戻る</a></p>
			</div>
		</div>
	</div>
</div>
<?php
get_footer();
