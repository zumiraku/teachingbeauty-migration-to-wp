<?php
/**
 * Plugin Name: TB Editor
 * Description: 旧サイトの HTML をそのまま持っている固定ページを、崩さずに編集できるようにする。
 * Version: 1.0.0
 *
 * 置き場所: wp-content/mu-plugins/tb-editor.php
 *
 * このテーマは wpautop を通さず本文をそのまま出力している。
 * 既定の TinyMCE は保存時に <p>/<br> を落として表示側の wpautop に任せる作りなので、
 * そのままだと一度保存しただけで行間と段落が消える。<font> の <span> 変換も同じ。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ブロックエディターのクラシックブロックは狭いモーダルで「メディアを追加」も無い。従来の編集画面を使う。
add_filter( 'use_block_editor_for_post_type', '__return_false', 100 );
add_filter( 'use_widgets_block_editor', '__return_false' );

add_filter(
	'tiny_mce_before_init',
	function ( $init ) {
		$init['wpautop']                = false;
		$init['convert_fonts_to_spans'] = false;
		$init['forced_root_block']      = false;

		// スキーマ検証を切る。<font> の中の <div> など、旧サイトには普通にある。
		$init['verify_html']             = false;
		$init['valid_elements']          = '*[*]';
		$init['extended_valid_elements'] = '*[*]';
		$init['valid_children']          = '+body[style],+font[div|p|table|ul|ol|h1|h2|h3|h4|h5|h6|center|img|br|a|b|span]';

		$init['remove_trailing_brs'] = false;
		$init['indent']              = false;
		$init['remove_linebreaks']   = false;
		$init['entity_encoding']     = 'raw';
		$init['convert_urls']        = false;
		$init['remove_script_host']  = false;

		$init['keep_styles']                  = true;
		$init['fix_list_elements']            = false;
		$init['end_container_on_empty_block'] = false;
		$init['allow_html_in_named_anchor']   = true;
		$init['allow_conditional_comments']   = true;
		$init['allow_unsafe_link_target']     = true;
		$init['element_format']               = 'html';

		// 設定では止まらない整形が4つ残る。読み込む前に細工して、保存する時に戻す。
		$setup = <<<'JS'
function( ed ) {
	var ONLYBR = /<p>((?:<br ?\/?>|\u00a0|\uFEFF| )*)<\/p>/g;
	var NONEMPTY = 'td,th,iframe,video,audio,object,script,pre,code,area,base,basefont,br,col,frame,hr,img,input,isindex,link,meta,param,embed,source,wbr,track';

	/* TinyMCE と同じ「空」の判定。空ブロックにはカーソル用の <br> を勝手に入れられる。 */
	var isEmptyForTinyMCE = function( el ) {
		if ( el.querySelector( NONEMPTY ) ) { return false; }
		if ( ! /^[ \t\r\n]*$/.test( el.textContent ) ) { return false; }
		if ( el.ownerDocument.createTreeWalker( el, 128 ).nextNode() ) { return false; }
		return ! el.querySelector( '[name],[data-mce-bookmark]' );
	};

	var prepare = function( html ) {
		try {
			var d = document.implementation.createHTMLDocument( '' );

			/* ブラウザに一度パースさせる。閉じ忘れの <p>/<font> を TinyMCE は違う位置で閉じて段落を割る。 */
			/* <![endif]> のような Word 由来の偽コメントも、ここで普通のコメントになる。 */
			d.body.innerHTML = html;

			/* コメントが入っていれば「空」と見なされないので <br> を入れられずに済む。 */
			var blocks = d.body.querySelectorAll( 'p,h1,h2,h3,h4,h5,h6,div' );
			for ( var i = 0; i < blocks.length; i++ ) {
				if ( isEmptyForTinyMCE( blocks[ i ] ) ) {
					blocks[ i ].insertBefore( d.createComment( 'tb-empty' ), blocks[ i ].firstChild );
				}
			}

			/* br.Apple-interchange-newline は無条件に消される。改行としては効いているので class を逃がす。 */
			var brs = d.body.querySelectorAll( 'br.Apple-interchange-newline' );
			for ( var j = 0; j < brs.length; j++ ) {
				brs[ j ].setAttribute( 'data-tb-class', brs[ j ].getAttribute( 'class' ) );
				brs[ j ].removeAttribute( 'class' );
			}
			return d.body.innerHTML;
		} catch ( err ) { return html; }
	};

	var restore = function( e ) {
		if ( ! e.content ) { return; }
		e.content = e.content
			.replace( /<!--tb-empty-->/g, '' )
			.replace( /<br([^>]*?)\sdata-tb-class="([^"]*)"/gi, '<br$1 class="$2"' );
	};

	ed.on( 'BeforeSetContent', function( e ) {
		if ( e.content ) { e.content = prepare( e.content ); }
	} );
	ed.on( 'PostProcess', restore );

	/* WP 側のプラグインが <br> だけの <p> を <p>&nbsp;</p> にする。属性を付けて正規表現から外し、後で剥がす。 */
	ed.on( 'SaveContent', function( e ) {
		restore( e );
		if ( e.content ) { e.content = e.content.replace( ONLYBR, '<p data-tb-keep="1">$1</p>' ); }
	} );
	ed.on( 'init', function() {
		ed.on( 'SaveContent', function( e ) {
			if ( e.content ) { e.content = e.content.replace( /<p data-tb-keep="1">/g, '<p>' ); }
		} );
	} );
}
JS;
		$init['setup'] = str_replace( array( "\r", "\n", "\t" ), ' ', $setup );
		return $init;
	},
	100
);

// 「テキスト」タブ側も wpautop を通さない。
add_filter(
	'wp_editor_settings',
	function ( $settings ) {
		$settings['wpautop'] = false;
		return $settings;
	}
);

// 文字色は既定で2段目にあるが背景色（マーカー）は出ていない。同じプラグインの機能なので並べる。
add_filter(
	'mce_buttons_2',
	function ( $buttons ) {
		if ( ! in_array( 'backcolor', $buttons, true ) ) {
			$i = array_search( 'forecolor', $buttons, true );
			if ( false !== $i ) {
				array_splice( $buttons, $i + 1, 0, 'backcolor' );
			} else {
				$buttons[] = 'backcolor';
			}
		}
		return $buttons;
	}
);

add_action(
	'admin_notices',
	function () {
		$screen = get_current_screen();
		if ( ! $screen || 'post' !== $screen->base ) {
			return;
		}
		echo '<div class="notice notice-info"><p>'
			. '<b>このページは元サイトのデザインをそのまま保持しています。</b> '
			. '文章の修正と写真の差し替え（写真をクリック → えんぴつ → 置換）は「ビジュアル」タブで行えます。'
			. 'レイアウトを動かしたい場合は「テキスト」タブは触らず、ご相談ください。'
			. '</p></div>';
	}
);
