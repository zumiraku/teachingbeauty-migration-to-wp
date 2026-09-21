// 本番サイトの全 URL をブラウザで巡回して監査する
//   node audit-site.mjs https://www.teachingbeauty.jp [--engine=chromium|webkit] [--out=DIR]
// 巡回: トップから辿れる同一ホストの全リンク + sitemap.xml + /sp/index.html（管理画面・ログインは除外）
// 各ページで記録: HTTP、console エラー/警告、失敗したリクエスト（4xx/5xx/ブロック/混在コンテンツ）、
//   壊れた画像（naturalWidth 0）、文字化け（U+FFFD）、http:// 参照、横スクロール、外部リンクの生死
import fs from 'node:fs';
import path from 'node:path';
import { chromium, webkit } from 'playwright';

const args = process.argv.slice( 2 );
const base = ( args.find( a => ! a.startsWith( '--' ) ) || '' ).replace( /\/$/, '' );
const engine = ( args.find( a => a.startsWith( '--engine=' ) ) || '--engine=chromium' ).split( '=' )[ 1 ];
const outDir = ( args.find( a => a.startsWith( '--out=' ) ) || '--out=./audit-out' ).split( '=' )[ 1 ];
if ( ! base ) { console.log( 'usage: node audit-site.mjs <base> [--engine=chromium|webkit] [--out=DIR]' ); process.exit( 2 ); }
fs.mkdirSync( outDir, { recursive: true } );
const host = new URL( base ).host;

const isInternal = ( u ) => { try { const x = new URL( u ); return x.host === host; } catch { return false; } };
const skip = ( u ) => /\/wp\/wp-(admin|login|json)|\/wp-json|\/_old-static\/|\?|#|\.(jpg|jpeg|png|gif|css|js|xml|txt|pdf|ico)$/i.test( u );
const norm = ( u ) => { const x = new URL( u ); x.hash = ''; return x.href; };

// seed: sitemap + トップ + /sp/（--seeds=FILE で URL 一覧を与えることもできる。--no-crawl でリンクを辿らない）
const seedsFile = ( args.find( a => a.startsWith( '--seeds=' ) ) || '' ).split( '=' )[ 1 ];
const noCrawl = args.includes( '--no-crawl' );
const queue = seedsFile ? fs.readFileSync( seedsFile, 'utf8' ).split( /\r?\n/ ).filter( Boolean ) : [ `${ base }/`, `${ base }/sp/index.html` ];
if ( ! seedsFile ) {
	try {
		const sm = await ( await fetch( `${ base }/sitemap.xml` ) ).text();
		for ( const m of sm.matchAll( /<loc>([^<]+)<\/loc>/g ) ) { const u = m[ 1 ].trim(); if ( isInternal( u ) ) queue.push( u ); }
	} catch {}
}

const browserType = engine === 'webkit' ? webkit : chromium;
const browser = await browserType.launch( engine === 'chromium' ? { channel: 'chrome', headless: true } : { headless: true } );
const ctx = await browser.newContext( { viewport: { width: 1280, height: 900 }, locale: 'ja-JP' } );

const seen = new Set();
const results = [];
const externalCache = new Map();

async function checkExternal( u ) {
	if ( externalCache.has( u ) ) return externalCache.get( u );
	let s = 0;
	try {
		const r = await fetch( u, { method: 'HEAD', redirect: 'follow', headers: { 'User-Agent': 'Mozilla/5.0 tb-audit' }, signal: AbortSignal.timeout( 15000 ) } );
		s = r.status;
		if ( s === 405 || s === 403 ) { const r2 = await fetch( u, { redirect: 'follow', headers: { 'User-Agent': 'Mozilla/5.0 tb-audit' }, signal: AbortSignal.timeout( 15000 ) } ); s = r2.status; }
	} catch ( e ) { s = -1; }
	externalCache.set( u, s );
	return s;
}

