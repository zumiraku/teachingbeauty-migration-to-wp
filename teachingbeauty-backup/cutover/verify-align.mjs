// 本文の左端が全ページでそろっているかを確かめる。
//   node verify-align.mjs
//   TB_LOCAL_CSS=../../teachingbeauty-theme/enhance.css node verify-align.mjs   ← 反映前の確認
//
// 元サイトは <p> だけに padding-left: 16.8px が付いていて、<p> を使わず div に
// 直接書かれた文は 0px から始まる。同じページの中でブロックごとに左端がずれ、
// ページ間でも見え方が変わっていた（耳鳴りのページで指摘を受けた）。
// いまは全ページ 16.8px が本文の左端。それより深い値は元サイト由来の字下げ。
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { chromium } from 'playwright';
const here = path.dirname(fileURLToPath(import.meta.url));
const wxr = fs.readFileSync(path.join(here, '..', 'teachingbeauty-fullbody.wxr'), 'utf8');
const slugs = [...wxr.matchAll(/<wp:post_name><!\[CDATA\[([^\]]+)\]\]>/g)].map(m => m[1]);
export const MEASURE = () => {
  const main = document.querySelector('#hpb-main');
  const ml = main.getBoundingClientRect().left;
  const BLOCK = new Set(['P','DIV','H1','H2','H4','H5','LI','BLOCKQUOTE','DD','DT','CENTER']);
  const w = document.createTreeWalker(main, NodeFilter.SHOW_TEXT);
  const rows = [];
  for (let n; (n = w.nextNode());) {
    if (!/[^\s\u3000]/.test(n.textContent)) continue;
    if (n.parentElement.closest('h3, table, #pagetop, #hpb-nav')) continue;
    const cs = getComputedStyle(n.parentElement);
    if (cs.display === 'none' || cs.visibility === 'hidden') continue;
    let par = n.parentElement;
    while (par && par !== main && !BLOCK.has(par.tagName)) par = par.parentElement;
    if (!par) continue;
    const rg = document.createRange(); rg.selectNodeContents(n);
    const rc = [...rg.getClientRects()].filter(x => x.width > 0 && x.height > 0);
    if (!rc.length) continue;
    const top = Math.min(...rc.map(x => x.top));
    const left = Math.min(...rc.filter(x => x.top < top + 3).map(x => x.left));
    rows.push({ left: Math.round((left - ml) * 10) / 10, tag: par.tagName, id: par.id || '',
      y: Math.round(top + window.scrollY), t: n.textContent.replace(/\s+/g, '').slice(0, 22) });
  }
  return rows;
};
const b = await chromium.launch({ channel: 'chrome' });
const p = await b.newPage({ viewport: { width: 1280, height: 900 } });
const all = {};
for (const slug of slugs) {
  await p.goto(`https://www.teachingbeauty.jp/${slug === 'home' ? '' : slug + '.html'}`, { waitUntil: 'domcontentloaded' });
  if (process.env.TB_LOCAL_CSS) {
    await p.evaluate(() => document.querySelectorAll('link[href*="enhance.css"]').forEach(l => l.remove()));
    await p.addStyleTag({ content: fs.readFileSync(process.env.TB_LOCAL_CSS, 'utf8') });
    await p.waitForTimeout(300);
  }
  const rows = await p.evaluate(MEASURE);
  const cnt = {};
  for (const x of rows) cnt[x.left] = (cnt[x.left] || 0) + 1;
  // 段落の左端になりうる値だけ（0〜60px）で多数決
  const keys = Object.keys(cnt).filter(k => +k >= 0 && +k < 60).sort((a, c) => cnt[c] - cnt[a]);
  const dom = keys[0];
  const odd = rows.filter(x => x.left >= 0 && x.left < 60 && String(x.left) !== dom);
  all[slug] = { dom, cnt, odd: odd.length, total: rows.length };
  const kinds = [...new Set(odd.map(x => x.left))].sort((a, c) => a - c);
  console.log(`${slug.padEnd(13)} 主 ${dom}px×${cnt[dom]}/${rows.length}  ${odd.length ? 'ズレ ' + odd.length + '本 → ' + kinds.join('/') + 'px' : 'そろっている'}`);
}
fs.writeFileSync('_survey.json', JSON.stringify(all, null, 1));
await b.close();
