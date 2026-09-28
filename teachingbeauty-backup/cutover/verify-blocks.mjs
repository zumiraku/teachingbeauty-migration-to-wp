// トップページの本文を撮って、適用の前後で見た目が1ピクセルも変わっていないかを確かめる。
//   node verify-blocks.mjs <url> --save <file>   … 適用前に撮っておく
//   node verify-blocks.mjs <url> --check <file>  … 適用後に撮って比べる
// カタマリを割るだけの変更なので、一致しなければ何かを壊している。

import fs from 'fs';
import { chromium } from 'playwright';

const base = ( process.argv[ 2 ] || 'https://www.teachingbeauty.jp' ).replace( /\/$/, '' );
const mode = process.argv.includes( '--check' ) ? 'check' : 'save';
const file = process.argv[ process.argv.indexOf( `--${ mode }` ) + 1 ];

// テーマのフェードインが入ると同じページでも撮るたびに絵が変わるので止める。
const KILL = '*{animation:none!important;transition:none!important;opacity:1!important;transform:none!important}';

const browser = await chromium.launch( { channel: 'chrome', headless: true } );
const page = await browser.newPage( { viewport: { width: 1280, height: 900 } } );
await page.goto( base + '/', { waitUntil: 'domcontentloaded', timeout: 60000 } );
await page.addStyleTag( { content: KILL } );
await page.waitForTimeout( 4000 );

const height = Math.round( await page.evaluate( () => document.querySelector( '#hpb-main' ).getBoundingClientRect().height ) );
const shot = await page.locator( '#hpb-main' ).screenshot();
await browser.close();

if ( 'save' === mode ) {
	fs.writeFileSync( file, shot );
	fs.writeFileSync( file + '.h', String( height ) );
	console.log( `  適用前の本文を記録しました（高さ ${ height }px）` );
	process.exit( 0 );
}

const was = fs.readFileSync( file );
const wasH = Number( fs.readFileSync( file + '.h', 'utf8' ) );
console.log( `  本文の高さ  前: ${ wasH }px  後: ${ height }px` );

if ( was.equals( shot ) ) {
	console.log( '  OK   トップページの見た目は1ピクセルも変わっていません' );
	process.exit( 0 );
}

fs.writeFileSync( file.replace( /\.png$/, '-after.png' ), shot );
console.log( '  NG   見た目が変わってしまいました（-after.png に保存）' );
process.exit( 1 );
