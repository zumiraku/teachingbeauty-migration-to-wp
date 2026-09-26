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
      const rect = h.getBoundingClientRect();
      // 最初のテキストを実際に描いている要素
      let el = h, node = null;
      const w = document.createTreeWalker(h, NodeFilter.SHOW_TEXT);
      while ((node = w.nextNode())) { if (node.nodeValue.trim()) { el = node.parentElement; break; } }
      const cs = getComputedStyle(el);
      let gap = null, prev = h.previousElementSibling;
      while (prev && getComputedStyle(prev).display === 'none') prev = prev.previousElementSibling;
      if (prev) gap = Math.round(rect.top - prev.getBoundingClientRect().bottom);
      res.push({ txt: (h.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 18), fs: cs.fontSize, fw: cs.fontWeight, h: Math.round(rect.height), gap, disp: getComputedStyle(h).display });
    }
    return res;
  });
  r.forEach((x) => rows.push({ page: u, ...x }));
}
await b.close();
fs.writeFileSync(out, JSON.stringify(rows, null, 1));
console.log(out, '見出し数:', rows.length);
