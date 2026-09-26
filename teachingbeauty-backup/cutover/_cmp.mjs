import { chromium } from 'playwright';
const [u1, u2, w] = process.argv.slice(2);
const b = await chromium.launch({ channel: 'chrome' });
async function look(url) {
  const p = await b.newPage({ viewport: { width: Number(w), height: 900 } });
  await p.goto(url, { waitUntil: 'load' });
  await p.addStyleTag({ content: `*,*::before,*::after{animation:none!important;transition:none!important;opacity:1!important;transform:none!important}` });
  await p.waitForTimeout(600);
  const r = await p.evaluate(() => {
    const heads = [...document.querySelectorAll('h1,h2,h3,h4,h5')].map((e) => {
      const b = e.getBoundingClientRect();
      return { tag: e.tagName, t: e.textContent.trim().slice(0, 14), l: Math.round(b.left), w: Math.round(b.width) };
    });
    let tel = null;
    for (const a of document.querySelectorAll('a[href^="tel:"]')) {
      const b = a.getBoundingClientRect();
      tel = { l: Math.round(b.left), t: Math.round(b.top + window.scrollY), w: Math.round(b.width) };
      break;
    }
    const p1 = document.querySelector('p');
    return { docW: document.documentElement.scrollWidth, docH: document.documentElement.scrollHeight, heads, tel, vp: document.querySelector('meta[name=viewport]')?.content || '(なし)' };
  });
  await p.close();
  return r;
}
const a = await look(u1), c = await look(u2);
console.log('viewport メタ  元:', a.vp, ' / 本番:', c.vp);
console.log('ページ幅  元:', a.docW, ' / 本番:', c.docW, '   高さ 元:', a.docH, ' / 本番:', c.docH);
console.log('最初の tel リンク  元:', JSON.stringify(a.tel), ' / 本番:', JSON.stringify(c.tel));
console.log('見出し（左端 / 幅）:');
for (let i = 0; i < Math.max(a.heads.length, c.heads.length); i++) {
  const x = a.heads[i], y = c.heads[i];
  const mark = (x && y && x.l === y.l && x.w === y.w) ? '  ' : '≠ ';
  console.log(` ${mark}[${i}] 元: ${x ? `${x.tag} l=${x.l} w=${x.w} "${x.t}"` : '-'}`);
  console.log(`      本番: ${y ? `${y.tag} l=${y.l} w=${y.w} "${y.t}"` : '-'}`);
}
await b.close();
