// テーマの CSS / JS が、中身を直したときに必ず読み直されるかを確かめる。
//   node verify-cache.mjs https://www.teachingbeauty.jp
//
// 版が固定（?ver=2.0.0 のような）だと URL が変わらないので、一度見た人の
// ブラウザは古いものを使い続ける。このサーバーは max-age=604800（7日）なので、
// 直したのに直っていないように見える状態が最大1週間続く。
// 版がファイルの更新時刻になっていれば、直した瞬間に URL が変わる。

const base = (process.argv[2] || 'https://www.teachingbeauty.jp').replace(/\/$/, '');
const html = await (await fetch(base + '/')).text();

const links = [...html.matchAll(/<(?:link|script)[^>]*(?:href|src)='?"?([^'"]*(?:enhance|form)\.(?:css|js)[^'"]*)'?"?[^>]*>/g)].map(m => m[1]);
let bad = 0;

if (!links.length) { console.log('  NG   テーマの CSS / JS が見つからない'); process.exitCode = 1; }

for (const url of links) {
  const ver = (url.match(/[?&]ver=([^&]*)/) || [])[1];
  const name = url.split('/').pop().split('?')[0];
  if (!ver) {
    console.log(`  NG   ${name}: 版が付いていない`);
    bad++;
    continue;
  }
  // 更新時刻なら10桁の数字
  if (/^\d{9,}$/.test(ver)) {
    const d = new Date(Number(ver) * 1000);
    console.log(`  OK   ${name}: 版が更新時刻（${d.toISOString().slice(0, 16).replace('T', ' ')}）`);
  } else {
    console.log(`  NG   ${name}: 版が固定（${ver}）。直してもブラウザが読み直さない`);
    bad++;
  }
}

// 配信側のキャッシュ設定も見ておく
const res = await fetch(base + '/wp/wp-content/themes/teachingbeauty/enhance.css');
const cc = res.headers.get('cache-control') || 'なし';
console.log(`  --   配信側の指示: ${cc}`);

process.exitCode = bad ? 1 : 0;
