// iPhone で全39ページが画面に収まっているかを確かめる。
//   node verify-iphone.mjs https://www.teachingbeauty.jp
// レイアウトは 941px 固定なので、viewport に実寸を宣言して端末側に縮めてもらう。
// 宣言が画面幅のままだと 941px が収まらず、左右が切れる。

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { chromium, devices } from 'playwright';

const here = path.dirname( fileURLToPath( import.meta.url ) );
const base = ( process.argv[ 2 ] || 'https://www.teachingbeauty.jp' ).replace( /\/$/, '' );
const wxr = fs.readFileSync( path.join( here, '..', 'teachingbeauty-fullbody.wxr' ), 'utf8' );
const slugs = [ ...wxr.matchAll( /<wp:post_name><!\[CDATA\[([^\]]+)\]\]>/g ) ].map( ( m ) => m[ 1 ] );

const browser = await chromium.launch( { channel: 'chrome' } );
let bad = 0;

for ( const dev of [ 'iPhone 13', 'iPhone SE' ] ) {
	const ctx = await browser.newContext( { ...devices[ dev ] } );
	const page = await ctx.newPage();
	let ng = 0;

	for ( const slug of slugs ) {
		const url = base + ( 'home' === slug ? '/' : `/${ slug }.html` );
		await page.goto( url, { waitUntil: 'domcontentloaded', timeout: 60000 } );
		await page.waitForTimeout( 250 );
		const r = await page.evaluate( () => ( {
			screen: window.innerWidth,
			doc: document.documentElement.scrollWidth,
			meta: ( document.querySelector( 'meta[name="viewport"]' ) || {} ).content || '',
		} ) );
		// 端末が縮めてくれていれば innerWidth はレイアウト幅と同じになる。
		if ( r.doc > r.screen + 2 ) {
			console.log( `  NG   ${ dev } ${ slug }: ページ ${ r.doc }px が画面 ${ r.screen }px に収まらない` );
			ng++;
		}
		if ( ! /width=941/.test( r.meta ) ) {
			console.log( `  NG   ${ dev } ${ slug }: viewport の指定が違う（${ r.meta }）` );
			ng++;
		}
	}

	if ( ng ) { bad += ng; } else { console.log( `  OK   ${ dev } 全 ${ slugs.length } ページが画面に収まっている` ); }
	await ctx.close();
}

await browser.close();
process.exitCode = bad ? 1 : 0;
