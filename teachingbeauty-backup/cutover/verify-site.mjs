// 切替後のサイト検証（ローカル再現・本番の両方で使う）
//   node verify-site.mjs <base URL> [--live]
//   例: node verify-site.mjs https://www.teachingbeauty.jp --live
// 検査: 39ページ(200・title・本文・canonical・WP既定CSS無し)、全 <img>、内部リンク、
//       リダイレクト(index.html / wp/xxx.html / funin_aen / http / 非www)、管理画面・REST、フォーム、静的資産
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const base = ( process.argv[ 2 ] || '' ).replace( /\/$/, '' );
const live = process.argv.includes( '--live' );
if ( ! base ) { console.log( 'usage: node verify-site.mjs <base> [--live]' ); process.exit( 2 ); }

const here = path.dirname( fileURLToPath( import.meta.url ) );
const wxr = fs.readFileSync( path.join( here, '..', 'teachingbeauty-fullbody.wxr' ), 'utf8' );
const pages = [];
for ( const m of wxr.matchAll( /<item>[\s\S]*?<title>([^<]*)<\/title>[\s\S]*?<wp:post_name><!\[CDATA\[([^\]]+)\]\]>[\s\S]*?<\/item>/g ) ) {
	const slug = m[ 2 ];
	// 期待値は元サイト（静的HTMLのミラー）の <title>。WXR は全角スペースが半角になっている箇所があるため使わない
	let title = m[ 1 ].replace( /&amp;/g, '&' );
	const orig = path.join( here, '..', 'www', slug === 'home' ? 'index.html' : `${ slug }.html` );
	if ( fs.existsSync( orig ) ) {
		const buf = fs.readFileSync( orig );
		const head = buf.toString( 'latin1', 0, 2000 );
		const cs = /charset=["']?shift_jis/i.test( head ) ? 'shift_jis' : 'utf-8'; // 元サイトは 19 ページが Shift_JIS
		const t = new TextDecoder( cs ).decode( buf ).match( /<title>([^<]*)<\/title>/ );
		if ( t ) { title = t[ 1 ]; }
	}
	pages.push( { title, slug } );
}

let pass = 0, fail = 0;
const failures = [];
const ok = ( cond, label, detail = '' ) => { if ( cond ) { pass++; } else { fail++; failures.push( label + ( detail ? ' — ' + detail : '' ) ); } };

async function get( url, opts = {} ) {
	try {
		const r = await fetch( url, { redirect: 'manual', headers: { 'User-Agent': 'tb-verify/1.0', 'Cache-Control': 'no-cache' }, ...opts } );
		const text = opts.method === 'HEAD' ? '' : await r.text();
		return { status: r.status, location: r.headers.get( 'location' ) || '', ct: r.headers.get( 'content-type' ) || '', text };
	} catch ( e ) { return { status: 0, location: '', ct: '', text: '', err: e.message }; }
}
const head = async ( url ) => { const r = await get( url, { method: 'HEAD' } ); return r.status === 405 ? ( await get( url ) ).status : r.status; };
const abs = ( href, pageUrl ) => { try { return new URL( href, pageUrl ).href; } catch { return null; } };
const norm = ( u ) => u.replace( /^http:\/\//, 'https://' ).replace( /\/$/, '' );

console.log( `検証対象: ${ base }  (${ pages.length } ページ)` );

// 1) 39 ページ
const checkedAssets = new Map();
let imgTotal = 0, linkTotal = 0;
for ( const p of pages ) {
	const url = p.slug === 'home' ? `${ base }/` : `${ base }/${ p.slug }.html`;
	const r = await get( url );
	ok( r.status === 200, `${ p.slug }: HTTP 200`, `got ${ r.status }` );
	if ( r.status !== 200 ) { continue; }
	const title = ( r.text.match( /<title>([^<]*)<\/title>/ ) || [ , '' ] )[ 1 ];
	ok( title === p.title, `${ p.slug }: <title> 一致`, `"${ title }"` );
	ok( r.text.includes( 'id="hpb-container"' ), `${ p.slug}: 本文(hpb-container)あり` );
	const canon = ( r.text.match( /<link rel="canonical" href="([^"]+)"/ ) || [ , '' ] )[ 1 ];
	ok( norm( canon ) === norm( url ), `${ p.slug }: canonical`, `"${ canon }"` );
	ok( ! r.text.includes( 'global-styles-inline-css' ), `${ p.slug }: WP既定CSSなし` );
	ok( ! /<meta name=.robots.[^>]*noindex/i.test( r.text ), `${ p.slug }: noindex なし` );
	ok( ! /\/wp\/[^"']*\.html/.test( r.text ), `${ p.slug }: 本文に /wp/xxx.html リンクなし` );
	if ( p.slug === 'home' ) { ok( r.text.includes( "location.href = '/sp/index.html'" ), 'home: スマホ→/sp/ リダイレクトあり' ); }
	if ( p.slug === 'reserve' && live ) { ok( /wpcf7|contact-form-7/.test( r.text ), 'reserve: 予約フォーム(CF7)あり' ); }
	if ( p.slug === 'muryou' ) { ok( ! /wpcf7-form/.test( r.text ), 'muryou: フォームなし（原本どおり）' ); }
	for ( const css of [ 'hpbparts.css', 'container_5H_2c_top.css', 'main_5H_2c.css', 'user.css' ] ) {
		ok( r.text.includes( `/${ css }` ), `${ p.slug }: ${ css } 参照あり` );
	}
	// 画像
	for ( const m of r.text.matchAll( /<img\b[^>]*\bsrc="([^"]+)"/gi ) ) {
		const u = abs( m[ 1 ], url ); if ( ! u ) continue;
		imgTotal++;
		if ( ! checkedAssets.has( u ) ) { checkedAssets.set( u, await head( u ) ); }
		ok( checkedAssets.get( u ) === 200, `${ p.slug }: 画像 ${ m[ 1 ] }`, `HTTP ${ checkedAssets.get( u ) }` );
	}
	// CSS 背景画像（inline style）
	for ( const m of r.text.matchAll( /url\(['"]?([^'")]+)['"]?\)/gi ) ) {
		const u = abs( m[ 1 ], url ); if ( ! u || ! u.startsWith( base ) ) continue;
		if ( ! checkedAssets.has( u ) ) { checkedAssets.set( u, await head( u ) ); }
		ok( checkedAssets.get( u ) === 200, `${ p.slug }: 背景画像 ${ m[ 1 ] }`, `HTTP ${ checkedAssets.get( u ) }` );
	}
	// 内部リンク（同一サイト内 .html と /）
	for ( const m of r.text.matchAll( /<a\b[^>]*\bhref="([^"#]+)"/gi ) ) {
		const u = abs( m[ 1 ], url ); if ( ! u || ! u.startsWith( base ) ) continue;
		if ( ! /\.html$|\/$/.test( u ) ) continue;
		linkTotal++;
		if ( ! checkedAssets.has( u ) ) {
			let s = await head( u );
			if ( s === 301 || s === 302 ) { const r2 = await get( u ); const t = abs( r2.location, u ); s = t ? await head( t ) : s; }
			checkedAssets.set( u, s );
		}
		ok( checkedAssets.get( u ) === 200, `${ p.slug }: リンク ${ m[ 1 ] }`, `HTTP ${ checkedAssets.get( u ) }` );
	}
}

// 2) リダイレクト
const r1 = await get( `${ base }/index.html` );
ok( r1.status === 301 && norm( abs( r1.location, base + '/' ) ) === norm( base ), '/index.html → / (301)', `${ r1.status } ${ r1.location }` );
const r2 = await get( `${ base }/wp/concept.html` );
ok( r2.status === 301 && norm( abs( r2.location, base + '/' ) ) === norm( `${ base }/concept.html` ), '/wp/concept.html → /concept.html (301)', `${ r2.status } ${ r2.location }` );
const r3 = await get( `${ base }/wp/` );
ok( r3.status === 301 && norm( abs( r3.location, base + '/' ) ) === norm( base ), '/wp/ → / (301)', `${ r3.status } ${ r3.location }` );
const r7 = await get( `${ base }/3okushi.html` );
ok( r7.status === 301 && /\/kokushi\.html$/.test( r7.location ), '/3okushi.html → /kokushi.html (301)', `${ r7.status } ${ r7.location }` );
const r8 = await get( `${ base }/Meniere.html` );
ok( r8.status === 200 && /canonical" href="[^"]*\/Meniere\.html"/.test( r8.text ), '/Meniere.html: 200 かつ canonical が Meniere.html', `${ r8.status }` );
const r4 = await get( `${ base }/funin_aen.html` );
ok( r4.status === 301 && /\/funin\.html$/.test( r4.location ), '/funin_aen.html → /funin.html (301)', `${ r4.status } ${ r4.location }` );
if ( live ) {
	const h = new URL( base ).host;
	const r5 = await get( `http://${ h }/concept.html` );
	ok( r5.status === 301 && r5.location.startsWith( 'https://' ), 'http → https (301)', `${ r5.status } ${ r5.location }` );
	const r6 = await get( `https://${ h.replace( /^www\./, '' ) }/concept.html` );
	ok( r6.status === 301 && r6.location.startsWith( `https://${ h }/` ), '非www → www (301)', `${ r6.status } ${ r6.location }` );
}

// 3) 管理画面・REST・ログイン
const a1 = await get( `${ base }/wp/wp-login.php` );
ok( a1.status === 200 && /wp-submit|loginform/.test( a1.text ), '/wp/wp-login.php 200', `${ a1.status }` );
const a2 = await get( `${ base }/wp/wp-admin/` );
ok( a2.status === 302 || a2.status === 200, '/wp/wp-admin/ 応答（302→ログイン）', `${ a2.status }` );
const a3 = await get( `${ base }/wp-json/` );
ok( a3.status === 200 && /"namespaces"/.test( a3.text ), '/wp-json/ 200 (REST がルートで動く)', `${ a3.status }` );
const a4 = live ? await get( `${ base }/wp-json/contact-form-7/v1/contact-forms` ) : { status: 200 };
ok( a4.status === 401 || a4.status === 403 || a4.status === 200, 'CF7 REST 応答', `${ a4.status }` );

// 4) 静的資産（残すもの）
for ( const f of [ '/sp/index.html', '/sitemap.xml', '/robots.txt', '/googlee9db13e7722f4047.html', '/hpbparts.css', '/newpage26.html', '/este.html' ] ) {
	if ( ! live && ( f === '/newpage26.html' || f === '/este.html' || f === '/googlee9db13e7722f4047.html' ) ) { continue; } // ローカルのミラーには孤立ページが無い
	const s = await head( `${ base }${ f }` );
	ok( s === 200, `静的 ${ f } 200`, `HTTP ${ s }` );
}

console.log( `\n画像 ${ imgTotal } 参照 / 内部リンク ${ linkTotal } 参照 / 資産URL ${ checkedAssets.size } 種` );
console.log( `結果: PASS ${ pass } / FAIL ${ fail }` );
if ( fail ) { console.log( '--- 失敗 ---' ); for ( const f of failures ) console.log( ' ✗ ' + f ); }
console.log( fail ? '[RESULT FAIL]' : '[RESULT PASS]' );
process.exit( fail ? 1 : 0 );
