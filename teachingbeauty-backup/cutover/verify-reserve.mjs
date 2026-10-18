// ご予約ページに FC2 のフォームが実際に描かれているかを確かめる。
//   node verify-reserve.mjs https://www.teachingbeauty.jp
//
// FC2 のフォームは外のサーバーから読み込む script が document.write で書き出す。
// 本文の中に直接書かれているので読み込みの途中で実行され、ちゃんと描かれる。
// ただし外のサーバー任せなので、落ちていれば予約ページからフォームが消える。
// 置いてあるだけでなく、入力欄と送信ボタンが人に見える状態かまで見る。

import { chromium } from 'playwright';

const base = ( process.argv[ 2 ] || 'https://www.teachingbeauty.jp' ).replace( /\/$/, '' );
// FC2 は headless の既定の UA を 503 で弾くので、普通のブラウザとして名乗る。
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';
const problems = [];
const browser = await chromium.launch( { channel: 'chrome' } );

for ( const [ name, opt ] of [
	[ 'パソコン', { viewport: { width: 1280, height: 900 }, userAgent: UA } ],
	[ 'スマホ', { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 3, userAgent: UA } ],
] ) {
	const ctx = await browser.newContext( opt );
	const page = await ctx.newPage();
	page.setDefaultTimeout( 45000 );
	try {
		await page.goto( `${ base }/reserve.html`, { waitUntil: 'load' } );
		await page.waitForTimeout( 1500 );
		const r = await page.evaluate( () => {
			const form = document.querySelector( 'form[name="form858290"]' ) || document.querySelector( '#fc2form form' );
			const box  = document.querySelector( '#fc2form' ) || document.querySelector( '#output858290' );
			const vis  = ( e ) => {
				if ( ! e ) { return false; }
				const b = e.getBoundingClientRect();
				const c = getComputedStyle( e );
				return b.width > 1 && b.height > 1 && 'none' !== c.display && 'hidden' !== c.visibility;
			};
			const inputs = form ? [ ...form.querySelectorAll( 'input, textarea, select' ) ].filter( vis ) : [];
			// FC2 の送信は <input type="submit"> ではなく、onclick 付きの
			// <input type="button" value="確認"> で JavaScript から送る形になっている。
			const submit = form ? [ ...form.querySelectorAll( 'input[type="submit"], input[type="image"], input[type="button"], button' ) ].filter( vis ) : [];
			return {
				script: !! document.querySelector( 'script[src*="form1ssl.fc2.com/parts"]' ),
				wpcf7:  !! document.querySelector( '.wpcf7, form.wpcf7-form' ),
				box: vis( box ),
				form: vis( form ),
				inputs: inputs.length,
				submit: submit.length,
				width: box ? Math.round( box.getBoundingClientRect().width ) : 0,
				cancel: document.body.textContent.includes( 'キャンセル料100％全額' ),
			};
		} );
		if ( ! r.script ) { problems.push( `${ name }: FC2 のスクリプトが本文に無い` ); }
		if ( ! r.box )    { problems.push( `${ name }: フォームの枠が描かれていない` ); }
		if ( ! r.form )   { problems.push( `${ name }: フォーム本体が描かれていない` ); }
		if ( r.inputs < 3 ) { problems.push( `${ name }: 入力欄が ${ r.inputs } 個しか見えない` ); }
		if ( r.submit < 1 ) { problems.push( `${ name }: 送信ボタンが見えない` ); }
		if ( r.wpcf7 )    { problems.push( `${ name }: Contact Form 7 が残っている` ); }
		if ( ! r.cancel ) { problems.push( `${ name }: キャンセル規定の本文が消えている` ); }
		if ( ! problems.length ) {
			console.log( `  OK   ${ name }: フォームが出ている（入力欄 ${ r.inputs } 個・送信ボタン ${ r.submit } 個・幅 ${ r.width }px）` );
		}
	} catch ( e ) {
		problems.push( `${ name }: 確認できない（${ e.message.split( '\n' )[ 0 ] }）` );
	}
	await ctx.close();
}

// 予備リンク（スクリプトが動かないとき用）が生きているか。
// FC2 は素のリクエストを 503 で弾くので、実際にブラウザで開いて確かめる。
const ctx = await browser.newContext( { viewport: { width: 1280, height: 900 }, userAgent: UA } );
const pg = await ctx.newPage();
try {
	const res = await pg.goto( 'https://form1ssl.fc2.com/form/?id=6c82e89b62069752', { waitUntil: 'load', timeout: 45000 } );
	await pg.waitForTimeout( 1000 );
	const n = await pg.evaluate( () => document.querySelectorAll( 'input, textarea, select' ).length );
	if ( ! res || 200 !== res.status() ) {
		problems.push( `予備のフォームページが開かない（${ res ? res.status() : '応答なし' }）` );
	} else if ( n < 3 ) {
		problems.push( `予備のフォームページに入力欄が ${ n } 個しか無い` );
	} else {
		console.log( `  OK   予備のフォームページも開く（入力欄 ${ n } 個）` );
	}
} catch ( e ) {
	problems.push( '予備のフォームページを確認できない: ' + String( e.message ).slice( 0, 120 ) );
}
await ctx.close();
await browser.close();

for ( const x of [ ...new Set( problems ) ] ) { console.log( `  NG   ${ x }` ); }
process.exitCode = problems.length ? 1 : 0;
