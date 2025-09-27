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
    else if (a === '--spread') opts.spread = true; // spread mode
    else if (a === '--suffix') opts.suffix = args[++i]; // output suffix
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
      
    case 'image-caption-only':
      return generateImageBlock(data.image, data.caption);
      
    case 'image-caption-3-medium-small':
      return `
        <div class="image-grid-3">
          ${generateImageBlock(data.image_1, data.caption_1)}
          ${generateImageBlock(data.image_2, data.caption_2)}
          ${generateImageBlock(data.image_3, data.caption_3)}
        </div>
      `;
      
    case 'heading-two-columns-text':
      return `
        ${data.heading ? `<h2>${htmlEscape(data.heading)}</h2>` : ''}
        <div class="two-columns">
          <div class="column-left">
            ${data.left_content ? `<p>${htmlEscape(data.left_content)}</p>` : ''}
          </div>
          <div class="column-right">
            ${data.right_content ? `<p>${htmlEscape(data.right_content)}</p>` : ''}
          </div>
        </div>
      `;
      
    case 'heading-text-image-1':
      return `
        ${data.heading ? `<h2>${htmlEscape(data.heading)}</h2>` : ''}
        <div class="text-image-layout">
          <div class="text-content">
            ${data.left_content ? `<p>${htmlEscape(data.left_content)}</p>` : ''}
          </div>
          <div class="image-content">
            ${generateImageBlock(data.image, data.caption)}
          </div>
        </div>
      `;
      
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

