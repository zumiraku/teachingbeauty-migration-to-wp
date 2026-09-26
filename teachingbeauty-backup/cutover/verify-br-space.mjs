// <br> の直後に空白が残っていないかを全ページで見る。
//   例: node verify-br-space.mjs https://www.teachingbeauty.jp
// Safari は <br> の直後の空白を1文字分描画するため、残っていると行頭が揃わない。

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const here = path.dirname( fileURLToPath( import.meta.url ) );
const base = ( process.argv[ 2 ] || 'https://www.teachingbeauty.jp' ).replace( /\/$/, '' );

const wxr = fs.readFileSync( path.join( here, '..', 'teachingbeauty-fullbody.wxr' ), 'utf8' );
const slugs = [ ...wxr.matchAll( /<wp:post_name><!\[CDATA\[([^\]]+)\]\]>/g ) ].map( ( m ) => m[ 1 ] );

const AFTER_BR = /<br\s*\/?>[ \t\r\n]+(?=[^<\s])/gi;

let fail = 0;
let checked = 0;

for ( const slug of slugs ) {
	const url = base + ( slug === 'home' ? '/' : `/${ slug }.html` );
	const r = await fetch( url, { headers: { 'User-Agent': 'tb-verify/1.0', 'Cache-Control': 'no-cache' } } );
	if ( ! r.ok ) {
		console.log( `  NG   ${ slug }: HTTP ${ r.status }` );
		fail++;
		continue;
	}
	const html = await r.text();
	const body = html.replace( /^[\s\S]*?<body[^>]*>/i, '' ).replace( /<\/body>[\s\S]*$/i, '' );
	const hits = body.match( AFTER_BR );
	checked++;
	if ( hits ) {
		console.log( `  NG   ${ slug }: <br> の直後の空白 ${ hits.length } 箇所` );
		fail++;
	}
}

console.log( '' );
if ( fail ) {
	console.log( `${ checked } ページ中 ${ fail } ページで空白が残っています。` );
	process.exit( 1 );
}
console.log( `${ checked } ページすべてで <br> の直後の空白はありません。` );
