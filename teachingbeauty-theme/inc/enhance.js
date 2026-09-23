/**
 * Teaching Beauty — 装飾・アニメーション強化
 * 本文を段落・要素ごとの単位に分け、画面に入った時点でフェードインさせる。
 * 初期表示（ファーストビュー）の文章は読み込み時点で自動的にフェードイン。
 */
( function () {
	'use strict';

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) { fn(); }
		else { document.addEventListener( 'DOMContentLoaded', fn ); }
	}

	/* 見出し・画像・表などはそのまま1単位、地の文は <br><br> で段落に割ってフェードイン対象にする。 */
	function buildChunks( root ) {
		var out = [];
		var nodes = Array.prototype.slice.call( root.childNodes );
		var buf = [];

		function isBlock( n ) {
			return n.nodeType === 1 &&
				/^(H1|H2|H3|H4|H5|HR|TABLE|SECTION|IMG|UL|OL|DL|DIV|IFRAME)$/.test( n.tagName );
		}
		function hasText( arr ) {
			return arr.some( function ( n ) {
				return ( n.nodeType === 3 && n.textContent.trim() ) ||
				       ( n.nodeType === 1 && n.tagName !== 'BR' );
			} );
		}
		function wrap( arr ) {
			if ( ! hasText( arr ) ) { return; }
			var w = document.createElement( 'div' );
			w.className = 'tb-rv';
			root.insertBefore( w, arr[0] );
			arr.forEach( function ( n ) { w.appendChild( n ); } );
			out.push( w );
		}
		function flush() {
			if ( ! buf.length ) { return; }
			// 連続する <br> が2つ以上続く箇所で段落に分割
			var para = [], brRun = 0;
			buf.forEach( function ( n ) {
				var isBr = ( n.nodeType === 1 && n.tagName === 'BR' );
				para.push( n );
				if ( isBr ) {
					brRun++;
					if ( brRun >= 2 ) { wrap( para ); para = []; brRun = 0; }
				} else if ( ! ( n.nodeType === 3 && ! n.textContent.trim() ) ) {
					brRun = 0;
				}
			} );
			wrap( para );
			buf = [];
		}

		nodes.forEach( function ( n ) {
			if ( isBlock( n ) ) {
				flush();
				n.classList.add( 'tb-rv' );
				out.push( n );
			} else {
				buf.push( n );
			}
		} );
		flush();
		return out;
	}

	/* 電話番号の保険。ふつうは CSS の 22px で収まる。書体の都合で溢れた時だけ実測して縮める。 */
	function fitSidebarTel() {
		try {
			var box = document.getElementById( 'shopinfo' );
			var a = box && box.querySelector( 'a[href^="tel:"]' );
			if ( ! a ) { return; }
			// 入れ子が逆のページがあるので <a> と中の <font> の両方に当てる
			var targets = [ a ].concat( Array.prototype.slice.call( a.querySelectorAll( 'font' ) ) );
			targets.forEach( function ( el ) { el.style.fontSize = ''; } );
			var cs = window.getComputedStyle( box );
			var avail = box.clientWidth - parseFloat( cs.paddingLeft || 0 ) - parseFloat( cs.paddingRight || 0 );
			var size = parseFloat( window.getComputedStyle( a ).fontSize );
			var guard = 0;
			while ( a.getBoundingClientRect().width > avail && size > 12 && guard++ < 24 ) {
				size -= 1;
				targets.forEach( function ( el ) { el.style.fontSize = size + 'px'; } );
			}
		} catch ( e ) { /* 測れない環境では何もしない */ }
	}

	ready( function () {
		var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		document.body.classList.add( 'tb-enhanced' );

		fitSidebarTel();
		window.addEventListener( 'load', fitSidebarTel );
		window.addEventListener( 'resize', fitSidebarTel );
		if ( document.fonts && document.fonts.ready && document.fonts.ready.then ) { document.fonts.ready.then( fitSidebarTel ); }

		try {
			var targets = [];
			var main = document.getElementById( 'hpb-main' );
			if ( main ) {
				var root = document.getElementById( 'toppage' ) || main;
				targets = buildChunks( root );
			}
			// タイトル画像・サイドバーの各ブロックも対象に
			Array.prototype.forEach.call(
				document.querySelectorAll( '#hpb-title, #hpb-aside #banner li, #hpb-aside #shopinfo' ),
				function ( el ) { el.classList.add( 'tb-rv' ); targets.push( el ); }
			);

			if ( ! reduce && 'IntersectionObserver' in window ) {
				var io = new IntersectionObserver( function ( entries ) {
					entries.forEach( function ( entry ) {
						if ( entry.isIntersecting ) {
							entry.target.classList.add( 'tb-in' );
							io.unobserve( entry.target );
						}
					} );
				}, { threshold: 0, rootMargin: '0px 0px -6% 0px' } );
				targets.forEach( function ( el ) { io.observe( el ); } );
			} else {
				targets.forEach( function ( el ) { el.classList.add( 'tb-in' ); } );
			}
		} catch ( e ) {
			// 失敗しても本文が隠れたままにならないように、全部表示しておく
			Array.prototype.forEach.call( document.querySelectorAll( '.tb-rv' ), function ( el ) {
				el.classList.add( 'tb-in' );
			} );
		}

		/* --- ページ先頭へ戻る フローティングボタン --- */
		var btn = document.createElement( 'button' );
		btn.type = 'button';
		btn.className = 'tb-totop';
		btn.setAttribute( 'aria-label', 'ページの先頭へ戻る' );
		btn.innerHTML = '▲';
		document.body.appendChild( btn );
		btn.addEventListener( 'click', function () {
			window.scrollTo( { top: 0, behavior: reduce ? 'auto' : 'smooth' } );
		} );
		var ticking = false;
		window.addEventListener( 'scroll', function () {
			if ( ticking ) { return; }
			ticking = true;
			window.requestAnimationFrame( function () {
				if ( window.pageYOffset > 480 ) { btn.classList.add( 'show' ); }
				else { btn.classList.remove( 'show' ); }
				ticking = false;
			} );
		}, { passive: true } );
	} );
}() );
