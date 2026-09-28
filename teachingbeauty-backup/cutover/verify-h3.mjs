// トップページの見出しバーが、文字を帯の中にきちんと収めているかを確かめる。
//   node verify-h3.mjs https://www.teachingbeauty.jp
// 帯からはみ出していたら異常終了する。

import { chromium } from 'playwright';

const base = ( process.argv[ 2 ] || 'https://www.teachingbeauty.jp' ).replace( /\/$/, '' );
const WANT = [
	{ key: '不妊症施術', size: '32px', lines: 1 },
	{ key: 'の思い', size: '24px', lines: 1 },
];

const browser = await chromium.launch( { channel: 'chrome', headless: true } );
const page = await browser.newPage( { viewport: { width: 1280, height: 900 } } );
await page.goto( base + '/', { waitUntil: 'domcontentloaded', timeout: 60000 } );
await page.addStyleTag( { content: '*{animation:none!important;transition:none!important;opacity:1!important;transform:none!important}' } );
await page.waitForTimeout( 4000 );

const got = await page.evaluate( ( keys ) => {
	const out = {};
	for ( const k of keys ) {
		const h = [ ...document.querySelectorAll( '#hpb-main h3' ) ].find( ( x ) => x.textContent.includes( k ) );
		if ( ! h ) { out[ k ] = null; continue; }
		const hb = h.getBoundingClientRect();
		const rg = document.createRange();
		rg.selectNodeContents( h );
		const tb = rg.getBoundingClientRect();
		const f = h.querySelector( 'font' );
		out[ k ] = {
			size: f ? getComputedStyle( f ).fontSize : getComputedStyle( h ).fontSize,
			// 行数は rect の個数では数えられない。h3・font・文字ノードそれぞれに rect が出るので、
			// 1行でも2個3個になる。上端の座標が何種類あるかで数える。
			lines: new Set( [ ...rg.getClientRects() ].map( ( r ) => Math.round( r.top ) ) ).size,
			over: Math.round( tb.right - hb.right ) > 0 || Math.round( tb.bottom - hb.bottom ) > 0,
			bar: Math.round( hb.height ),
			width: Math.round( tb.width ),
			barWidth: Math.round( hb.width ),
		};
	}
	out.news = getComputedStyle( document.querySelector( '#toppage-news .en font' ) ).fontSize;
	return out;
}, WANT.map( ( w ) => w.key ) );

await browser.close();

let bad = 0;
for ( const w of WANT ) {
	const g = got[ w.key ];
	if ( ! g ) { console.log( `  NG   「${ w.key }」が見出しバーになっていない` ); bad++; continue; }
	if ( g.over ) { console.log( `  NG   「${ w.key }」の文字が帯からはみ出している` ); bad++; continue; }
	if ( g.size !== w.size ) { console.log( `  NG   「${ w.key }」の文字が ${ g.size }（${ w.size } のはず）` ); bad++; continue; }
	if ( g.lines !== w.lines ) { console.log( `  NG   「${ w.key }」が ${ g.lines } 行に折り返している` ); bad++; continue; }
	console.log( `  OK   「${ w.key }」 文字 ${ g.size } / 帯 ${ g.bar }px / ${ g.lines }行 / 文字幅 ${ g.width }px（帯 ${ g.barWidth }px）` );
}
console.log( `  （参考）新着情報の文字: ${ got.news }` );
process.exit( bad ? 1 : 0 );
