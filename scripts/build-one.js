#!/usr/bin/env node
/*
  Simple local builder: content JSON -> HTML, and optionally PDF via Puppeteer.
  Usage:
    node scripts/build-one.js --json examples/dummy/content-demo.json --out out
*/

import fs from 'node:fs';
import path from 'node:path';
import url from 'node:url';
import os from 'node:os';

const __dirname = path.dirname(url.fileURLToPath(import.meta.url));

function parseArgs() {
  const args = process.argv.slice(2);
  const opts = {};
  for (let i = 0; i < args.length; i++) {
    const a = args[i];
    if (a === '--json') opts.json = args[++i];
    else if (a === '--out') opts.out = args[++i];
    else if (a === '--pdf') opts.pdf = true; // force PDF attempt
  }
  return opts;
}

function ensureDir(p) {
  fs.mkdirSync(p, { recursive: true });
}

function htmlEscape(s = '') {
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function renderHTML(data) {
  const title = htmlEscape(data.title || 'Untitled');
  const content = htmlEscape(data.content || '');
  const p1 = data.photo1?.url || '';
  const c1 = htmlEscape(data.caption1 || '');
  const p2 = data.photo2?.url || '';
  const c2 = htmlEscape(data.caption2 || '');

  // A4 landscape-ish print-friendly styles
  return `<!doctype html>
  <html lang="ja">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>${title}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
      @page { size: A4 landscape; margin: 14mm; }
      body { 
        font-family: 'Noto Sans JP', 'Hiragino Sans', 'Hiragino Kaku Gothic ProN', 'Meiryo', sans-serif; 
        color: #111; 
        line-height: 1.6;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
      }
      h1 { font-size: 28px; margin: 0 0 12px; font-weight: 500; }
      p { line-height: 1.8; margin: 0 0 16px; }
      .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; }
      figure { margin: 0; }
      figcaption { font-size: 12px; color: #555; margin-top: 8px; text-align: center; }
      img { 
        width: 100%; 
        height: auto; 
        border: 1px solid #ddd; 
        border-radius: 4px;
        max-height: 300px;
        object-fit: cover;
      }
      .page { break-after: page; }
    </style>
  </head>
  <body>
    <section class="page">
      <h1>${title}</h1>
      <p>${content}</p>
      <div class="grid">
        <figure>
          ${p1 ? `<img src="${p1}" alt="" />` : ''}
          ${c1 ? `<figcaption>${c1}</figcaption>` : ''}
        </figure>
        <figure>
          ${p2 ? `<img src="${p2}" alt="" />` : ''}
          ${c2 ? `<figcaption>${c2}</figcaption>` : ''}
        </figure>
      </div>
    </section>
  </body>
  </html>`;
}

async function maybeCreatePDF(htmlPath, pdfPath, force = false) {
  let puppeteer;
  try {
    puppeteer = await import('puppeteer');
  } catch (e) {
    if (force) {
      throw new Error('Puppeteer がインストールされていません。`npm i -D puppeteer` を実行してください。');
    }
    console.warn('ℹ️ Puppeteer 未導入のため PDF をスキップしました。`npm i -D puppeteer` で有効化できます。');
    return;
  }

  // Prepare a temporary user data dir to avoid crashpad issues in sandboxed envs
  const tmpProfile = fs.mkdtempSync(path.join(os.tmpdir(), 'pptr-profile-'));
  const execPath = process.env.PUPPETEER_EXECUTABLE_PATH || process.env.CHROME_PATH;
  const browser = await puppeteer.default.launch({
    headless: true,
    executablePath: execPath,
    // Some CI-friendly flags
    args: [
      '--no-sandbox',
      '--disable-setuid-sandbox',
      '--disable-dev-shm-usage',
      '--no-zygote',
      '--disable-gpu',
      '--disable-features=VizDisplayCompositor,Crashpad',
      '--disable-crash-reporter',
      '--no-crash-upload',
      `--user-data-dir=${tmpProfile}`
    ]
  });
  try {
    const page = await browser.newPage();
    
    // ファイルパスを正しいfile:// URLに変換
    const fileUrl = new URL('file://');
    fileUrl.pathname = path.resolve(htmlPath);
    
    console.log('🔗 Loading HTML:', fileUrl.href);
    
    // 外部リソース（フォント・画像）の読み込みを待つ
    await page.goto(fileUrl.href, { 
      waitUntil: 'networkidle0',  // ネットワークが2秒間アイドル状態になるまで待つ
      timeout: 30000  // 30秒でタイムアウト
    });
    
    // フォントの読み込み完了を待つ
    await page.evaluateHandle('document.fonts.ready');
    
    // 少し待ってから画像の読み込み状況を確認
    await page.waitForTimeout(2000);
    
    // PDF生成
    await page.pdf({ 
      path: pdfPath, 
      format: 'A4', 
      landscape: true, 
      printBackground: true, 
      margin: { top: 14, right: 14, bottom: 14, left: 14 },
      preferCSSPageSize: true
    });
    console.log('📄 PDF generated:', pdfPath);
  } finally {
    await browser.close();
  }
}

async function main() {
  const { json, out = 'out', pdf: forcePdf } = parseArgs();
  if (!json) {
    console.error('Usage: node scripts/build-one.js --json <content-json> [--out out] [--pdf]');
    process.exit(1);
  }
  const raw = fs.readFileSync(json, 'utf-8');
  const data = JSON.parse(raw);
  // ファイル名はID名を使用（日本語エンコード問題を回避）
  const filename = String(data.id || data.slug || 'page');

  ensureDir(out);
  const html = renderHTML(data);
  const htmlPath = path.resolve(out, `booklet-${filename}.html`);
  const pdfPath = path.resolve(out, `booklet-${filename}.pdf`);
  fs.writeFileSync(htmlPath, html);
  console.log('📝 HTML generated:', htmlPath);

  await maybeCreatePDF(htmlPath, pdfPath, !!forcePdf);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
