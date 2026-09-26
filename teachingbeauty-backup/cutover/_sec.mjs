import { chromium } from 'playwright';
const [url, out, needle, W, H] = process.argv.slice(2);
const w = Number(W), h = Number(H);
const b = await chromium.launch({ channel: 'chrome' });
const p = await b.newPage({ viewport: { width: w, height: h } });
await p.goto(url, { waitUntil: 'load' });
await p.addStyleTag({ content: '*,*::before,*::after{animation:none!important;transition:none!important;opacity:1!important;transform:none!important}' });
await p.waitForTimeout(600);
const y = await p.evaluate((n) => {
  const el = [...document.querySelectorAll('h1,h2,h3,h4,b,p')].find((e) => e.textContent.trim().startsWith(n));
  if (!el) return -1;
  return Math.round(el.getBoundingClientRect().top + window.scrollY);
}, needle);
if (y < 0) { console.log('見つからない:', needle); process.exit(1); }
const top = Math.max(0, y - 30);
await p.evaluate((t) => window.scrollTo(0, t), top);
await p.waitForTimeout(400);
await p.screenshot({ path: out });
const total = await p.evaluate(() => document.documentElement.scrollHeight);
console.log('保存:', out, ' 見出し y=' + y, ' ページ高さ=' + total);
await b.close();
