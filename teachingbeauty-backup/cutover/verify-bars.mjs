// 見出しバーの文字が、バーの中で同じ位置・同じ大きさに収まっているかを全ページで見る。
//   例: node verify-bars.mjs https://www.teachingbeauty.jp
// 旧サイトには </h3> を閉じ忘れて本文まで飲み込んでいる見出しがある。
// それ自体は元からなので直さないが、1行目の文字の位置が他とズレていないかだけ確かめる。

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { chromium } from 'playwright';

const here = path.dirname( fileURLToPath( import.meta.url ) );
const base = ( process.argv[ 2 ] || 'https://www.teachingbeauty.jp' ).replace( /\/$/, '' );
const wxr = fs.readFileSync( path.join( here, '..', 'teachingbeauty-fullbody.wxr' ), 'utf8' );
const slugs = [ ...wxr.matchAll( /<wp:post_name><!\[CDATA\[([^\]]+)\]\]>/g ) ].map( ( m ) => m[ 1 ] );

const browser = await chromium.launch( { channel: 'chrome', headless: true } );
const page = await browser.newPage( { viewport: { width: 1280, height: 900 } } );

let checked = 0;
let bad = 0;

for ( const slug of slugs ) {
	const url = base + ( slug === 'home' ? '/' : `/${ slug }.html` );
	await page.goto( url, { waitUntil: 'load' } );
	const bars = await page.evaluate( () => {
		const out = [];
		for ( const h of document.querySelectorAll( '#hpb-main h3' ) ) {
			const walker = document.createTreeWalker( h, NodeFilter.SHOW_TEXT );
			let n;
			let first = null;
			while ( ( n = walker.nextNode() ) ) {
				if ( n.nodeValue.trim() ) { first = n; break; }
			}
			if ( ! first ) { continue; }
			const hb = h.getBoundingClientRect();
			const rg = document.createRange();
			rg.selectNode( first );
			out.push( {
				t: first.nodeValue.trim().slice( 0, 16 ),
				top: Math.round( rg.getBoundingClientRect().top - hb.top ),
				size: getComputedStyle( first.parentElement ).fontSize,
			} );
		}
		return out;
	} );

	for ( const b of bars ) {
		checked++;
		if ( b.top !== 5 ) {
			console.log( `  NG   ${ slug }: 「${ b.t }」の文字が +${ b.top }px（他は +5px）` );
			bad++;
		}
	}
}

await browser.close();

console.log( '' );
console.log( bad
	? `見出し ${ checked } 本中 ${ bad } 本の位置がズレています。`
	: `見出し ${ checked } 本すべて、文字がバーの同じ位置に収まっています。` );
// 一覧を出すのが目的なので、ズレがあっても異常終了はしない。
// 直した箇所が直っているかは、呼び出し側で名前を見て判断する。
