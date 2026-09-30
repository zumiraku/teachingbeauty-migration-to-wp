// 全39ページを、パソコンとスマホの両方で1回ずつ開いて、まとめて調べる。
//   node audit-site.mjs https://www.teachingbeauty.jp
//
// これまで verify-iphone.mjs と audit-bars.mjs が別々に全ページを開いていて、
// 同じページを4回読み込んでいた。40分以上かかり、途中でブラウザが落ちた。
// 1ページにつき1回だけ開いて、そこで全部の項目を調べる形にまとめる。
//
// 調べること:
//   スマホ   画面に収まっているか / 左右に余白があるか / サイドバーが追従するか
//   共通     見出しバーの帯・左端・幅・右のはみ出し・折り返し・文字の中心
//
// 元サイト由来で直しようがないものは KNOWN に入れ、報告はするが失敗にしない。

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { chromium, devices } from 'playwright';

const here = path.dirname( fileURLToPath( import.meta.url ) );
const base = ( process.argv[ 2 ] || 'https://www.teachingbeauty.jp' ).replace( /\/$/, '' );
const wxr = fs.readFileSync( path.join( here, '..', 'teachingbeauty-fullbody.wxr' ), 'utf8' );
const slugs = [ ...wxr.matchAll( /<wp:post_name><!\[CDATA\[([^\]]+)\]\]>/g ) ].map( ( m ) => m[ 1 ] );

const TICK_C = 13.5;
const BAR_IMG = 'indexBg_5H.png';
const KNOWN_NO_BAR = [ 'news' ];   // #coupon の見出しは元から帯が無い意匠
const KILL = '*{animation:none!important;transition:none!important;opacity:1!important;transform:none!important}';

const DEVS = [
	[ 'パソコン', { viewport: { width: 1280, height: 900 } }, false ],
	[ 'スマホ', { ...devices[ 'iPhone 13' ] }, true ],
];

const bars = [];
const mobile = [];
let fatal = null;

