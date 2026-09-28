// 見出しバーが「新着情報」と同じ見え方になっているかを確かめる。
//   node verify-bars2.mjs https://www.teachingbeauty.jp
//
// 見るところは3つ。
//   1. 文字の大きさと色が、新着情報（24px / rgb(72,10,23)）と同じか
//   2. 文字が帯の絵（indexBg_5H.png = 690x33）の中に収まっているか
//   3. 見出しが本文を飲み込んでいないか（飲み込むと本文が 12px 右にズレる）

import { chromium } from 'playwright';

const base = ( process.argv[ 2 ] || 'https://www.teachingbeauty.jp' ).replace( /\/$/, '' );
const BAR_H = 33;            // 帯の絵の高さ
const WANT_SIZE = '24px';
const WANT_COLOR = 'rgb(72, 10, 23)';

const CHECKS = [
	{ path: '/', key: '不妊症施術', style: true },
	{ path: '/', key: 'の思い', style: true },
	{ path: '/muryou.html', key: '無料相談', style: true, flush: true },
	{ path: '/beauty.html', key: '交通事故施術', style: true, flush: true },
];

const browser = await chromium.launch( { channel: 'chrome', headless: true } );
const page = await browser.newPage( { viewport: { width: 1280, height: 900 } } );
let bad = 0;

for ( const c of CHECKS ) {
	await page.goto( base + c.path, { waitUntil: 'domcontentloaded', timeout: 60000 } );
	await page.addStyleTag( { content: '*{animation:none!important;transition:none!important;opacity:1!important;transform:none!important}' } );
	await page.waitForTimeout( 2000 );

	const g = await page.evaluate( ( key ) => {
		const h = [ ...document.querySelectorAll( '#hpb-main h3' ) ].find( ( x ) => x.textContent.includes( key ) );
		if ( ! h ) { return null; }
		const hb = h.getBoundingClientRect();
		const main = document.querySelector( '#hpb-main' ).getBoundingClientRect();
		const f = h.querySelector( 'font' ) || h;
		const cs = getComputedStyle( f );
		const rg = document.createRange();
		rg.selectNodeContents( h );
		const tb = rg.getBoundingClientRect();
		// 見出しのすぐ後ろに続く本文が、本文の左端から始まっているか
		let next = h.nextSibling, left = null;
		while ( next && null === left ) {
			if ( 3 === next.nodeType && next.nodeValue.trim() ) {
				const r = document.createRange();
				r.selectNode( next );
				left = Math.round( r.getBoundingClientRect().left - main.left );
			} else if ( 1 === next.nodeType && next.textContent.trim() ) {
				left = Math.round( next.getBoundingClientRect().left - main.left );
			}
			next = next.nextSibling;
		}
		return {
			size: cs.fontSize, color: cs.color,
			barH: Math.round( hb.height ),
			textBottom: Math.round( tb.bottom - hb.top ),
			nextLeft: left,
		};
	}, c.key );

	if ( ! g ) { console.log( `  NG   ${ c.path } の「${ c.key }」が見出しになっていない` ); bad++; continue; }

	const errs = [];
	if ( c.style && g.size !== WANT_SIZE ) { errs.push( `文字が ${ g.size }（${ WANT_SIZE } のはず）` ); }
	if ( c.style && g.color !== WANT_COLOR ) { errs.push( `色が ${ g.color }（${ WANT_COLOR } のはず）` ); }
	if ( g.textBottom > BAR_H ) { errs.push( `文字が帯からはみ出す（下端 ${ g.textBottom }px > 帯 ${ BAR_H }px）` ); }
	if ( c.flush && null !== g.nextLeft && 0 !== g.nextLeft ) { errs.push( `続く本文が ${ g.nextLeft }px ズレている` ); }

	if ( errs.length ) {
		console.log( `  NG   ${ c.key }: ${ errs.join( ' / ' ) }` );
		bad++;
	} else {
		console.log( `  OK   ${ c.key } ${ g.size } ${ g.color } / 帯 ${ g.barH }px / 文字の下端 ${ g.textBottom }px${ c.flush ? ` / 本文 ${ g.nextLeft }px` : '' }` );
	}
}

await browser.close();
process.exitCode = bad ? 1 : 0;
