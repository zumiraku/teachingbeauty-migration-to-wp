import { chromium } from 'playwright';
import fs from 'fs';
const [o, w2, W, urlsFile] = process.argv.slice(2);
const w = Number(W);
const urls = fs.readFileSync(urlsFile, 'utf8').trim().split(/\r?\n/);
const b = await chromium.launch({ channel: 'chrome' });
const p = await b.newPage({ viewport: { width: w, height: 900 } });
async function look(url) {
  await p.goto(url, { waitUntil: 'load' });
  await p.addStyleTag({ content: '*,*::before,*::after{animation:none!important;transition:none!important;opacity:1!important;transform:none!important}' });
  await p.waitForTimeout(250);
  return p.evaluate(() => [...document.querySelectorAll('h1,h2,h3,h4')].map((el) => {
    const rg = document.createRange();
    rg.selectNodeContents(el);
    const r = [...rg.getClientRects()];
    const box = el.getBoundingClientRect();
    const cs = getComputedStyle(el);
    return [el.tagName, el.textContent.trim().slice(0, 16), Math.round(box.left), Math.round(box.width), Math.round(box.height),
      r.length ? Math.round(Math.min(...r.map((x) => x.left))) : null, cs.display];
  }));
}
let bad = 0;
for (const u of urls) {
  const origPath = u === '/' ? '/index.html' : u;
  const a = await look(o + origPath);
  const c = await look(w2 + u);
  const diffs = [];
  for (let i = 0; i < Math.max(a.length, c.length); i++) {
    if (JSON.stringify(a[i]) !== JSON.stringify(c[i])) diffs.push([i, a[i], c[i]]);
  }
  if (diffs.length) {
    bad++;
    console.log(`  差 ${u}  見出し ${a.length}/${c.length}  差 ${diffs.length}`);
    for (const [i, x, y] of diffs.slice(0, 3)) {
      console.log(`     元  : ${JSON.stringify(x)}`);
      console.log(`     本番: ${JSON.stringify(y)}`);
    }
  }
}
console.log(bad === 0 ? '全ページ: 見出しの位置・大きさ・display が元サイトと一致' : `差のあるページ: ${bad}`);
await b.close();
