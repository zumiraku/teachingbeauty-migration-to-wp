/**
 * モバイルメニューのトグル（アクセシブル）
 * Teaching Beauty theme
 */
( function () {
	'use strict';
	document.addEventListener( 'DOMContentLoaded', function () {
		var nav = document.getElementById( 'tb-nav' );
		if ( ! nav ) {
			return;
		}
		var toggle = nav.querySelector( '.tb-nav-toggle' );
		if ( ! toggle ) {
			return;
		}
		toggle.addEventListener( 'click', function () {
			var open = nav.classList.toggle( 'is-open' );
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		} );
	} );
}() );
