/**
 * Teaching Beauty — 装飾・アニメーション強化
 * レイアウトは変えず、スクロール演出と「先頭へ戻る」ボタンを付与。
 */
( function () {
	'use strict';

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) { fn(); }
		else { document.addEventListener( 'DOMContentLoaded', fn ); }
	}

	ready( function () {
		var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		document.body.classList.add( 'tb-enhanced' );

		/* --- スクロールで浮かび上がる演出 ---
		   本文はブロック単位でまとめてフェードイン（文章・画像を一体で。
		   一部だけ animate される不自然さを避ける）。 */
		var selector = '#hpb-title, #hpb-main, #hpb-aside #banner, #hpb-aside #shopinfo';
		var targets = Array.prototype.slice.call( document.querySelectorAll( selector ) );

		if ( ! reduce && 'IntersectionObserver' in window ) {
			var io = new IntersectionObserver( function ( entries ) {
				entries.forEach( function ( entry ) {
					if ( entry.isIntersecting ) {
						entry.target.classList.add( 'tb-in' );
						io.unobserve( entry.target );
					}
				} );
			}, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' } );

			targets.forEach( function ( el ) {
				el.classList.add( 'tb-reveal' );
				io.observe( el );
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
