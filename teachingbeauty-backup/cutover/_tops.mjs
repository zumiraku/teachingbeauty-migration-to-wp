import { chromium } from 'playwright';
const [u1, u2, w] = process.argv.slice(2);
const b = await chromium.launch({ channel: 'chrome' });
async function look(url) {
  const p = await b.newPage({ viewport: { width: Number(w), height: 900 } });
  await p.goto(url, { waitUntil: 'load' });
  await p.addStyleTag({ content: `*,*::before,*::after{animation:none!important;transition:none!important;opacity:1!important;transform:none!important}` });
  await p.waitForTimeout(600);
  const r = await p.evaluate(() => ({
    heads: [...document.querySelectorAll('h1,h2,h3')].map((e) => ({ t: e.textContent.trim().slice(0, 12), y: Math.round(e.getBoundingClientRect().top + window.scrollY) })),
    imgs: [...document.querySelectorAll('img')].map((e) => ({ src: (e.currentSrc || e.src).split('/').pop(), y: Math.round(e.getBoundingClientRect().top + window.scrollY), w: Math.round(e.getBoundingClientRect().width), h: Math.round(e.getBoundingClientRect().height) })),
  }));
  await p.close();
  return r;
}
const a = await look(u1), c = await look(u2);
console.log('見出しの縦位置:');
for (let i = 0; i < Math.max(a.heads.length, c.heads.length); i++) {
  const x = a.heads[i], y = c.heads[i];
  const d = x && y ? y.y - x.y : '';
  console.log(`  [${i}] ${x ? x.t : '-'} : 元 ${x ? x.y : '-'} / 本番 ${y ? y.y : '-'}  差 ${d}`);
}
console.log('\n画像数  元:', a.imgs.length, '/ 本番:', c.imgs.length);
for (let i = 0; i < Math.max(a.imgs.length, c.imgs.length); i++) {
  const x = a.imgs[i], y = c.imgs[i];
  if (!x || !y || x.w !== y.w || x.h !== y.h) console.log(`  ≠ [${i}] 元 ${x ? `${x.src} ${x.w}x${x.h}` : '-'} / 本番 ${y ? `${y.src} ${y.w}x${y.h}` : '-'}`);
}
await b.close();
