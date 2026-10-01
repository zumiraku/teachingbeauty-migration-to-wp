// スクロールした状態での重なりと、メニューが押せるかを全ページで確かめる。
//   node verify-scroll.mjs https://www.teachingbeauty.jp
//
// サイドバーを追従させている関係で、止まった状態では出ない不具合がある。
// 実際に貼り付いてから重なるため、必ずスクロールして見る。
// （突発性難聴のページで、店舗情報がバナーに重なる不具合を見落とした反省）

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { chromium, devices } from 'playwright';

const here = path.dirname( fileURLToPath( import.meta.url ) );
const base = ( process.argv[ 2 ] || 'https://www.teachingbeauty.jp' ).replace( /\/$/, '' );
const wxr = fs.readFileSync( path.join( here, '..', 'teachingbeauty-fullbody.wxr' ), 'utf8' );
const slugs = [ ...wxr.matchAll( /<wp:post_name><!\[CDATA\[([^\]]+)\]\]>/g ) ].map( ( m ) => m[ 1 ] );

const CHECK = ( positions ) => {
	const out = [];
	const hit = ( a, c ) => a && c && ! ( c.top >= a.bottom || a.top >= c.bottom || c.left >= a.right || a.left >= c.right );
	const ban = document.querySelector( '#banner' );
	const shop = document.querySelector( '#shopinfo' );
	const main = document.querySelector( '#hpb-main' );
	// 片方がもう片方の中に入っている場合は、箱としては必ず重なるが
	// 見た目は重なっていない。入れ子は除く。
	// （不妊症のページは #shopinfo が #banner の中にあり、誤検出していた）
	const nested = ( a, c ) => a && c && ( a.contains( c ) || c.contains( a ) );
	if ( ban && shop && ! nested( ban, shop ) && hit( ban.getBoundingClientRect(), shop.getBoundingClientRect() ) ) {
		out.push( 'バナーと店舗情報が重なる' );
	}
	// 入れ子のページでは、バナーの一覧そのものと店舗情報を比べる
	const list = document.querySelector( '#banner ul' );
	if ( list && shop && ! nested( list, shop ) && hit( list.getBoundingClientRect(), shop.getBoundingClientRect() ) ) {
		out.push( 'バナー一覧と店舗情報が重なる' );
	}
	for ( const s of document.querySelectorAll( '#hpb-aside' ) ) {
		if ( main && ! nested( main, s ) && hit( main.getBoundingClientRect(), s.getBoundingClientRect() ) ) {
			out.push( 'サイドバーが本文に重なる' );
		}
	}
	for ( const a of document.querySelectorAll( '#hpb-nav li a' ) ) {
		const holder = a.closest( '#hpb-nav' );
		if ( holder && parseInt( getComputedStyle( holder ).zIndex, 10 ) < 0 ) { continue; }
		const bb = a.getBoundingClientRect();
		if ( bb.width < 2 || bb.bottom < 0 || bb.top > window.innerHeight ) { continue; }
		const t = document.elementFromPoint( Math.round( bb.left + bb.width / 2 ), Math.round( bb.top + bb.height / 2 ) );
		const h = t ? t.closest( 'a' ) : null;
		if ( ! h || h.getAttribute( 'href' ) !== a.getAttribute( 'href' ) ) {
			out.push( `メニュー「${ a.textContent.replace( /\s+/g, ' ' ).trim().slice( 0, 8 ) }」が押せない` );
		}
	}
	return [ ...new Set( out ) ];
};

const browser = await chromium.launch( { channel: 'chrome' } );
const problems = [];
let checks = 0;

for ( const [ devName, opt ] of [ [ 'パソコン', { viewport: { width: 1280, height: 900 } } ], [ 'スマホ', { ...devices[ 'iPhone 13' ] } ] ] ) {
	const ctx = await browser.newContext( opt );
	const page = await ctx.newPage();
	page.setDefaultTimeout( 30000 );

	for ( const slug of slugs ) {
		const url = base + ( 'home' === slug ? '/' : `/${ slug }.html` );
		try {
			await page.goto( url, { waitUntil: 'domcontentloaded', timeout: 30000 } );
		} catch ( e ) {
			problems.push( `${ devName } ${ slug }: ページが開けない` );
			continue;
		}
		await page.waitForTimeout( 200 );

		for ( const frac of [ 0, 0.4, 0.85 ] ) {
			await page.evaluate( ( f ) => {
				const H = document.body.scrollHeight;
				window.scrollTo( 0, Math.max( 0, ( H - window.innerHeight ) * f ) );
			}, frac );
			await page.waitForTimeout( 170 );
			const r = await page.evaluate( CHECK );
			checks++;
			for ( const x of r ) { problems.push( `${ devName } ${ slug } (${ Math.round( frac * 100 ) }%): ${ x }` ); }
		}
	}
	await ctx.close();
}
await browser.close();

console.log( `確認した回数: ${ checks }（全 ${ slugs.length } ページ × 2端末 × 3か所）` );
const uniq = [ ...new Set( problems ) ];
if ( uniq.length ) {
	console.log( `  NG   ${ uniq.length } 件` );
	uniq.slice( 0, 20 ).forEach( ( x ) => console.log( '        ' + x ) );
	if ( uniq.length > 20 ) { console.log( `        …ほか ${ uniq.length - 20 } 件` ); }
} else {
	console.log( '  OK   重なり・押せないメニュー ともに無し' );
}
process.exitCode = uniq.length ? 1 : 0;
