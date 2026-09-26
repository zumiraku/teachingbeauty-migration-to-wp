import { chromium } from 'playwright';
const [u1, u2] = process.argv.slice(2);
const b = await chromium.launch({ channel: 'chrome' });
async function look(url) {
  const p = await b.newPage({ viewport: { width: 1280, height: 900 } });
  await p.goto(url, { waitUntil: 'load' });
  await p.waitForTimeout(500);
  const r = await p.evaluate(() => {
    const el = [...document.querySelectorAll('p')].find((e) => e.textContent.trim().length > 60);
    const cs = getComputedStyle(el);
    const body = getComputedStyle(document.body);
    return {
      bodyFont: body.fontFamily, bodySize: body.fontSize,
      pFont: cs.fontFamily, pSize: cs.fontSize, lh: cs.lineHeight,
      sheets: [...document.styleSheets].map((s) => (s.href || 'inline').split('/').pop()),
    };
  });
  await p.close();
  return r;
}
const a = await look(u1), c = await look(u2);
console.log('元  :', JSON.stringify(a, null, 1));
console.log('本番:', JSON.stringify(c, null, 1));
await b.close();
