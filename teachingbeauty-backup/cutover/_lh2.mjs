import { chromium } from 'playwright';
const b = await chromium.launch({ channel: 'chrome' });
const grab = async (url) => {
  const p = await b.newPage({ viewport: { width: 1280, height: 900 } });
  await p.goto(url, { waitUntil: 'load' });
  const r = await p.evaluate(() => {
    const root = document.querySelector('#hpb-main') || document.body;
    const tally = {};
    for (const el of root.querySelectorAll('p, td, li, font, span, div')) {
      if (!(el.textContent || '').trim()) continue;
      if (el.closest('h1,h2,h3,h4,h5,h6')) continue;
      const cs = getComputedStyle(el);
      const k = cs.fontSize + ' / ' + cs.lineHeight;
      tally[k] = (tally[k] || 0) + 1;
    }
    return tally;
  });
  await p.close();
  return r;
};
for (const name of process.argv.slice(2)) {
  const a = await grab('http://127.0.0.1:8766/' + name);
  const c = await grab('http://127.0.0.1:8765/' + name);
  const keys = [...new Set([...Object.keys(a), ...Object.keys(c)])].sort();
  const diff = keys.filter((k) => (a[k] || 0) !== (c[k] || 0));
  console.log(`\n${name}: ${diff.length === 0 ? '本文の行間は原本と完全一致' : '差あり'}`);
  for (const k of diff.slice(0, 5)) console.log(`   ${k}  原本 ${a[k] || 0} 個 / WP ${c[k] || 0} 個`);
}
await b.close();