for ( const [ devName, opt, isPhone ] of DEVS ) {
	// 端末ごとにブラウザを立て直す。1つを長く使い回すと途中で落ちることがある。
	const browser = await chromium.launch( { channel: 'chrome' } );
	const ctx = await browser.newContext( opt );
	const page = await ctx.newPage();
	page.setDefaultTimeout( 45000 );

	try {
		for ( const slug of slugs ) {
			const url = base + ( 'home' === slug ? '/' : `/${ slug }.html` );

			// 一時的な失敗で全体を落とさない
			let ok = false;
			for ( let i = 0; i < 3 && ! ok; i++ ) {
				try {
					await page.goto( url, { waitUntil: 'domcontentloaded', timeout: 45000 } );
					ok = true;
				} catch ( e ) {
					if ( 2 === i ) { throw e; }
					await page.waitForTimeout( 1500 );
				}
			}
			await page.addStyleTag( { content: KILL } ).catch( () => {} );
			await page.waitForTimeout( 260 );

			const got = await page.evaluate( ( { BAR_IMG, isPhone } ) => {
				const main = document.querySelector( '#hpb-main' );
				const inner = document.querySelector( '#hpb-inner' );
				const out = { bars: [], phone: null };

				if ( isPhone ) {
					out.phone = {
						screen: window.innerWidth,
						doc: document.documentElement.scrollWidth,
						meta: ( document.querySelector( 'meta[name="viewport"]' ) || {} ).content || '',
						gutter: inner ? Math.round( inner.getBoundingClientRect().left ) : -1,
						asidePos: ( () => {
							const a = document.querySelector( '#hpb-aside' );
							return a ? getComputedStyle( a ).position : 'なし';
						} )(),
					};
				}

				if ( ! main ) { return out; }
				const mb = main.getBoundingClientRect();
				for ( const h of main.querySelectorAll( 'h3' ) ) {
					if ( h.classList.contains( 'hpb-c-index' ) ) { continue; }
					const cs = getComputedStyle( h );
					const hb = h.getBoundingClientRect();
					const pb = h.parentElement.getBoundingClientRect();
					const pcs = getComputedStyle( h.parentElement );
					const padL = parseFloat( pcs.paddingLeft ) || 0;
					const padR = parseFloat( pcs.paddingRight ) || 0;

					// 行数と文字の位置は、文字そのものの箱で測る。
					// <strong> などの要素は自前の rect を別の高さで返すので、
					// 要素の rect を数えると1行でも2行に見えてしまう。
					//
					// さらに、空白でない文字のかたまりごとに測る。旧サイトは位置合わせに
					// 全角スペースを使っていて、それが行末に何個もぶら下がっている。
					// 全角スペースは普通の文字なので幅を持ち、文字ごと測ると
					// 目に見えない空白のぶんまで「帯からはみ出している」と出てしまう。
					// （エステの「美脚リンパマッサージ」で +13px と出たのがこれ）
					const rects = [];
					const tw = document.createTreeWalker( h, NodeFilter.SHOW_TEXT );
					let tn;
					while ( ( tn = tw.nextNode() ) ) {
						if ( ! tn.nodeValue.trim() ) { continue; }
						const re = /\S+/g;
						let mm;
						while ( ( mm = re.exec( tn.nodeValue ) ) ) {
							const r2 = document.createRange();
							r2.setStart( tn, mm.index );
							r2.setEnd( tn, mm.index + mm[ 0 ].length );
							for ( const rr of r2.getClientRects() ) {
								if ( rr.height >= 4 && rr.width >= 2 ) { rects.push( rr ); }
							}
						}
					}
					if ( ! rects.length ) { continue; }
					const top = Math.min( ...rects.map( ( r ) => r.top ) );
					const bot = Math.max( ...rects.map( ( r ) => r.bottom ) );
					const right = Math.max( ...rects.map( ( r ) => r.right ) );
					// 行数は、縦に重なっている箱を同じ行として数える。
					// 「news 新着情報」のように書体や大きさが違う文字が並ぶと、
					// 同じ行でも箱の上端がずれるため、上端の種類で数えると
					// 1行なのに2行と出てしまう。
					const sorted = rects.slice().sort( ( a, c ) => a.top - c.top );
					let lines = 0;
					let lineBottom = -Infinity;
					for ( const r of sorted ) {
						if ( r.top >= lineBottom - Math.min( r.height, 6 ) ) {
							lines++;
							lineBottom = r.bottom;
						} else {
							lineBottom = Math.max( lineBottom, r.bottom );
						}
					}

					out.bars.push( {
						t: h.textContent.trim().replace( /\s+/g, ' ' ).slice( 0, 18 ),
						// 色ベタの帯は画像を使わないので、画像の有無では判定できない。
						// class ではなく見た目で判定する。全部を色ベタに切り替えたあとは
						// class が付いていないバーも色ベタになるため。
						ベタ帯: ! cs.backgroundImage.includes( BAR_IMG ) && 'rgba(0, 0, 0, 0)' !== cs.backgroundColor,
						帯あり: cs.backgroundImage.includes( BAR_IMG ) || 'rgba(0, 0, 0, 0)' !== cs.backgroundColor,
						左: Math.round( hb.left - pb.left - padL ),
						主左: Math.round( hb.left - mb.left ),
						幅: Math.round( hb.width ),
						親幅: Math.round( pb.width - padL - padR ),
						高さ: Math.round( hb.height ),
						右はみ出し: Math.round( right - hb.right ),
						行数: lines,
						中心: +( ( ( top + bot ) / 2 - hb.top ).toFixed( 1 ) ),
						飲み込み: !! h.querySelector( 'br, img, div, p, table, ul, ol, dl, iframe, form' ),
					// 大きさは実際に字が出ているところから取る。外側が <font size="1"> の
					// ような入れ物になっていることがあり、要素を先頭から拾うと
					// 見えている字と違う値になる（エステのページで 10px と出ていた）。
					大きさ: ( () => {
						const w2 = document.createTreeWalker( h, NodeFilter.SHOW_TEXT );
						let n2;
						while ( ( n2 = w2.nextNode() ) ) {
							if ( n2.nodeValue.trim() ) { return getComputedStyle( n2.parentElement ).fontSize; }
						}
						return cs.fontSize;
					} )(),
					} );
				}
				return out;
			}, { BAR_IMG, isPhone } );

			for ( const b of got.bars ) { bars.push( { dev: devName, slug, ...b } ); }

			if ( isPhone ) {
				// サイドバーの追従は、実際にスクロールして確かめる
				const stuck = await page.evaluate( () => {
					const h = document.body.scrollHeight;
					window.scrollTo( 0, Math.max( 0, Math.min( 2600, h - window.innerHeight - 10 ) ) );
					return new Promise( ( r ) => setTimeout( () => {
						const a = document.querySelector( '#hpb-aside' );
						if ( ! a ) { return r( { なし: true } ); }
						const b = a.getBoundingClientRect();
						r( { 見えている: b.bottom > 0 && b.top < window.innerHeight, top: Math.round( b.top ), scrolled: Math.round( window.scrollY ) } );
					}, 380 ) );
				} );
				mobile.push( { slug, ...got.phone, ...stuck } );
			}
		}
	} catch ( e ) {
		fatal = `${ devName } の途中で止まりました: ${ e.message.split( '\n' )[ 0 ] }`;
	}

	await ctx.close().catch( () => {} );
	await browser.close().catch( () => {} );
	if ( fatal ) { break; }
}

