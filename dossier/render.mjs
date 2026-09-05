// Genera public/dossier-mae-hong-son-loop.pdf a partir de dossier/index.html con Chromium.
import { chromium } from 'playwright-core';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
const here = path.dirname(fileURLToPath(import.meta.url));
const exe = process.env.CHROMIUM_PATH || '/opt/pw-browsers/chromium';
const b = await chromium.launch({ executablePath: exe });
const p = await b.newPage();
await p.goto('file://' + path.join(here, 'index.html'), { waitUntil: 'networkidle' });
await p.evaluate(() => document.fonts.ready);
await p.pdf({ path: path.join(here, '..', 'public', 'dossier-mae-hong-son-loop.pdf'), format: 'A4', printBackground: true, preferCSSPageSize: true, margin: { top: 0, right: 0, bottom: 0, left: 0 } });
// capturas por página para revisar
const pages = await p.$$('.page');
for (const [i, el] of pages.entries()) await el.screenshot({ path: `/tmp/dossier-${i + 1}.png` });
await b.close();
console.log('pdf ok, pages', pages.length);
