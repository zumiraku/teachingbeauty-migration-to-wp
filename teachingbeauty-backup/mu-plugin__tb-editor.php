<?php
/**
 * Plugin Name: TB Editor — 原本HTMLを壊さない編集環境
 * Description: 固定ページ・投稿をクラシックエディターで開き、TinyMCE が元サイト（ホームページビルダー製）のHTMLを整形・改変しないようにする。写真の差し替え（画像クリック → 鉛筆 → 置換）と文章の修正を、レイアウトを崩さずに行える。
 * Version: 1.0.0
 *
 * 設置先: wp-content/mu-plugins/tb-editor.php
 *
 * 背景（validation/EDITOR-EDIT-RISK.md）:
 *   標準設定の TinyMCE は保存時に (1) <p>/<br> を剥がし表示時の wpautop に委ねる、(2) <font> を <span> に変換する。
 *   本テーマは原本忠実のため wpautop を使わず本文をそのまま出力するので、(1)(2) によりレイアウトが崩れる。
 *   → 下記の設定で TinyMCE を「見たままを、そのまま保存する」モードにする。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 1) ブロックエディターを使わず、クラシックエディター（従来の編集画面）を使う。
 *    ブロックエディターの「クラシック」ブロックは小さなモーダル内で「メディアを追加」も無く、編集に向かない。
 */
add_filter( 'use_block_editor_for_post_type', '__return_false', 100 );
add_filter( 'use_widgets_block_editor', '__return_false' );

/**
 * 2) TinyMCE を非破壊にする。
 */