if ( fatal ) {
	console.log( `  NG   ${ fatal }` );
	process.exitCode = 1;
}

// ---- スマホ ----
const mProblems = [];
for ( const m of mobile ) {
	if ( m.doc > m.screen + 2 ) { mProblems.push( `${ m.slug }: ページ ${ m.doc }px が画面 ${ m.screen }px に収まらない` ); }
	if ( ! /width=965/.test( m.meta ) ) { mProblems.push( `${ m.slug }: viewport が ${ m.meta }` ); }
	if ( m.gutter < 8 ) { mProblems.push( `${ m.slug }: 左の余白が ${ m.gutter }px` ); }
	if ( 'sticky' !== m.asidePos ) { mProblems.push( `${ m.slug }: サイドバーが sticky でない（${ m.asidePos }）` ); }
	if ( m.scrolled > 200 && ! m.見えている && ! m.なし ) { mProblems.push( `${ m.slug }: スクロール後にサイドバーが消える（top=${ m.top }）` ); }
}
// サイドバーの確認（バナーに画像の無い項目が混ざっていないか／アクセスの余白）
{
	const br = await chromium.launch( { channel: 'chrome' } );
	const pg = await br.newPage( { viewport: { width: 1280, height: 900 } } );
	await pg.goto( base + '/', { waitUntil: 'domcontentloaded', timeout: 60000 } );
	await pg.waitForTimeout( 1500 );
	const side = await pg.evaluate( () => {
		const noImg = [ ...document.querySelectorAll( '#banner li a' ) ]
			.filter( ( a ) => 'none' === getComputedStyle( a ).backgroundImage )
			.map( ( a ) => a.textContent.trim().slice( 0, 12 ) );
		const acc = document.querySelector( '#shopinfo a[href="access.html"]' );
		const tel = document.querySelector( '#shopinfo a[href^="tel:"]' );
		return {
			画像の無いバナー: noImg,
			アクセスの余白: acc ? getComputedStyle( acc ).marginTop : 'なし',
			電話からの間隔: ( acc && tel ) ? Math.round( acc.getBoundingClientRect().top - tel.getBoundingClientRect().bottom ) : null,
		};
	} );
	await br.close();
	console.log( 'サイドバー:' );
	if ( side.画像の無いバナー.length ) {
		console.log( `  NG   バナーに画像の無い項目: ${ side.画像の無いバナー.join( ', ' ) }` );
		process.exitCode = 1;
	} else {
		console.log( '  OK   バナーはすべて画像つき' );
	}
	console.log( `  OK   →アクセスの上の余白 ${ side.アクセスの余白 }（電話との間隔 ${ side.電話からの間隔 }px）` );
	console.log( '' );
}
console.log( `スマホ: ${ mobile.length } ページ` );
if ( mProblems.length ) {
	for ( const x of mProblems.slice( 0, 15 ) ) { console.log( `  NG   ${ x }` ); }
	if ( mProblems.length > 15 ) { console.log( `       …ほか ${ mProblems.length - 15 } 件` ); }
} else {
	console.log( '  OK   全ページ、画面に収まり・余白あり・サイドバーが追従' );
}

