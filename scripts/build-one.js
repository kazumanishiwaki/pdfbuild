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

// テンプレート別コンテンツ生成
function generateTemplateContent(data, template) {
  switch (template) {
    case 'heading-text':
      return `
        ${data.heading ? `<h2>${htmlEscape(data.heading)}</h2>` : ''}
        ${data.content ? `<p>${htmlEscape(data.content)}</p>` : ''}
      `;
      
    case 'main-heading-2':
      return `
        ${data.main_heading ? `<h2>${htmlEscape(data.main_heading)}</h2>` : ''}
        ${generateSection(data.section_1)}
        ${generateSection(data.section_2)}
      `;
      
    case 'main-heading-3':
      return `
        ${data.main_heading ? `<h2>${htmlEscape(data.main_heading)}</h2>` : ''}
        ${generateSection(data.section_1)}
        ${generateSection(data.section_2)}
        ${generateSection(data.section_3)}
      `;
      
    case 'image-caption-1':
      return generateImageBlock(data.image, data.caption);
      
    case 'image-caption-2':
      return `
        <div class="grid">
          ${generateImageBlock(data.image_1, data.caption_1)}
          ${generateImageBlock(data.image_2, data.caption_2)}
        </div>
      `;
      
    case 'image-caption-3':
      return `
        <div class="image-grid-3">
          ${generateImageBlock(data.image_1, data.caption_1)}
          ${generateImageBlock(data.image_2, data.caption_2)}
          ${generateImageBlock(data.image_3, data.caption_3)}
        </div>
      `;
      
    case 'image-caption-4':
      return `
        <div class="grid">
          ${generateImageBlock(data.image_1, data.caption_1)}
          ${generateImageBlock(data.image_2, data.caption_2)}
          ${generateImageBlock(data.image_3, data.caption_3)}
          ${generateImageBlock(data.image_4, data.caption_4)}
        </div>
      `;
      
    case 'timeline':
      return generateTimeline(data.timeline_title, data.timeline_items);
      
    case 'text-photo2':
    default:
      // 後方互換性：既存のtext-photo2テンプレート
      const content = htmlEscape(data.content || '');
      const p1 = data.photo1?.url || '';
      const c1 = htmlEscape(data.caption1 || '');
      const p2 = data.photo2?.url || '';
      const c2 = htmlEscape(data.caption2 || '');
      
      return `
        ${content ? `<p>${content}</p>` : ''}
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
      `;
  }
}

// セクション生成（見出し+本文）
function generateSection(section) {
  if (!section) return '';
  return `
    <div class="section">
      ${section.heading ? `<h3>${htmlEscape(section.heading)}</h3>` : ''}
      ${section.content ? `<p>${htmlEscape(section.content)}</p>` : ''}
    </div>
  `;
}

// 画像ブロック生成
function generateImageBlock(image, caption) {
  if (!image?.url) return '<figure></figure>';
  return `
    <figure>
      <img src="${image.url}" alt="${htmlEscape(image.alt || '')}" />
      ${caption ? `<figcaption>${htmlEscape(caption)}</figcaption>` : ''}
    </figure>
  `;
}

// 年表生成
function generateTimeline(title, items) {
  if (!items || !Array.isArray(items)) return '';
  
  return `
    ${title ? `<h2>${htmlEscape(title)}</h2>` : ''}
    <table class="timeline-table">
      <thead>
        <tr>
          <th>年</th>
          <th>月</th>
          <th>出来事</th>
        </tr>
      </thead>
      <tbody>
        ${items.map(item => `
          <tr>
            <td>${item.year || ''}</td>
            <td>${item.month ? item.month + '月' : ''}</td>
            <td>${htmlEscape(item.event || '')}</td>
          </tr>
        `).join('')}
      </tbody>
    </table>
  `;
}

function renderHTML(data) {
  const title = htmlEscape(data.title || 'Untitled');
  const template = data.template || 'text-photo2';
  
  // 日本時間でのタイムスタンプを生成（WordPressページの更新日時を優先）
  const modifiedDate = data.modified ? new Date(data.modified) : new Date();
  const jstTimestamp = new Intl.DateTimeFormat('ja-JP', {
    timeZone: 'Asia/Tokyo',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false
  }).format(modifiedDate).replace(/\//g, '-');

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
      .timestamp { 
        font-size: 12px; 
        color: #666; 
        text-align: right; 
        margin-bottom: 20px; 
        border-bottom: 1px solid #eee; 
        padding-bottom: 10px; 
      }
      h2 { font-size: 24px; margin: 20px 0 12px; font-weight: 500; color: #333; }
      h3 { font-size: 18px; margin: 16px 0 8px; font-weight: 500; color: #444; }
      .section { margin: 20px 0; }
      .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; }
      .image-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-top: 20px; }
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
      .timeline-table { 
        width: 100%; 
        border-collapse: collapse; 
        margin-top: 20px; 
        font-size: 14px;
      }
      .timeline-table th, .timeline-table td { 
        border: 1px solid #ddd; 
        padding: 8px 12px; 
        text-align: left; 
      }
      .timeline-table th { 
        background-color: #f5f5f5; 
        font-weight: 500; 
      }
      .timeline-table td:first-child { 
        width: 80px; 
        text-align: center; 
        font-weight: 500; 
      }
      .timeline-table td:nth-child(2) { 
        width: 60px; 
        text-align: center; 
      }
      .page { break-after: page; }
    </style>
  </head>
  <body>
    <section class="page">
      <div class="timestamp">最終更新: ${jstTimestamp}</div>
      <h1>${title}</h1>
      ${generateTemplateContent(data, template)}
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
    
    try {
      // 外部リソース（フォント・画像）の読み込みを待つ
      await page.goto(fileUrl.href, { 
        waitUntil: 'networkidle0',  // ネットワークが2秒間アイドル状態になるまで待つ
        timeout: 30000  // 30秒でタイムアウト
      });
      
      // フォントの読み込み完了を待つ
      await page.evaluateHandle('document.fonts.ready');
      
      // 少し待ってから画像の読み込み状況を確認
      await new Promise(resolve => setTimeout(resolve, 2000));
      
      console.log('✅ Page loaded successfully, generating PDF...');
      
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
      
    } catch (pageError) {
      console.error('❌ Error during page processing:', pageError.message);
      console.error('🔍 Stack trace:', pageError.stack);
      throw pageError;
    }
    
  } catch (browserError) {
    console.error('❌ Browser error:', browserError.message);
    throw browserError;
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