while ( queue.length ) {
	const raw = queue.shift();
	let url; try { url = norm( raw ); } catch { continue; }
	if ( seen.has( url ) || ! isInternal( url ) || skip( url.replace( base, '' ) ) ) continue;
	seen.add( url );

	const page = await ctx.newPage();
	const rec = { url, engine, status: 0, console: [], failed: [], brokenImages: [], mojibake: 0, httpRefs: [], hScroll: false, links: 0, externalBad: [], title: '' };
	page.on( 'console', ( m ) => { if ( m.type() === 'error' || m.type() === 'warning' ) rec.console.push( `${ m.type() }: ${ m.text().slice( 0, 200 ) }` ); } );
	page.on( 'requestfailed', ( r ) => rec.failed.push( `${ r.failure()?.errorText } ${ r.url().slice( 0, 160 ) }` ) );
	page.on( 'response', ( r ) => { if ( r.status() >= 400 ) rec.failed.push( `HTTP ${ r.status() } ${ r.url().slice( 0, 160 ) }` ); } );
	try {
		const resp = await page.goto( url, { waitUntil: 'load', timeout: 60000 } );
		rec.status = resp ? resp.status() : 0;
		await page.waitForTimeout( 1500 );
		const info = await page.evaluate( () => {
			const imgs = [ ...document.images ].filter( i => i.complete && i.naturalWidth === 0 && i.getAttribute( 'src' ) ).map( i => i.getAttribute( 'src' ) );
			const text = document.body ? document.body.innerText : '';
			const mojibake = ( text.match( /�/g ) || [] ).length;
			const httpRefs = [ ...document.querySelectorAll( '[src^="http://"],[href^="http://"][rel~="stylesheet"],link[href^="http://"]' ) ].map( e => e.getAttribute( 'src' ) || e.getAttribute( 'href' ) );
			const hScroll = document.documentElement.scrollWidth > document.documentElement.clientWidth + 2;
			const links = [ ...document.querySelectorAll( 'a[href]' ) ].map( a => a.href );
			return { imgs, mojibake, httpRefs, hScroll, links, title: document.title };
		} );
		rec.brokenImages = info.imgs; rec.mojibake = info.mojibake; rec.httpRefs = info.httpRefs; rec.hScroll = info.hScroll; rec.title = info.title;
		rec.links = info.links.length;
		for ( const l of info.links ) {
			if ( isInternal( l ) ) { if ( ! noCrawl && ! skip( l.replace( base, '' ) ) ) queue.push( l ); }
			else if ( /^https?:/.test( l ) ) { const s = await checkExternal( l ); if ( s !== 200 && s !== 301 && s !== 302 ) rec.externalBad.push( `${ s } ${ l }` ); }
		}
		const name = url.replace( base, '' ).replace( /[^a-zA-Z0-9._-]+/g, '_' ) || '_root';
		await page.screenshot( { path: path.join( outDir, `${ engine }-${ name }.png` ), fullPage: true } ).catch( () => {} );
	} catch ( e ) {
		rec.error = e.message.slice( 0, 200 );
	}
	await page.close();
	// ノイズ除去: 外部ウィジェット（ekiten/GTM/FC2）由来のものは別扱い
	const isExt = ( s ) => /ekiten|googletagmanager|google-analytics|analytics\.google|google\.com\/(ccm|rmkt|pagead)|google\.co\.jp\/pagead|fc2|doubleclick|googleads|youtube|ameblo/i.test( s );
	rec.consoleExt = rec.console.filter( isExt ); rec.console = rec.console.filter( s => ! isExt( s ) );
	rec.failedExt = rec.failed.filter( isExt ); rec.failed = rec.failed.filter( s => ! isExt( s ) );
	results.push( rec );
	const bad = rec.status !== 200 || rec.console.length || rec.failed.length || rec.brokenImages.length || rec.mojibake || rec.httpRefs.length || rec.hScroll || rec.externalBad.length || rec.error;
	console.log( `${ bad ? '✗' : '✓' } ${ rec.status } ${ url.replace( base, '' ) || '/' }` + ( bad ? `  console=${ rec.console.length } failed=${ rec.failed.length } brokenImg=${ rec.brokenImages.length } mojibake=${ rec.mojibake } http=${ rec.httpRefs.length } hscroll=${ rec.hScroll } extBad=${ rec.externalBad.length }${ rec.error ? ' ERR ' + rec.error : '' }` : '' ) );
}
await browser.close();
fs.writeFileSync( path.join( outDir, `audit-${ engine }.json` ), JSON.stringify( results, null, 2 ) );
const bad = results.filter( r => r.status !== 200 || r.console.length || r.failed.length || r.brokenImages.length || r.mojibake || r.httpRefs.length || r.hScroll || r.externalBad.length || r.error );
console.log( `\n巡回 ${ results.length } URL / 問題あり ${ bad.length }` );
for ( const r of bad ) {
	console.log( `\n--- ${ r.url }` );
	if ( r.status !== 200 ) console.log( '  HTTP', r.status );
	for ( const c of r.console ) console.log( '  console:', c );
	for ( const f of r.failed ) console.log( '  failed:', f );
	for ( const i of r.brokenImages ) console.log( '  broken img:', i );
	if ( r.mojibake ) console.log( '  mojibake chars:', r.mojibake );
	for ( const h of r.httpRefs ) console.log( '  http ref:', h );
	if ( r.hScroll ) console.log( '  horizontal scroll' );
	for ( const e of r.externalBad ) console.log( '  external:', e );
	if ( r.error ) console.log( '  error:', r.error );
}
const extNoise = results.filter( r => r.consoleExt.length || r.failedExt.length );
if ( extNoise.length ) { console.log( `\n（参考）外部ウィジェット由来の警告があるページ: ${ extNoise.length }` ); }
console.log( bad.length ? '[AUDIT FAIL]' : '[AUDIT PASS]' );