function renderHTML(data, spread = false, nextPageData = null, leftPageData = null, rightPageData = null) {
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
      @page { 
        size: ${spread ? 'A3 landscape' : 'A4 landscape'}; 
        margin: ${spread ? '8mm' : '14mm'}; 
      }
      body { 
        font-family: 'Noto Sans JP', 'Hiragino Sans', 'Hiragino Kaku Gothic ProN', 'Meiryo', sans-serif; 
        color: #111; 
        line-height: 1.6;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        ${spread ? 'font-size: 12px;' : ''}
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
      
      /* 見開きモード用のスタイル */
      ${spread ? `
      .spread-layout {
        display: flex;
        width: 100%;
        height: 100vh;
        gap: 0;
        margin: 0;
        padding: 0;
      }
      .spread-page {
        flex: 1;
        width: 50%;
        padding: 12mm;
        box-sizing: border-box;
        border-right: 2px solid #e0e0e0;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
      }
      .spread-page:last-child {
        border-right: none;
        border-left: 2px solid #e0e0e0;
      }
      .spread-page h1 {
        font-size: 20px;
        margin: 0 0 8px;
        font-weight: 600;
      }
      .spread-page h2 {
        font-size: 18px;
        margin: 12px 0 6px;
        font-weight: 500;
      }
      .spread-page h3 {
        font-size: 14px;
        margin: 10px 0 4px;
        font-weight: 500;
      }
      .spread-page p {
        font-size: 11px;
        line-height: 1.6;
        margin: 0 0 10px;
      }
      .spread-page img {
        max-width: 100%;
        max-height: 180px;
        object-fit: contain;
      }
      .spread-page .grid {
        grid-template-columns: 1fr;
        gap: 8px;
      }
      .spread-page .image-grid-2 {
        grid-template-columns: 1fr 1fr;
        gap: 6px;
      }
      .spread-page .image-grid-3 {
        grid-template-columns: 1fr 1fr;
        gap: 6px;
      }
      .spread-page figcaption {
        font-size: 9px;
        margin-top: 4px;
      }
      .spread-page .timestamp {
        font-size: 9px;
        color: #888;
        margin-bottom: 8px;
      }
      .spread-page .timeline-table {
        font-size: 10px;
      }
      .spread-page .timeline-table th,
      .spread-page .timeline-table td {
        padding: 3px 6px;
      }
      ` : ''}
    </style>
  </head>
  <body>
    ${spread ? `
    <div class="spread-layout">
      <div class="spread-page">
        ${leftPageData ? `
        <div class="timestamp">最終更新: ${jstTimestamp}</div>
        <h1>${htmlEscape(leftPageData.title || 'Untitled')}</h1>
        ${generateTemplateContent(leftPageData, leftPageData.template || 'text-photo2')}
        ` : `
        <div class="timestamp">最終更新: ${jstTimestamp}</div>
        <h1>左ページがありません</h1>
        <p>見開きの左ページが見つかりませんでした。</p>
        `}
      </div>
      <div class="spread-page">
        ${rightPageData ? `
        <div class="timestamp">最終更新: ${jstTimestamp}</div>
        <h1>${htmlEscape(rightPageData.title || 'Untitled')}</h1>
        ${generateTemplateContent(rightPageData, rightPageData.template || 'text-photo2')}
        ` : `
        <div class="timestamp">最終更新: ${jstTimestamp}</div>
        <h1>右ページがありません</h1>
        <p>見開きの右ページが見つかりませんでした。</p>
        `}
      </div>
    </div>
    ` : `
    <section class="page">
      <div class="timestamp">最終更新: ${jstTimestamp}</div>
      <h1>${title}</h1>
      ${generateTemplateContent(data, template)}
    </section>
    `}
  </body>
  </html>`;
}

async function maybeCreatePDF(htmlPath, pdfPath, force = false, spread = false) {
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
      
      // PDF生成（見開きモード対応）
      const pdfOptions = {
        path: pdfPath, 
        format: spread ? 'A3' : 'A4',  // 見開きモードではA3サイズ
        landscape: true,  // 見開きモードでも横向き
        printBackground: true, 
        margin: spread ? 
          { top: 8, right: 8, bottom: 8, left: 8 } : 
          { top: 14, right: 14, bottom: 14, left: 14 },
        preferCSSPageSize: true
      };
      
      if (spread) {
        console.log('📖 Generating spread PDF in A3 landscape mode');
      }
      
      await page.pdf(pdfOptions);
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
  const { json, out = 'out', pdf: forcePdf, suffix } = parseArgs();
  let spread = parseArgs().spread; // letで宣言して後で変更可能にする
  if (!json) {
    console.error('Usage: node scripts/build-one.js --json <content-json> [--out out] [--pdf] [--spread] [--suffix <suffix>]');
    process.exit(1);
  }
  const raw = fs.readFileSync(json, 'utf-8');
  const data = JSON.parse(raw);
  
  // 見開きモードの場合、次のページのデータも読み込む
  let nextPageData = null;
  let leftPageData = null;
  let rightPageData = null;
  
  if (spread) {
    console.log('📖 Spread mode enabled');
    if (suffix) {
      console.log(`📝 Output suffix: ${suffix}`);
    }
    
    // 見開きページの計算（pdf_page_numberベースで偶数ページを左、奇数ページを右に配置）
    const currentPageNumber = parseInt(data.pdf_page_number || data.id);
    const currentPageId = parseInt(data.id);
    let leftPageNumber, rightPageNumber;
    
    if (currentPageNumber % 2 === 0) {
      // 偶数ページ番号の場合：現在のページが左、次のページが右
      leftPageNumber = currentPageNumber;
      rightPageNumber = currentPageNumber + 1;
    } else {
      // 奇数ページ番号の場合：前のページが左、現在のページが右
      leftPageNumber = currentPageNumber - 1;
      rightPageNumber = currentPageNumber;
    }
    
    console.log(`🔍 Current page ID: ${currentPageId}, PDF page number: ${currentPageNumber}`);
    console.log(`🔍 Left page number: ${leftPageNumber}, Right page number: ${rightPageNumber}`);
    
    // 隣接ページのファイルを探す（全てのcontent-*.jsonファイルを確認）
    const dir = path.dirname(json);
    const allFiles = fs.readdirSync(dir).filter(f => f.startsWith('content-') && f.endsWith('.json'));
    
    console.log(`🔍 Searching for adjacent pages in directory: ${dir}`);
    console.log(`📁 Available content files: ${allFiles.length} files`);
    
    let leftPageFile = null, rightPageFile = null;
    
    for (const file of allFiles) {
      try {
        const filePath = path.join(dir, file);
        const fileData = JSON.parse(fs.readFileSync(filePath, 'utf-8'));
        const filePageNumber = parseInt(fileData.pdf_page_number || fileData.id);
        
        console.log(`   📄 ${file}: page number ${filePageNumber} (title: ${fileData.title || 'Untitled'})`);
        
        if (filePageNumber === leftPageNumber) {
          leftPageFile = filePath;
          console.log(`   ✅ → Selected as LEFT page (${filePageNumber})`);
        } else if (filePageNumber === rightPageNumber) {
          rightPageFile = filePath;
          console.log(`   ✅ → Selected as RIGHT page (${filePageNumber})`);
        }
      } catch (e) {
        console.warn(`   ⚠️ Could not read file ${file}: ${e.message}`);
      }
    }
    
    console.log(`🎯 Search results: Left page file: ${leftPageFile ? 'found' : 'NOT FOUND'}, Right page file: ${rightPageFile ? 'found' : 'NOT FOUND'}`);
    console.log('');
    
    // 左ページのデータを読み込み
    if (leftPageFile && leftPageNumber !== currentPageNumber) {
      const leftRaw = fs.readFileSync(leftPageFile, 'utf-8');
      leftPageData = JSON.parse(leftRaw);
      console.log(`📄 Left page loaded: ${leftPageData.title || 'Untitled'} (page number: ${leftPageNumber})`);
    } else if (leftPageNumber === currentPageNumber) {
      leftPageData = data;
      console.log(`📄 Left page is current page: ${currentPageNumber}`);
    }
    
    // 右ページのデータを読み込み
    if (rightPageFile && rightPageNumber !== currentPageNumber) {
      const rightRaw = fs.readFileSync(rightPageFile, 'utf-8');
      rightPageData = JSON.parse(rightRaw);
      console.log(`📄 Right page loaded: ${rightPageData.title || 'Untitled'} (page number: ${rightPageNumber})`);
    } else if (rightPageNumber === currentPageNumber) {
      rightPageData = data;
      console.log(`📄 Right page is current page: ${currentPageNumber}`);
    }
    
    // nextPageDataを適切に設定（後方互換性のため）
    if (currentPageNumber % 2 === 0) {
      nextPageData = rightPageData;
    } else {
      nextPageData = leftPageData;
    }
    
    if (!leftPageData || !rightPageData) {
      console.log(`⚠️ Missing page data - Left: ${!!leftPageData}, Right: ${!!rightPageData}`);
      console.log(`🔍 Available files and their page numbers:`);
      allFiles.forEach(file => {
        try {
          const filePath = path.join(dir, file);
          const fileData = JSON.parse(fs.readFileSync(filePath, 'utf-8'));
          const filePageNumber = parseInt(fileData.pdf_page_number || fileData.id);
          console.log(`  - ${file}: page number ${filePageNumber}`);
        } catch (e) {
          console.log(`  - ${file}: could not read`);
        }
      });
      
      // 隣接ページが見つからない場合の処理
      if (!leftPageData || !rightPageData) {
        console.log(`⚠️ WARNING: Missing adjacent page for spread layout`);
        console.log(`   - Left page: ${leftPageData ? 'found' : 'MISSING'}`);
        console.log(`   - Right page: ${rightPageData ? 'found' : 'MISSING'}`);
        
        // 見開きに必要な隣接ページが見つからない場合は、見開きモードを無効化
        console.log(`❌ Cannot create proper spread layout - disabling spread mode`);
        console.log(`📄 Falling back to single page layout`);
        
        // 見開きモードを無効化して単独ページとして処理
        spread = false;
        leftPageData = null;
        rightPageData = null;
        nextPageData = null;
      }
    }
  }
  
  // ファイル名はID名を使用（日本語エンコード問題を回避）
  let filename = String(data.id || data.slug || 'page');
  
  // 見開きモード用のサフィックスを追加
  if (spread && suffix) {
    filename += suffix;
  }

  ensureDir(out);
  
  // デバッグ用：PDFレイアウトのログ出力
  console.log('');
  if (spread) {
    console.log('📖 ========== SPREAD PDF LAYOUT DEBUG ==========');
    console.log(`🎯 Current page: ${data.title || 'Untitled'}`);
    console.log(`   - ID: ${data.id}`);
    console.log(`   - PDF Page Number: ${data.pdf_page_number || 'not set'}`);
    console.log('');
    console.log('📄 Left page (偶数ページ):');
    if (leftPageData) {
      console.log(`   - Title: ${leftPageData.title || 'Untitled'}`);
      console.log(`   - ID: ${leftPageData.id}`);
      console.log(`   - PDF Page Number: ${leftPageData.pdf_page_number || 'not set'}`);
      console.log(`   - Template: ${leftPageData.template || 'not set'}`);
    } else {
      console.log('   - ❌ No left page data available');
    }
    console.log('');
    console.log('📄 Right page (奇数ページ):');
    if (rightPageData) {
      console.log(`   - Title: ${rightPageData.title || 'Untitled'}`);
      console.log(`   - ID: ${rightPageData.id}`);
      console.log(`   - PDF Page Number: ${rightPageData.pdf_page_number || 'not set'}`);
      console.log(`   - Template: ${rightPageData.template || 'not set'}`);
    } else {
      console.log('   - ❌ No right page data available');
    }
    console.log('');
    console.log('📋 Spread layout summary:');
    const leftPageNum = leftPageData ? (leftPageData.pdf_page_number || leftPageData.id) : 'N/A';
    const rightPageNum = rightPageData ? (rightPageData.pdf_page_number || rightPageData.id) : 'N/A';
    console.log(`   - Final layout: ${leftPageNum} ⇔ ${rightPageNum}`);
    console.log('================================================');
  } else {
    console.log('📄 ========== SINGLE PAGE LAYOUT DEBUG ==========');
    console.log(`🎯 Current page: ${data.title || 'Untitled'}`);
    console.log(`   - ID: ${data.id}`);
    console.log(`   - PDF Page Number: ${data.pdf_page_number || 'not set'}`);
    console.log(`   - Template: ${data.template || 'not set'}`);
    console.log(`   - Layout: A4 landscape (single page)`);
    console.log('================================================');
  }
  console.log('');
  
  const html = renderHTML(data, spread, nextPageData, spread ? leftPageData : null, spread ? rightPageData : null);
  const htmlPath = path.resolve(out, `booklet-${filename}.html`);
  const pdfPath = path.resolve(out, `booklet-${filename}.pdf`);
  fs.writeFileSync(htmlPath, html);
  console.log('📝 HTML generated:', htmlPath);

  await maybeCreatePDF(htmlPath, pdfPath, !!forcePdf, spread);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
