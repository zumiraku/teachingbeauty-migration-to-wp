import { chromium } from 'playwright';
const [u1, u2, W] = process.argv.slice(2);
const w = Number(W);
const b = await chromium.launch({ channel: 'chrome' });
async function look(url) {
  const p = await b.newPage({ viewport: { width: w, height: 900 } });
  await p.goto(url, { waitUntil: 'load' });
  await p.addStyleTag({ content: '*,*::before,*::after{animation:none!important;transition:none!important;opacity:1!important;transform:none!important}' });
  await p.waitForTimeout(600);
  const r = await p.evaluate(() => {
    const out = [];
    for (const el of document.querySelectorAll('h3')) {
      const t = el.textContent.trim();
      if (!t) continue;
      const rg = document.createRange();
      rg.selectNodeContents(el);
      const rects = [...rg.getClientRects()];
      const box = el.getBoundingClientRect();
      const cs = getComputedStyle(el);
      out.push({
        t: t.slice(0, 12),
        boxL: Math.round(box.left),
        textL: rects.length ? Math.round(Math.min(...rects.map((x) => x.left))) : null,
        padL: cs.paddingLeft, disp: cs.display, ti: cs.textIndent,
      });
    }
    // 本文の左端（比較用）
    const p1 = [...document.querySelectorAll('p,div')].find((e) => e.textContent.trim().length > 40 && e.getBoundingClientRect().width > 200);
    const rg = document.createRange();
    if (p1) rg.selectNodeContents(p1);
    const prects = p1 ? [...rg.getClientRects()] : [];
    return { out, bodyL: prects.length ? Math.round(Math.min(...prects.map((x) => x.left))) : null };
  });
  await p.close();
  return r;
}
const a = await look(u1), c = await look(u2);
console.log('本文の左端  元:', a.bodyL, ' / 本番:', c.bodyL);
console.log('h3（枠の左 / 文字の左 / padding-left / display）:');
for (let i = 0; i < Math.max(a.out.length, c.out.length); i++) {
  const x = a.out[i], y = c.out[i];
  const same = x && y && x.textL === y.textL;
  console.log(` ${same ? '  ' : '≠ '}[${i}] "${x ? x.t : '-'}"`);
  if (x) console.log(`      元  : 枠${x.boxL} 文字${x.textL} pad${x.padL} ${x.disp} indent${x.ti}`);
  if (y) console.log(`      本番: 枠${y.boxL} 文字${y.textL} pad${y.padL} ${y.disp} indent${y.ti}`);
}
await b.close();
