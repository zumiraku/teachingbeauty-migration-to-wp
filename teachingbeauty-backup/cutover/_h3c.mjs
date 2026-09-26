import { chromium } from 'playwright';
import fs from 'fs';
const [base, out, listFile] = process.argv.slice(2);
const list = fs.readFileSync(listFile, 'utf8').trim().split(/\r?\n/);
const b = await chromium.launch({ channel: 'chrome' });
const p = await b.newPage({ viewport: { width: 390, height: 900 } });
const rows = [];
for (const u of list) {
  await p.goto(base.replace(/\/$/, '') + u, { waitUntil: 'load' });
  await p.addStyleTag({ content: `*,*::before,*::after{animation:none!important;transition:none!important;opacity:1!important;transform:none!important}` });
  await p.waitForTimeout(200);
  const r = await p.evaluate(() => {
    const res = [];
    const main = document.querySelector('#hpb-main') || document.body;
    for (const h of main.querySelectorAll('h3')) {
      if (h.classList.contains('hpb-c-index')) continue;
      const hr = h.getBoundingClientRect();
      let node = null, el = h;
      const w = document.createTreeWalker(h, NodeFilter.SHOW_TEXT);
      while ((node = w.nextNode())) { if (node.nodeValue.trim()) { el = node; break; } }
      let tTop = null, tH = null;
      if (node) { const rg = document.createRange(); rg.selectNodeContents(node); const rr = rg.getBoundingClientRect(); tTop = Math.round(rr.top - hr.top); tH = Math.round(rr.height); }
      const fonts = h.querySelectorAll('font, span').length;
      res.push({ txt: (h.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 16), barH: Math.round(hr.height), textTop: tTop, textH: tH, nest: fonts, disp: getComputedStyle(h).display });
    }
    return res;
  });
  r.forEach((x) => rows.push({ page: u, ...x }));
}
await b.close();
fs.writeFileSync(out, JSON.stringify(rows, null, 1));
console.log('計測:', rows.length);
