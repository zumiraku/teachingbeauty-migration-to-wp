// 全39ページの見出しバーを、パソコンとスマホの両方で1本ずつ調べる。
//   node audit-bars.mjs https://www.teachingbeauty.jp
//
// 見るところ:
//   1. 帯の絵が出ているか
//   2. 帯の左端と幅が他と揃っているか（本文の左0・幅698が基準）
//   3. 文字が帯の右へはみ出していないか
//   4. 文字が折り返していないか
//   5. 文字の中心が帯の目印（13.5px）に乗っているか
//   6. 見出しが本文を飲み込んでいないか
//
// 元サイト由来で直しようがないものは KNOWN に入れて、報告はするが失敗にしない。

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { chromium, devices } from 'playwright';

const here = path.dirname( fileURLToPath( import.meta.url ) );
const base = ( process.argv[ 2 ] || 'https://www.teachingbeauty.jp' ).replace( /\/$/, '' );
const wxr = fs.readFileSync( path.join( here, '..', 'teachingbeauty-fullbody.wxr' ), 'utf8' );
const slugs = [ ...wxr.matchAll( /<wp:post_name><!\[CDATA\[([^\]]+)\]\]>/g ) ].map( ( m ) => m[ 1 ] );

const TICK_C = 13.5;   // 帯の絵の目印の中心
const BAR_IMG = 'indexBg_5H.png';

// 元サイトの作りで、直すと元の意匠が壊れるもの。報告はするが失敗にはしない。
//   news.html の #coupon … クーポン枠の見出し。元から帯が無いデザイン
//   長いタイトルの折り返し … 文字数の問題で、詰めるには文章を変えるしかない
const KNOWN_NO_BAR = [ 'news' ];

const browser = await chromium.launch( { channel: 'chrome' } );
const rows = [];

for ( const [ devName, opt ] of [ [ 'パソコン', { viewport: { width: 1280, height: 900 } } ], [ 'スマホ', { ...devices[ 'iPhone 13' ] } ] ] ) {
	const ctx = await browser.newContext( opt );
	const page = await ctx.newPage();

	for ( const slug of slugs ) {
		const url = base + ( 'home' === slug ? '/' : `/${ slug }.html` );
		await page.goto( url, { waitUntil: 'domcontentloaded', timeout: 60000 } );
		await page.addStyleTag( { content: '*{animation:none!important;transition:none!important;opacity:1!important;transform:none!important}' } );
		await page.waitForTimeout( 320 );

		const found = await page.evaluate( ( BAR_IMG ) => {
			const main = document.querySelector( '#hpb-main' );
			if ( ! main ) { return []; }
			const mb = main.getBoundingClientRect();
			const out = [];
			for ( const h of main.querySelectorAll( 'h3' ) ) {
				if ( h.classList.contains( 'hpb-c-index' ) ) { continue; }
				const cs = getComputedStyle( h );
				const hb = h.getBoundingClientRect();
				const rg = document.createRange();
				rg.selectNodeContents( h );
				// 行数は文字そのものの箱で数える。<strong> などの要素は自前の rect を
				// 別の高さで返すので、要素の rect を数えると1行でも2行に見えてしまう。
				const rects = [];
				const tw = document.createTreeWalker( h, NodeFilter.SHOW_TEXT );
				let tn;
				while ( ( tn = tw.nextNode() ) ) {
					if ( ! tn.nodeValue.trim() ) { continue; }
					const r2 = document.createRange();
					r2.selectNode( tn );
					for ( const rr of r2.getClientRects() ) {
						if ( rr.height >= 4 && rr.width >= 2 ) { rects.push( rr ); }
					}
				}
				const tb = rg.getBoundingClientRect();
				const tops = new Set( rects.map( ( r ) => Math.round( r.top ) ) );
				const pb = h.parentElement.getBoundingClientRect();
				const pcs = getComputedStyle( h.parentElement );
				const padL = parseFloat( pcs.paddingLeft ) || 0;
				const padR = parseFloat( pcs.paddingRight ) || 0;
				const f = h.querySelector( 'font' );
				out.push( {
					t: h.textContent.trim().replace( /\s+/g, ' ' ).slice( 0, 18 ),
					帯あり: cs.backgroundImage.includes( BAR_IMG ),
					左: Math.round( hb.left - pb.left - padL ),
					幅: Math.round( hb.width ),
					本文幅: Math.round( pb.width - padL - padR ),
					主本文からの左: Math.round( hb.left - mb.left ),
					高さ: Math.round( hb.height ),
					右はみ出し: Math.round( tb.right - hb.right ),
					行数: tops.size,
					中心: +( ( ( tb.top + tb.bottom ) / 2 - hb.top ).toFixed( 1 ) ),
					大きさ: f ? getComputedStyle( f ).fontSize : cs.fontSize,
					飲み込み: Math.round( hb.height ) > 60,
				} );
			}
			return out;
		}, BAR_IMG );

		for ( const b of found ) { rows.push( { dev: devName, slug, ...b } ); }
	}
	await ctx.close();
}
await browser.close();

// ---- 集計 ----
const problems = { 帯なし: [], 左ずれ: [], 幅ちがい: [], 右はみ出し: [], 折り返し: [], 中心ずれ: [], 飲み込み: [] };

for ( const r of rows ) {
	const id = `${ r.dev } ${ r.slug }「${ r.t }」`;
	if ( ! r.帯あり && ! KNOWN_NO_BAR.includes( r.slug ) ) { problems.帯なし.push( id ); }
	if ( 0 !== r.左 && ! KNOWN_NO_BAR.includes( r.slug ) ) { problems.左ずれ.push( `${ id } 左${ r.左 }px（本文からは ${ r.主本文からの左 }px）` ); }
	if ( r.幅 !== r.本文幅 && ! KNOWN_NO_BAR.includes( r.slug ) ) { problems.幅ちがい.push( `${ id } 幅${ r.幅 }（本文${ r.本文幅 }）` ); }
	if ( r.右はみ出し > 0 ) { problems.右はみ出し.push( `${ id } +${ r.右はみ出し }px` ); }
	if ( r.行数 > 1 && ! r.飲み込み ) { problems.折り返し.push( `${ id } ${ r.行数 }行` ); }
	if ( ! r.飲み込み && 1 === r.行数 && ! KNOWN_NO_BAR.includes( r.slug ) && Math.abs( r.中心 - TICK_C ) > 1.5 ) { problems.中心ずれ.push( `${ id } 中心${ r.中心 }` ); }
	if ( r.飲み込み ) { problems.飲み込み.push( `${ id } 高さ${ r.高さ }px` ); }
}

console.log( `見出しバー 合計 ${ rows.length } 本（パソコン＋スマホ、全 ${ slugs.length } ページ）\n` );
let bad = 0;
for ( const [ name, list ] of Object.entries( problems ) ) {
	if ( ! list.length ) { console.log( `  OK   ${ name }: なし` ); continue; }
	// 飲み込みは元サイト由来。報告だけして失敗にはしない。
	const known = '飲み込み' === name;
	console.log( `  ${ known ? '--' : 'NG' }   ${ name }: ${ list.length } 件${ known ? '（元サイト由来・別件）' : '' }` );
	for ( const x of list.slice( 0, 12 ) ) { console.log( `        ${ x }` ); }
	if ( list.length > 12 ) { console.log( `        …ほか ${ list.length - 12 } 件` ); }
	if ( ! known ) { bad += list.length; }
}

console.log( bad ? `\n直すべき問題 ${ bad } 件` : '\n直すべき問題はありません' );
process.exitCode = bad ? 1 : 0;
