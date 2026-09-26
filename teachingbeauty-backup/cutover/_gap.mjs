import { chromium } from 'playwright';
import fs from 'fs';
const [base, out, listFile] = process.argv.slice(2);
const list = fs.readFileSync(listFile, 'utf8').trim().split(/\r?\n/);
const b = await chromium.launch({ channel: 'chrome' });
const p = await b.newPage({ viewport: { width: 390, height: 900 } });
const rows = [];
for (const u of list) {
  await p.goto(base.replace(/\/$/, '') + u, { waitUntil: 'load' });
  await p.addStyleTag({ content: '*,*::before,*::after{animation:none!important;transition:none!important;opacity:1!important;transform:none!important}' });
  await p.waitForTimeout(200);
  const r = await p.evaluate(() => {
    const res = [];
    const main = document.querySelector('#hpb-main') || document.body;
    const all = [...main.querySelectorAll('h3')].filter((h) => !h.classList.contains('hpb-c-index'));
    const bottomOfInk = (root, before) => {
      // root 配下で before より上にある「最後の見えているもの」の下端
      let best = null;
      const w = document.createTreeWalker(root, NodeFilter.SHOW_TEXT | NodeFilter.SHOW_ELEMENT);
      let n;
      while ((n = w.nextNode())) {
        if (before.compareDocumentPosition(n) & Node.DOCUMENT_POSITION_FOLLOWING) continue;
        let rect = null;
        if (n.nodeType === 3) {
          if (!n.nodeValue.trim()) continue;
          const rg = document.createRange(); rg.selectNodeContents(n); rect = rg.getBoundingClientRect();
        } else if (n.tagName === 'IMG' || n.tagName === 'TABLE') {
          rect = n.getBoundingClientRect();
        }
        if (rect && rect.height > 0 && (best === null || rect.bottom > best)) best = rect.bottom;
      }
      return best;
    };
    for (const h of all) {
      const hr = h.getBoundingClientRect();
      const ink = bottomOfInk(main, h);
      let node = null; const w2 = document.createTreeWalker(h, NodeFilter.SHOW_TEXT);
      let tTop = null;
      while ((node = w2.nextNode())) { if (node.nodeValue.trim()) { const rg = document.createRange(); rg.selectNodeContents(node); tTop = Math.round(rg.getBoundingClientRect().top - hr.top); break; } }
      res.push({ txt: (h.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 14), barTop: Math.round(hr.top), inkGap: ink === null ? null : Math.round(hr.top - ink), barH: Math.round(hr.height), textTop: tTop });
    }
    return res;
  });
  r.forEach((x) => rows.push({ page: u, ...x }));
}
await b.close();
fs.writeFileSync(out, JSON.stringify(rows, null, 1));
console.log('計測:', rows.length);
