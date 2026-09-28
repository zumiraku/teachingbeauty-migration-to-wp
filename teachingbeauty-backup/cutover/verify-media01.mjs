// 本番のジョブチューンの画像で、名前がちゃんと隠れているかを確かめる。
//   node verify-media01.mjs https://www.teachingbeauty.jp
// 隠した範囲に明るい画素（白い文字）が残っていたら異常終了する。

import sharp from 'sharp';

const base = ( process.argv[ 2 ] || 'https://www.teachingbeauty.jp' ).replace( /\/$/, '' );
const dir = `${ base }/wp/wp-content/uploads/2020/01`;
// 元の 471x263 での「康治」の位置。縮小版は同じ割合の場所を見る。
const R = { left: 381, top: 144, width: 40, height: 24 };
const FILES = [
	[ 'media01.png', 471, 263 ],
	[ 'media01-300x168.png', 300, 168 ],
];

let bad = 0;

for ( const [ name, w, h ] of FILES ) {
	const res = await fetch( `${ dir }/${ name }?v=${ Date.now() }` );
	if ( ! res.ok ) { console.log( `  NG   ${ name } が取れない（${ res.status }）` ); bad++; continue; }
	const buf = Buffer.from( await res.arrayBuffer() );
	const meta = await sharp( buf ).metadata();
	if ( meta.width !== w || meta.height !== h ) {
		console.log( `  NG   ${ name } の大きさが ${ meta.width }x${ meta.height }（${ w }x${ h } のはず）` );
		bad++;
		continue;
	}
	const sx = w / 471;
	const sy = h / 263;
	const box = {
		left: Math.round( R.left * sx ) + 2,
		top: Math.round( R.top * sy ) + 2,
		width: Math.max( 1, Math.round( R.width * sx ) - 4 ),
		height: Math.max( 1, Math.round( R.height * sy ) - 4 ),
	};
	const { data } = await sharp( buf ).extract( box ).raw().toBuffer( { resolveWithObject: true } );
	// 白い文字が残っていれば明るい画素が出る。塗りつぶしてあれば出ない。
	let bright = 0;
	for ( let i = 0; i < data.length; i += 3 ) {
		if ( data[ i ] > 200 && data[ i + 1 ] > 200 && data[ i + 2 ] > 200 ) { bright++; }
	}
	const total = box.width * box.height;
	const pct = ( 100 * bright / total ).toFixed( 1 );
	if ( bright > total * 0.02 ) {
		console.log( `  NG   ${ name } はまだ名前が読める（明るい画素 ${ pct }%）` );
		bad++;
	} else {
		console.log( `  OK   ${ name } ${ w }x${ h } 名前は隠れている（明るい画素 ${ pct }%）` );
	}
}

// sharp を読み込んだあとに process.exit() を呼ぶと libuv が落ちるので、終了コードだけ立てる。
process.exitCode = bad ? 1 : 0;
