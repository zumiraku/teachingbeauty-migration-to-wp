// 39 ページを「元サイト（静的ミラー）」と「WordPress」で撮影して比較する
//   node compare-original.mjs <origBase> <wpBase> [--no-enhance] [--out=DIR] [slug ...]
//   --no-enhance: WordPress 側で enhance.css/js を読み込まずに撮影（装飾抜きで原本との一致を見る）
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';
import { PNG } from 'pngjs';
import pixelmatch from 'pixelmatch';

const args = process.argv.slice( 2 );
const [ origBase, wpBase ] = args.filter( a => ! a.startsWith( '--' ) ).slice( 0, 2 ).map( a => a.replace( /\/$/, '' ) );
const only = args.filter( a => ! a.startsWith( '--' ) ).slice( 2 );
const noEnhance = args.includes( '--no-enhance' );
const cssOverride = ( args.find( a => a.startsWith( '--enhance-css=' ) ) || '' ).split( '=' )[ 1 ];
const cssBody = cssOverride ? fs.readFileSync( cssOverride, 'utf8' ) : null;
const outDir = ( args.find( a => a.startsWith( '--out=' ) ) || '--out=./compare-out' ).split( '=' )[ 1 ];
fs.mkdirSync( outDir, { recursive: true } );
const here = path.dirname( fileURLToPath( import.meta.url ) );
const wxr = fs.readFileSync( path.join( here, '..', 'teachingbeauty-fullbody.wxr' ), 'utf8' );
const slugs = [ ...wxr.matchAll( /<wp:post_name><!\[CDATA\[([^\]]+)\]\]>/g ) ].map( m => m[ 1 ] ).filter( s => ! only.length || only.includes( s ) );

const browser = await chromium.launch( { channel: 'chrome', headless: true } );
async function shot( base, slug, out, blockEnhance ) {
	const ctx = await browser.newContext( { viewport: { width: 1100, height: 900 }, reducedMotion: 'reduce', locale: 'ja-JP' } );
	await ctx.route( '**/*', ( r ) => {
		const u = r.request().url();
		if ( blockEnhance && /\/enhance\.(css|js)(\?|$)/.test( u ) ) return r.abort();
		if ( cssBody && /\/enhance\.css(\?|$)/.test( u ) ) return r.fulfill( { status: 200, contentType: 'text/css; charset=utf-8', body: cssBody } );
		return /^https?:\/\/(127\.0\.0\.1|www\.teachingbeauty\.jp)/.test( u ) ? r.continue() : r.abort();
	} );
	const page = await ctx.newPage();
	await page.goto( `${ base }/${ slug === 'home' ? '' : slug + '.html' }?n=${ Date.now() }`, { waitUntil: 'load', timeout: 60000 } );
	await page.waitForTimeout( 1200 );
	// 遅延フェードインをすべて表示済みにしてから撮影
	await page.evaluate( () => { document.querySelectorAll( '.tb-rv' ).forEach( e => e.classList.add( 'tb-in' ) ); } );
	await page.waitForTimeout( 700 );
	await page.screenshot( { path: out, fullPage: true } );
	const h = await page.evaluate( () => document.documentElement.scrollHeight );
	const sw = await page.evaluate( () => document.documentElement.scrollWidth );
	await ctx.close();
	return { h, sw };
}
function diff( a, b, out ) {
	const A = PNG.sync.read( fs.readFileSync( a ) ), B = PNG.sync.read( fs.readFileSync( b ) );
	const w = Math.min( A.width, B.width ), h = Math.min( A.height, B.height );
	const crop = ( img ) => { const o = new PNG( { width: w, height: h } ); PNG.bitblt( img, o, 0, 0, w, h, 0, 0 ); return o; };
	const D = new PNG( { width: w, height: h } );
	const n = pixelmatch( crop( A ).data, crop( B ).data, D.data, w, h, { threshold: 0.12 } );
	if ( out ) fs.writeFileSync( out, PNG.sync.write( D ) );
	let minY = h, maxY = 0;
	for ( let y = 0; y < h; y++ ) for ( let x = 0; x < w; x++ ) { const i = ( y * w + x ) * 4; if ( D.data[ i ] === 255 && D.data[ i + 1 ] === 0 ) { if ( y < minY ) minY = y; if ( y > maxY ) maxY = y; } }
	return { n, pct: ( 100 * n / ( w * h ) ).toFixed( 2 ), minY, maxY };
}
const rows = [];
for ( const slug of slugs ) {
	const o = path.join( outDir, `orig-${ slug }.png` ), w = path.join( outDir, `wp${ noEnhance ? '-noenh' : '' }-${ slug }.png` );
	const ro = await shot( origBase, slug, o, false );
	const rw = await shot( wpBase, slug, w, noEnhance );
	const d = diff( o, w, path.join( outDir, `diff${ noEnhance ? '-noenh' : '' }-${ slug }.png` ) );
	const row = { slug, origH: ro.h, wpH: rw.h, wpScrollW: rw.sw, diffPct: d.pct, firstDiffY: d.n ? d.minY : -1 };
	rows.push( row );
	console.log( `${ slug.padEnd( 14 ) } 原本 ${ String( ro.h ).padStart( 5 ) }px / WP ${ String( rw.h ).padStart( 5 ) }px (幅 ${ rw.sw })  差 ${ d.pct }%${ d.n ? '  最初の差 y=' + d.minY : '' }` );
}
await browser.close();
fs.writeFileSync( path.join( outDir, `compare${ noEnhance ? '-noenh' : '' }.json` ), JSON.stringify( rows, null, 2 ) );
