// ジョブチューンの画像から「康治」のところだけを隠した版を作る。
//   node make-masked-media01.mjs
// 元画像は本番のものを取ってくる。出来上がりは ../proposals/ に置く。
// 隠す範囲は、画像に座標の目盛りを重ねて実測した（大宮・さん・鍼灸歴18年は残す）。

import fs from 'fs';
import path from 'path';
import sharp from 'sharp';

const SRC = 'https://www.teachingbeauty.jp/wp/wp-content/uploads/2020/01/media01.png';
const OUT = path.join( process.cwd(), '..', 'proposals' );
const RECT = { left: 381, top: 144, width: 40, height: 24 };
// WordPress が作る縮小版。元のまま残すと隠していない名前が見えてしまうので一緒に作る。
const SIZES = [ [ 471, 263, 'media01.png' ], [ 300, 168, 'media01-300x168.png' ], [ 150, 150, 'media01-150x150.png' ] ];

const res = await fetch( SRC );
if ( ! res.ok ) { throw new Error( `元画像が取れない: ${ res.status }` ); }
const src = Buffer.from( await res.arrayBuffer() );
const meta = await sharp( src ).metadata();
console.log( `元画像 ${ meta.width }x${ meta.height } / ${ src.length } バイト` );
if ( 471 !== meta.width || 263 !== meta.height ) { throw new Error( '元画像の大きさが想定と違う' ); }

// 隠す部分は、札の地の色（隠す範囲のすぐ右下）で塗りつぶす。
const { data: px } = await sharp( src )
	.extract( { left: RECT.left + RECT.width + 4, top: RECT.top + RECT.height - 3, width: 1, height: 1 } )
	.raw().toBuffer( { resolveWithObject: true } );
const fill = { r: px[ 0 ], g: px[ 1 ], b: px[ 2 ] };
console.log( `塗る色: rgb(${ fill.r }, ${ fill.g }, ${ fill.b })` );

const patch = await sharp( {
	create: { width: RECT.width, height: RECT.height, channels: 3, background: fill },
} ).png().toBuffer();

const masked = await sharp( src )
	.composite( [ { input: patch, left: RECT.left, top: RECT.top } ] )
	.png( { compressionLevel: 9, palette: true, quality: 90 } )
	.toBuffer();

fs.mkdirSync( OUT, { recursive: true } );
for ( const [ w, h, name ] of SIZES ) {
	// 150x150 は WordPress と同じく中央を正方形に切り抜く。
	const img = 150 === w && 150 === h
		? sharp( masked ).resize( w, h, { fit: 'cover', position: 'centre' } )
		: sharp( masked ).resize( w, h, { fit: 'fill' } );
	const buf = await img.png( { compressionLevel: 9, palette: true, quality: 90 } ).toBuffer();
	fs.writeFileSync( path.join( OUT, name ), buf );
	console.log( `  ${ name.padEnd( 22 ) } ${ w }x${ h }  ${ buf.length } バイト` );
}
console.log( '\n出来上がり:', OUT );
