// 固定ページの編集画面に文字サイズの欄が出ているかを実際に開いて確かめる。
//   TB_USER=... TB_PASS=... node verify-editor.mjs https://www.teachingbeauty.jp 1006
//
// ツールバーは管理画面にログインしないと見られないので、実際にログインして開く。
// 認証情報は環境変数から受け取る（リポジトリには置かない）。

import { chromium } from 'playwright';

const base = ( process.argv[ 2 ] || 'https://www.teachingbeauty.jp' ).replace( /\/$/, '' );
const postId = process.argv[ 3 ] || '1006';
const user = process.env.TB_USER;
const pass = process.env.TB_PASS;

if ( ! user || ! pass ) {
	console.log( '  --   TB_USER / TB_PASS が無いので編集画面の確認は省略' );
	process.exit( 0 );
}

const problems = [];
const browser = await chromium.launch( { channel: 'chrome' } );
const page = await browser.newPage( { viewport: { width: 1400, height: 950 } } );
page.setDefaultTimeout( 45000 );

try {
	await page.goto( `${ base }/wp/wp-login.php`, { waitUntil: 'domcontentloaded' } );
	await page.fill( '#user_login', user );
	await page.fill( '#user_pass', pass );
	await Promise.all( [
		page.waitForNavigation( { waitUntil: 'domcontentloaded' } ),
		page.click( '#wp-submit' ),
	] );
	if ( ! /wp-admin/.test( page.url() ) ) {
		problems.push( 'ログインできない' );
	} else {
		await page.goto( `${ base }/wp/wp-admin/post.php?post=${ postId }&action=edit`, { waitUntil: 'domcontentloaded' } );
		// クラシックエディタの iframe が立ち上がるまで待つ
		await page.waitForSelector( '#wp-content-editor-container', { timeout: 45000 } ).catch( () => {} );
		await page.waitForTimeout( 2500 );

		const r = await page.evaluate( () => {
			const out = { tinymce: false, rows: 0, size: null, list: null, items: [] };
			const ed = window.tinymce && window.tinymce.get( 'content' );
			out.tinymce = !! ed;
			if ( ed ) {
				out.list = ed.settings.fontsize_formats || null;
				out.items = ( ed.settings.toolbar2 || '' ).split( /[ ,]+/ ).filter( Boolean );
			}
			const box = document.querySelector( '#wp-content-editor-container' );
			if ( box ) {
				out.rows = box.querySelectorAll( '.mce-toolbar' ).length;
				// 文字サイズの欄は「フォントサイズ」の見出しを持つボタンとして出る
				const btn = [ ...box.querySelectorAll( '.mce-btn' ) ].find( ( e ) =>
					/フォントサイズ|Font Sizes|Font size/i.test( e.getAttribute( 'aria-label' ) || e.title || e.textContent || '' ) );
				out.size = btn ? ( btn.textContent || '' ).trim().slice( 0, 20 ) : null;
			}
			return out;
		} );

		if ( ! r.tinymce ) {
			problems.push( 'クラシックエディタが見つからない（ブロックエディタになっている？）' );
		}
		if ( ! r.items.includes( 'fontsizeselect' ) ) {
			problems.push( `2段目に fontsizeselect が入っていない（今: ${ r.items.join( ' ' ) || 'なし' }）` );
		}
		if ( 'px' !== String( r.list ).slice( -2 ) ) {
			problems.push( `大きさの一覧が px になっていない（今: ${ r.list }）` );
		}
		if ( ! r.size ) {
			problems.push( 'ツールバーに文字サイズの欄が描かれていない' );
		}
		if ( ! problems.length ) {
			console.log( `  OK   文字サイズの欄が出ている（${ r.size }／${ r.list }）` );
			console.log( `  OK   ツールバー ${ r.rows } 段・2段目の先頭が ${ r.items[ 0 ] }` );
		}
	}
} catch ( e ) {
	problems.push( `確認できない: ${ e.message.split( '\n' )[ 0 ] }` );
}

await browser.close();
for ( const x of problems ) { console.log( `  NG   ${ x }` ); }
process.exitCode = problems.length ? 1 : 0;