// ---- 見出しバー ----
const P = { 帯なし: [], 右はみ出し: [], ページ内で大きさが不揃い: [], 折り返し: [], 中心ずれ: [], ページ内で左が不揃い: [], 飲み込み: [] };
const byPage = {};
const bySize = {};
for ( const r of bars ) {
	const id = `${ r.dev } ${ r.slug }「${ r.t }」`;
	const known = KNOWN_NO_BAR.includes( r.slug );
	if ( ! r.帯あり && ! known ) { P.帯なし.push( id ); }
	if ( r.右はみ出し > 2 ) { P.右はみ出し.push( `${ id } +${ r.右はみ出し }px` ); }
	if ( r.行数 > 1 && ! r.飲み込み ) { P.折り返し.push( `${ id } ${ r.行数 }行` ); }
	// 画像の帯は絵の中の目印（中心 13.5px）に文字を合わせる。
	// 色ベタの帯には目印が無いので、帯そのものの真ん中に来ていればよい。
	if ( ! r.飲み込み && 1 === r.行数 && ! known ) {
		const want = r.ベタ帯 ? r.高さ / 2 : TICK_C;
		const tol = r.ベタ帯 ? 2 : 1.5;
		if ( Math.abs( r.中心 - want ) > tol ) { P.中心ずれ.push( `${ id } 中心${ r.中心 }（${ want.toFixed( 1 ) } のはず）` ); }
	}
	if ( r.飲み込み ) { P.飲み込み.push( `${ id } 高さ${ r.高さ }px` ); }
	// 同じページのバーどうしで左端が揃っているか（自分で入れた指定の巻き添えを拾う）
	if ( ! r.飲み込み && ! known ) {
		const key = `${ r.dev } ${ r.slug }`;
		( byPage[ key ] = byPage[ key ] || [] ).push( r.主左 );
		( bySize[ key ] = bySize[ key ] || [] ).push( r.大きさ );
	}
}
// ページごとに、多数派と違う大きさのバーが混ざっていないか。
// 「バーは size=5」と一律に案内して taiban で浮いたので、その再発を防ぐ。
for ( const [ key, list ] of Object.entries( bySize ) ) {
	const c = {};
	for ( const x of list ) { c[ x ] = ( c[ x ] || 0 ) + 1; }
	const keys = Object.keys( c );
	if ( keys.length > 1 ) {
		P.ページ内で大きさが不揃い.push( `${ key }: ${ keys.map( ( k ) => `${ k }×${ c[ k ] }` ).join( ' / ' ) }` );
	}
}
for ( const [ key, list ] of Object.entries( byPage ) ) {
	const uniq = [ ...new Set( list ) ];
	if ( uniq.length > 1 ) { P.ページ内で左が不揃い.push( `${ key }: ${ uniq.join( ' / ' ) }px` ); }
}

console.log( `\n見出しバー: ${ bars.length } 本（パソコン＋スマホ）` );
let bad = 0;
// 大きさはこちらで全ページ24pxに揃えたので、ばらけていたら不具合として扱う。
// 折り返し・左の不揃い・飲み込みは元サイト由来なので報告のみ。
const SOFT = [ '飲み込み', '折り返し', 'ページ内で左が不揃い' ];   // 元サイト由来・文章の長さの問題
for ( const [ name, list ] of Object.entries( P ) ) {
	if ( ! list.length ) { console.log( `  OK   ${ name }: なし` ); continue; }
	const soft = SOFT.includes( name );
	console.log( `  ${ soft ? '--' : 'NG' }   ${ name }: ${ list.length } 件${ soft ? '（元サイト由来・別件）' : '' }` );
	for ( const x of list.slice( 0, 10 ) ) { console.log( `        ${ x }` ); }
	if ( list.length > 10 ) { console.log( `        …ほか ${ list.length - 10 } 件` ); }
	if ( ! soft ) { bad += list.length; }
}

bad += mProblems.length;
console.log( bad ? `\n直すべき問題 ${ bad } 件` : '\n直すべき問題はありません' );
if ( bad ) { process.exitCode = 1; }
