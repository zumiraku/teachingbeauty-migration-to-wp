import { chromium } from 'playwright';
const b = await chromium.launch({ channel: 'chrome' });
const h = async (url) => {
  const p = await b.newPage({ viewport: { width: 1280, height: 900 } });
  await p.goto(url, { waitUntil: 'load' });
  const v = await p.evaluate(() => document.documentElement.scrollHeight);
  await p.close();
  return v;
};
for (const n of ['Meniere.html', 'hari.html', 'newpage23.html', 'kyuujin.html', 'access.html']) {
  const wp = await h('http://127.0.0.1:8765/' + n);
  console.log(`${n.padEnd(18)} WP ${wp}px`);
}
await b.close();