add_filter(
	'tiny_mce_before_init',
	function ( $init ) {
		// 保存時に <p>/<br> を剥がさない（表示側で wpautop を使わないため、剥がされると戻らない）
		$init['wpautop'] = false;
		// <font size/color> を <span style> に変換しない
		$init['convert_fonts_to_spans'] = false;
		// 裸のテキストや <br> 区切りの文章を <p> で包まない
		$init['forced_root_block'] = false;
		// スキーマ検証による要素の削除・並べ替えをしない（<font> の中の <div> なども原本どおり）
		$init['verify_html']             = false;
		$init['valid_elements']          = '*[*]';
		$init['extended_valid_elements'] = '*[*]';
		$init['valid_children']          = '+body[style],+font[div|p|table|ul|ol|h1|h2|h3|h4|h5|h6|center|img|br|a|b|span]';
		// 改行・空白・末尾 <br> を整えない
		$init['remove_trailing_brs'] = false;
		$init['indent']              = false;
		$init['remove_linebreaks']   = false;
		// 文字実体参照・URL を書き換えない
		$init['entity_encoding']    = 'raw';
		$init['convert_urls']       = false;
		$init['remove_script_host'] = false;
		// その他の自動整形を止める
		$init['keep_styles']                   = true;
		$init['fix_list_elements']             = false;
		$init['end_container_on_empty_block']  = false;
		$init['allow_html_in_named_anchor']    = true;
		$init['allow_conditional_comments']    = true;
		$init['allow_unsafe_link_target']      = true;
		$init['element_format']                = 'html';

		/*
		 * 設定では止められない改変を、読込時に目印を付け保存時に戻すことで無効化する
		 * （すべて全39ページの実機検証で見つかったもの。validation/EDITING-VERIFICATION.md）。
		 *
		 * (0) 読込前に、ブラウザ自身のパーサーで一度正規化（DOM→HTML）してから TinyMCE に渡す。
		 *     閉じ忘れの <p>/<font>（ホームページビルダー由来）を TinyMCE はブラウザと違う位置で閉じ、
		 *     段落が分割されて余白が増える。「実際に描画されているとおりの構造」を渡せばこれが起きない。
		 *     <![endif]> や <!> のような偽コメント（Word 貼り付けの残骸）も、この段階で通常のコメントになる。
		 * (1) TinyMCE 本体は、空の <p>/<h1〜h6>/<div> に読込時 <br> を差し込む（カーソルを置くため）。
		 *     「コメントを含む要素は空とみなさない」仕様なので、空ブロックへ目印コメントを入れて防ぐ。
		 *     空の判定は TinyMCE と同じ規則（中身のある要素・空白以外の文字・コメントが無い）で行う。
		 * (3) WordPress 側の TinyMCE プラグインは、<br> だけの <p>（空行のスペーサー）を保存時に
		 *     <p>&nbsp;</p> に書き換え、2行分の余白が1行になる。属性を付けて正規表現から外し、後で外す。
		 * (4) TinyMCE 本体は <br class="Apple-interchange-newline">（Safari/Chrome からの貼り付け残骸だが、
		 *     ブラウザは普通の <br> として改行を描画する）を無条件に削除する。class 名を退避して防ぐ。
		 */
		$setup = <<<'JS'
function( ed ) {
	var ONLYBR = /<p>((?:<br ?\/?>|\u00a0|\uFEFF| )*)<\/p>/g;
	/* TinyMCE が「中身あり」とみなす要素（TinyMCE の nonEmptyElements と同じ） */
	var NONEMPTY = 'td,th,iframe,video,audio,object,script,pre,code,area,base,basefont,br,col,frame,hr,img,input,isindex,link,meta,param,embed,source,wbr,track';
	var isEmptyForTinyMCE = function( el ) {
		if ( el.querySelector( NONEMPTY ) ) { return false; }
		if ( ! /^[ \t\r\n]*$/.test( el.textContent ) ) { return false; }
		var w = el.ownerDocument.createTreeWalker( el, 128 /* comments */ );
		if ( w.nextNode() ) { return false; }
		var named = el.querySelector( '[name],[data-mce-bookmark]' );
		return ! named;
	};
	var prepare = function( html ) {
		try {
			var d = document.implementation.createHTMLDocument( '' );
			d.body.innerHTML = html;
			/* (1) 空ブロックに目印コメント → TinyMCE が <br> を差し込まない */
			var blocks = d.body.querySelectorAll( 'p,h1,h2,h3,h4,h5,h6,div' );
			for ( var i = 0; i < blocks.length; i++ ) {
				if ( isEmptyForTinyMCE( blocks[ i ] ) ) {
					blocks[ i ].insertBefore( d.createComment( 'tb-empty' ), blocks[ i ].firstChild );
				}
			}
			/* (4) <br class="Apple-interchange-newline"> を TinyMCE に消されないよう class 名を退避 */
			var brs = d.body.querySelectorAll( 'br.Apple-interchange-newline' );
			for ( var j = 0; j < brs.length; j++ ) {
				brs[ j ].setAttribute( 'data-tb-class', brs[ j ].getAttribute( 'class' ) );
				brs[ j ].removeAttribute( 'class' );
			}
			return d.body.innerHTML;
		} catch ( err ) { return html; }
	};
	ed.on( 'BeforeSetContent', function( e ) {
		if ( e.content ) { e.content = prepare( e.content ); }
	} );
	var restore = function( e ) {
		if ( ! e.content ) { return; }
		e.content = e.content
			.replace( /<!--tb-empty-->/g, '' )
			.replace( /<br([^>]*?)\sdata-tb-class="([^"]*)"/gi, '<br$1 class="$2"' );
	};
	ed.on( 'PostProcess', restore );
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

/**
 * 3) 「テキスト」タブ（生HTML）も wpautop 変換なしで往復させる。
 */
add_filter(
	'wp_editor_settings',
	function ( $settings ) {
		$settings['wpautop'] = false;
		return $settings;
	}
);

/**
 * 3.5) ツールバーに「マーカー（背景色）」ボタンを追加。文字色（forecolor）は標準で2段目にあるが、
 *      背景色（backcolor）は WordPress 標準では出ていない。同じ textcolor プラグインの機能なので追加するだけ。
 *      出力は <span style="background-color: …"> で、原本の <font color> と同様にそのまま保存される。
 */
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

/**
 * 4) 編集画面の案内（クライアント向け）。
 */
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
