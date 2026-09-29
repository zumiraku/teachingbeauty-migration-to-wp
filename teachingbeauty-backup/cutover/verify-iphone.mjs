// iPhone で全39ページが画面に収まっているかを確かめる。
//   node verify-iphone.mjs https://www.teachingbeauty.jp
// レイアウトは 941px 固定なので、viewport に実寸＋余白を宣言して端末側に縮めてもらう。
// 宣言が画面幅のままだと 941px が収まらず左右が切れる。
// ちょうど 941 だと中身が画面の端に貼りつくので、左右 12px ぶんを足した 965 にする。

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
			gutter: Math.round( ( document.querySelector( '#hpb-inner' ) || document.body ).getBoundingClientRect().left ),
		} ) );
		// 端末が縮めてくれていれば innerWidth はレイアウト幅と同じになる。
		if ( r.doc > r.screen + 2 ) {
			console.log( `  NG   ${ dev } ${ slug }: ページ ${ r.doc }px が画面 ${ r.screen }px に収まらない` );
			ng++;
		}
		if ( ! /width=965/.test( r.meta ) ) {
			console.log( `  NG   ${ dev } ${ slug }: viewport の指定が違う（${ r.meta }）` );
			ng++;
		}
		if ( r.gutter < 8 ) {
			console.log( `  NG   ${ dev } ${ slug }: 左の余白が ${ r.gutter }px しかない（端に貼りついている）` );
			ng++;
		}
	}


	// サイドバーがスクロールに追従するか（トップページで実際に動かして見る）
	// サイドバーの親はページによって #hpb-inner だったり #hpb-wrapper だったりする。
	// 後者のほうが多い（29ページ）ので、そちらを代表に taiban で確かめる。
	await page.goto( base + '/taiban.html', { waitUntil: 'domcontentloaded', timeout: 60000 } );
	await page.waitForTimeout( 1200 );
	const pos = await page.evaluate( () => getComputedStyle( document.querySelector( '#hpb-aside' ) ).position );
	await page.evaluate( () => window.scrollTo( 0, 2600 ) );
	await page.waitForTimeout( 400 );
	const stuck = await page.evaluate( () => {
		const r = document.querySelector( '#hpb-aside' ).getBoundingClientRect();
		return { top: Math.round( r.top ), 見えている: r.bottom > 0 && r.top < window.innerHeight };
	} );
	if ( 'sticky' !== pos || ! stuck.見えている ) {
		console.log( `  NG   ${ dev } サイドバーが追従しない（position=${ pos } / スクロール後 top=${ stuck.top }）` );
		ng++;
	} else {
		console.log( `  OK   ${ dev } サイドバーが追従する（2600px スクロール後も top=${ stuck.top }）` );
	}
	if ( ng ) { bad += ng; } else { console.log( `  OK   ${ dev } 全 ${ slugs.length } ページが画面に収まり、左右に余白がある` ); }
	await ctx.close();
}

await browser.close();
process.exitCode = bad ? 1 : 0;
