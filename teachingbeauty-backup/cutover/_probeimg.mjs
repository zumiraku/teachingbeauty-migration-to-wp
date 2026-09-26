import { chromium } from 'playwright';
const src = process.argv[2];
const b = await chromium.launch({ channel: 'chrome' });
const p = await b.newPage();
const url = 'file:///' + src.split('\').join('/');
await p.setContent(`<img id="i" src="${url}">`);
await p.waitForTimeout(800);
console.log(await p.evaluate(() => { const i = document.getElementById('i'); return { w: i.naturalWidth, h: i.naturalHeight, complete: i.complete }; }));
await b.close();
