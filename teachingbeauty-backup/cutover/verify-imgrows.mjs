// 横に並ぶ写真の行が折り返していないかを全ページで確かめる。
//   node verify-imgrows.mjs ../../teachingbeauty-theme/enhance.css
//
// 本文に左の余白を足すと行の幅が縮み、350+340=690px のような写真2枚組が
// 収まらなくなって縦に割れる。指定した CSS を当てた状態と当てる前を見比べ、
// 同じ写真の組が同じ行に残っているかで判定する。
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { chromium } from 'playwright';
const here = path.dirname(fileURLToPath(import.meta.url));
const wxr = fs.readFileSync(path.join(here, '..', 'teachingbeauty-fullbody.wxr'), 'utf8');
const slugs = [...wxr.matchAll(/<wp:post_name><!\[CDATA\[([^\]]+)\]\]>/g)].map(m => m[1]);
const cssFileName = process.argv[ 2 ] || "../../teachingbeauty-theme/enhance.css";
const css = fs.readFileSync(path.join(here, cssFileName), 'utf8');
const ROWS = () => {
  const main = document.querySelector('#hpb-main');
  const ml = main.getBoundingClientRect().left;
  const imgs = [...main.querySelectorAll('img')].filter(e => { const b = e.getBoundingClientRect(); return b.width > 1 && b.height > 1; })
    .map(e => { const b = e.getBoundingClientRect(); return { src: (e.getAttribute('src') || '').split('/').pop(), t: Math.round(b.top + window.scrollY), l: Math.round((b.left - ml) * 10) / 10, r: Math.round((b.right - ml) * 10) / 10 }; });
  const rows = [];
  for (const i of imgs) { const g = rows.find(x => Math.abs(x.t - i.t) < 20); if (g) { g.n++; g.srcs.push(i.src); g.r = Math.max(g.r, i.r); } else rows.push({ t: i.t, r: i.r, n: 1, srcs: [i.src] }); }
  return { rows: rows.map(r => ({ key: r.srcs.slice().sort().join('|'), n: r.n, r: r.r })), mainR: Math.round(main.getBoundingClientRect().right - ml) };
};
const b = await chromium.launch({ channel: 'chrome' });
let broke = 0, oversz = 0;
for (const slug of slugs) {
  const p = await b.newPage({ viewport: { width: 1280, height: 900 } });
  const u = `https://www.teachingbeauty.jp/${slug === 'home' ? '' : slug + '.html'}`;
  try { await p.goto(u, { waitUntil: 'load', timeout: 60000 }); }
  catch (e) { await p.goto(u, { waitUntil: 'domcontentloaded', timeout: 60000 }); await p.waitForTimeout(1500); }
  await p.waitForTimeout(600);
  const A = await p.evaluate(ROWS);
    // 本番の enhance.css を外してから手元のファイルを当てる（上書きではなく差し替え）
  if (/enhance.css$/.test(cssFileName)) { await p.evaluate(() => { document.querySelectorAll('link[href*="enhance.css"]').forEach(l => l.remove()); }); }
  await p.addStyleTag({ content: css });
  await p.waitForTimeout(400);
  const B = await p.evaluate(ROWS);
  await p.close();
  // 横並びだった行が分かれていないか（画像の組み合わせで照合）
  const bad = [];
  for (const a of A.rows.filter(x => x.n > 1)) {
    const m = B.rows.find(x => x.key === a.key);
    if (!m || m.n < a.n) bad.push(`${a.n}枚の行が ${m ? m.n : 1}枚に分かれた (${a.key.slice(0, 40)})`);
  }
  const ovA = A.rows.filter(x => x.r > A.mainR + 0.6).length, ovB = B.rows.filter(x => x.r > B.mainR + 0.6).length;
  if (bad.length) broke += bad.length;
  if (ovB > ovA) oversz++;
  console.log(`${bad.length || ovB > ovA ? 'NG' : 'OK'} ${slug.padEnd(13)} 横並び ${A.rows.filter(x => x.n > 1).length}行  右はみ出し ${ovA}→${ovB}  ${bad.join(' / ') || ''}`);
}
await b.close();
console.log(`\n折り返した行 ${broke} / はみ出しが増えたページ ${oversz}`);
