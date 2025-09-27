#!/usr/bin/env node
/*
  Fetch real WordPress data for PDF build.
  Usage: node scripts/fetch-acf.js "123,456"
  Behavior:
    - Requires WP_URL. Tries wp/v2 pages first, then acf/v3 for ACF fields.
    - Resolves numeric image IDs to media objects when possible.
    - If fetch fails: when ALLOW_DUMMY=1 writes a dummy item; otherwise exits 1.
  Output files:
    - id-slug-map.json
    - content-<slug>.json
*/

import fs from 'node:fs';
import path from 'node:path';
import https from 'node:https';
import http from 'node:http';

function httpGet(url, headers = {}) {
  return new Promise((resolve, reject) => {
    // ActionsのUAがBot判定されがち。ブラウザ寄りに偽装
    const debugHeaders = {
      "Accept": "application/json",
      "User-Agent": "Mozilla/5.0 (GitHub Actions ACF fetcher)",
      ...headers
    };
    
    const lib = url.startsWith('https') ? https : http;
    const req = lib.get(url, { headers: debugHeaders }, (res) => {
      let data = '';
      res.on('data', (c) => (data += c));
      res.on('end', () => {
        if (res.statusCode && res.statusCode >= 200 && res.statusCode < 300) {
          resolve({ status: res.statusCode, data });
        } else {
          console.error(`← Status: ${res.statusCode} ${res.statusMessage}`);
          resolve({ status: res.statusCode || 0, data });
        }
      });
    });
    req.on('error', (err) => {
      console.error(`← Error: ${err.message}`);
      reject(err);
    });
  });
}

function writeJSON(file, obj) {
  fs.writeFileSync(file, JSON.stringify(obj, null, 2));
}

function dummy() {
  const slug = 'demo';
  const id = 123;
  writeJSON('id-slug-map.json', { [String(id)]: slug, [slug]: id });
  writeJSON(`content-${slug}.json`, {
    id,
    slug,
    template: 'text-photo2',
    title: 'Demo Booklet',
    content: 'これはダミーの本文です（CIブートストラップ用）。',
    photo1: {
      url: 'data:image/svg+xml;utf8,<?xml version="1.0" encoding="UTF-8"?><svg xmlns="http://www.w3.org/2000/svg" width="800" height="450"><rect width="100%" height="100%" fill="%23e5e7eb"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-size="40" fill="%236b7280">PHOTO 1</text></svg>'
    },
    caption1: '写真1のキャプション（ダミー）',
    photo2: {
      url: 'data:image/svg+xml;utf8,<?xml version="1.0" encoding="UTF-8"?><svg xmlns="http://www.w3.org/2000/svg" width="800" height="450"><rect width="100%" height="100%" fill="%23e5e7eb"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-size="40" fill="%236b7280">PHOTO 2</text></svg>'
    },
    caption2: '写真2のキャプション（ダミー）',
    modified: new Date().toISOString(),
    // 共通フィールド
    pdf_page_number: 1
  });
}

async function getJSON(url, headers) {
  const { status, data } = await httpGet(url, headers);
  if (status >= 200 && status < 300) return JSON.parse(data);
  const err = new Error(`HTTP ${status} for ${url}`);
  err.status = status;
  err.body = data;
  console.error(`❌ JSON parse failed for ${url}: ${status}`);
  console.error(`❌ Response body: ${data.slice(0, 1000)}`);
  throw err;
}

function buildAuthHeadersFromEnv() {
  const WP_JWT = process.env.WP_JWT || '';
  const WP_BASIC_USER = process.env.WP_BASIC_USER || process.env.WP_APP_USER || '';
  const WP_BASIC_PASS = process.env.WP_BASIC_PASS || process.env.WP_APP_PASS || '';
  
  if (WP_JWT) {
    return { 'Authorization': `Bearer ${WP_JWT}` };
  } else if (WP_BASIC_USER && WP_BASIC_PASS) {
    const token = Buffer.from(`${WP_BASIC_USER}:${WP_BASIC_PASS}`).toString('base64');
    return { 'Authorization': `Basic ${token}` };
  }
  return {};
}

async function fetchPage(id, base, headers = {}) {
  const root = base.replace(/\/$/, '');
  const pathUrl = `${root}/wp-json/wp/v2/pages/${encodeURIComponent(id)}?_embed`; // 公開はまず無認証で
  const queryUrl = `${root}/index.php?rest_route=/wp/v2/pages/${encodeURIComponent(id)}&_embed`;
  
  try {
    return await getJSON(pathUrl, headers);
  } catch (error) {
    // WAFブロック検出（XSERVERなど）
    const wafBlocked = error.status === 403 && /XSERVER Inc\./i.test(error.body || '');
    if (wafBlocked) {
      console.warn(`⚠️ WAF block detected: retrying via query route`);
      try {
        return await getJSON(queryUrl, headers);
      } catch (queryError) {
        console.warn(`⚠️ Query route also failed: ${queryError.status}`);
        // Query route failed, proceed to auth retry
        error = queryError;
      }
    }
    
    if (error.status === 403 || error.status === 401) {
      // 403/401時のみ認証でリトライ（未公開やREST制限時に備える）
      console.log(`🔄 Retrying with auth due to ${error.status} error...`);
      const authHeaders = buildAuthHeadersFromEnv();
      const authPathUrl = `${root}/wp-json/wp/v2/pages/${encodeURIComponent(id)}?context=edit&_embed`;
      const authQueryUrl = `${root}/index.php?rest_route=/wp/v2/pages/${encodeURIComponent(id)}&context=edit&_embed`;
      
      try {
        return await getJSON(authPathUrl, { ...headers, ...authHeaders });
      } catch (authError) {
        // Path auth failed, try query auth
        const authWafBlocked = authError.status === 403 && /XSERVER Inc\./i.test(authError.body || '');
        if (authWafBlocked) {
          console.warn(`⚠️ Auth WAF block: retrying via auth query route`);
          return await getJSON(authQueryUrl, { ...headers, ...authHeaders });
        }
        throw authError;
      }
    }
    throw error;
  }
}

async function fetchACF(id, base, headers = {}) {
  // Requires ACF to REST API plugin
  const root = base.replace(/\/$/, '');
  const pathUrl = `${root}/wp-json/acf/v3/pages/${encodeURIComponent(id)}`;
  const queryUrl = `${root}/index.php?rest_route=/acf/v3/pages/${encodeURIComponent(id)}`;
  
  try {
    return await getJSON(pathUrl, headers);
  } catch (error) {
    // WAFブロック検出（XSERVERなど）
    const wafBlocked = error.status === 403 && /XSERVER Inc\./i.test(error.body || '');
    if (wafBlocked) {
      console.warn(`⚠️ ACF WAF block detected: retrying via query route`);
      try {
        return await getJSON(queryUrl, headers);
      } catch (queryError) {
        console.warn(`⚠️ ACF query route also failed: ${queryError.status}`);
        error = queryError;
      }
    }
    
    if (error.status === 403 || error.status === 401) {
      // 403/401時のみ認証でリトライ
      console.log(`🔄 Retrying ACF with auth due to ${error.status} error...`);
      const authHeaders = buildAuthHeadersFromEnv();
      
      try {
        return await getJSON(pathUrl, { ...headers, ...authHeaders });
      } catch (authError) {
        // Path auth failed, try query auth
        const authWafBlocked = authError.status === 403 && /XSERVER Inc\./i.test(authError.body || '');
        if (authWafBlocked) {
          console.warn(`⚠️ ACF auth WAF block: retrying via auth query route`);
          return await getJSON(queryUrl, { ...headers, ...authHeaders });
        }
        throw authError;
      }
    }
    throw error;
  }
}

async function fetchMedia(id, base, headers = {}) {
  const root = base.replace(/\/$/, '');
  const pathUrl = `${root}/wp-json/wp/v2/media/${encodeURIComponent(id)}`;
  const queryUrl = `${root}/index.php?rest_route=/wp/v2/media/${encodeURIComponent(id)}`;
  
  try {
    return await getJSON(pathUrl, headers);
  } catch (error) {
    // WAFブロック検出（XSERVERなど）
    const wafBlocked = error.status === 403 && /XSERVER Inc\./i.test(error.body || '');
    if (wafBlocked) {
      console.warn(`⚠️ Media WAF block detected: retrying via query route`);
      try {
        return await getJSON(queryUrl, headers);
      } catch (queryError) {
        console.warn(`⚠️ Media query route also failed: ${queryError.status}`);
        error = queryError;
      }
    }
    
    if (error.status === 403 || error.status === 401) {
      // 403/401時のみ認証でリトライ
      console.log(`🔄 Retrying media with auth due to ${error.status} error...`);
      const authHeaders = buildAuthHeadersFromEnv();
      
      try {
        return await getJSON(pathUrl, { ...headers, ...authHeaders });
      } catch (authError) {
        // Path auth failed, try query auth
        const authWafBlocked = authError.status === 403 && /XSERVER Inc\./i.test(authError.body || '');
        if (authWafBlocked) {
          console.warn(`⚠️ Media auth WAF block: retrying via auth query route`);
          return await getJSON(queryUrl, { ...headers, ...authHeaders });
        }
        throw authError;
      }
    }
    throw error;
  }
}

function normalizeImage(val) {
  if (!val) return null;
  
  // 既にオブジェクト形式でURLがある場合
  if (typeof val === 'object' && val.url) return val;
  
  // 数値ID（メディアID）の場合
  if (typeof val === 'number') return { id: val };
  
  // 文字列URLの場合はオブジェクト形式に変換
  if (typeof val === 'string' && val.startsWith('http')) {
    return { url: val };
  }
  
  // その他の場合はそのまま返す
  return val;
}

// テンプレートタイプを検出
function detectTemplateType(templateSlug) {
  const templateMap = {
    'template-heading-text.php': 'heading-text',
    'template-main-heading-2.php': 'main-heading-2',
    'template-main-heading-3.php': 'main-heading-3',
    'template-image-caption-1.php': 'image-caption-1',
    'template-image-caption-2.php': 'image-caption-2',
    'template-image-caption-3.php': 'image-caption-3',
    'template-image-caption-4.php': 'image-caption-4',
    'template-image-caption-only.php': 'image-caption-only',
    'template-image-caption-3-medium-small.php': 'image-caption-3-medium-small',
    'template-timeline.php': 'timeline',
    'template-heading-two-columns-text.php': 'heading-two-columns-text',
    'template-heading-text-image-1.php': 'heading-text-image-1',
    'template-heading-text-image-2.php': 'heading-text-image-2',
    'template-heading-text-image-3-large-medium.php': 'heading-text-image-3-large-medium',
    'template-heading-text-image-4-large-small.php': 'heading-text-image-4-large-small',
    'template-heading-text-image-4-medium.php': 'heading-text-image-4-medium',
    'template-heading-text-image-5-medium-small.php': 'heading-text-image-5-medium-small',
    // 後方互換性
    'template-text-photo2.php': 'text-photo2'
  };
  
  return templateMap[templateSlug] || 'default';
}

// テンプレート別フィールド処理
function processTemplateFields(content, acf, templateType) {
  switch (templateType) {
    case 'heading-text':
      content.heading = acf.heading || '';
      content.content = acf.content || '';
      break;
      
    case 'main-heading-2':
      content.main_heading = acf.main_heading || '';
      content.section_1 = acf.section_1 || { heading: '', content: '' };
      content.section_2 = acf.section_2 || { heading: '', content: '' };
      break;
      
    case 'main-heading-3':
      content.main_heading = acf.main_heading || '';
      content.section_1 = acf.section_1 || { heading: '', content: '' };
      content.section_2 = acf.section_2 || { heading: '', content: '' };
      content.section_3 = acf.section_3 || { heading: '', content: '' };
      break;
      
    case 'image-caption-1':
      content.image = normalizeImage(acf.image);
      content.caption = acf.caption || '';
      break;
      
    case 'image-caption-2':
      content.image_1 = normalizeImage(acf.image_1);
      content.caption_1 = acf.caption_1 || '';
      content.image_2 = normalizeImage(acf.image_2);
      content.caption_2 = acf.caption_2 || '';
      break;
      
    case 'image-caption-3':
      content.image_1 = normalizeImage(acf.image_1);
      content.caption_1 = acf.caption_1 || '';
      content.image_2 = normalizeImage(acf.image_2);
      content.caption_2 = acf.caption_2 || '';
      content.image_3 = normalizeImage(acf.image_3);
      content.caption_3 = acf.caption_3 || '';
      break;
      
    case 'image-caption-4':
      content.image_1 = normalizeImage(acf.image_1);
      content.caption_1 = acf.caption_1 || '';
      content.image_2 = normalizeImage(acf.image_2);
      content.caption_2 = acf.caption_2 || '';
      content.image_3 = normalizeImage(acf.image_3);
      content.caption_3 = acf.caption_3 || '';
      content.image_4 = normalizeImage(acf.image_4);
      content.caption_4 = acf.caption_4 || '';
      break;
      
    case 'image-caption-only':
      content.image = normalizeImage(acf.image);
      content.caption = acf.caption || '';
      break;
      
    case 'image-caption-3-medium-small':
      content.image_1 = normalizeImage(acf.image_1);
      content.caption_1 = acf.caption_1 || '';
      content.image_2 = normalizeImage(acf.image_2);
      content.caption_2 = acf.caption_2 || '';
      content.image_3 = normalizeImage(acf.image_3);
      content.caption_3 = acf.caption_3 || '';
      break;
      
    case 'heading-two-columns-text':
      content.heading = acf.heading || '';
      content.left_content = acf.left_content || '';
      content.right_content = acf.right_content || '';
      break;
      
    case 'heading-text-image-1':
      content.heading = acf.heading || '';
      content.left_content = acf.left_content || '';
      content.image = normalizeImage(acf.image);
      content.caption = acf.caption || '';
      break;
      
    case 'timeline':
      content.timeline_title = acf.timeline_title || '';
      content.timeline_items = acf.timeline_items || [];
      break;
      
    case 'text-photo2':
    default:
      // 後方互換性：既存のtext-photo2テンプレート
      content.content = acf.content || '';
      content.photo1 = normalizeImage(acf.photo1);
      content.caption1 = acf.caption1 || '';
      content.photo2 = normalizeImage(acf.photo2);
      content.caption2 = acf.caption2 || '';
      break;
  }
}

async function enrichImages(content, base, headers) {
  // 全ての可能な画像フィールドをチェック
  const imageFields = [
    'photo1', 'photo2', // 後方互換性
    'image', // image-caption-1
    'image_1', 'image_2', 'image_3', 'image_4' // image-caption-2,3,4
  ];
  
  for (const key of imageFields) {
    const v = content[key];
    if (v && typeof v === 'object' && v.id && !v.url) {
      try {
        const m = await fetchMedia(v.id, base, headers);
        if (m && m.source_url) content[key] = { url: m.source_url, alt: m.alt_text || '', title: m.title?.rendered || '' };
      } catch {}
    }
  }
  return content;
}

async function main() {
  const idsArg = process.argv[2] || '';
  const ids = idsArg.split(',').map((s) => s.trim()).filter(Boolean);
  const WP_URL = process.env.WP_URL || '';
  const WP_JWT = process.env.WP_JWT || '';
  const ALLOW_DUMMY = /^(1|true|yes)$/i.test(process.env.ALLOW_DUMMY || '');
  const WP_BASIC_USER = process.env.WP_BASIC_USER || process.env.WP_APP_USER || '';
  const WP_BASIC_PASS = process.env.WP_BASIC_PASS || process.env.WP_APP_PASS || '';

  if (!WP_URL || !ids.length) {
    console.error('WP_URL and ids are required to fetch real data.');
    if (ALLOW_DUMMY) { console.warn('ALLOW_DUMMY=1: writing dummy files.'); dummy(); return; }
    process.exit(1);
  }

  const idSlug = {};
  let wroteAny = false;
  for (const id of ids) {
    console.log(`\n🔍 Processing page ID: ${id}`);
    try {
      // 認証ヘッダーを構築
      const authHeaders = buildAuthHeadersFromEnv();
      const headers = { 'Accept': 'application/json', ...authHeaders };
      
      if (authHeaders.Authorization) {
        if (authHeaders.Authorization.startsWith('Bearer')) {
          console.log(`🔐 Using JWT authentication (Bearer token present)`);
        } else if (authHeaders.Authorization.startsWith('Basic')) {
          console.log(`🔐 Using Basic authentication (Basic auth present)`);
        }
        console.log(`🔍 Auth header: ${authHeaders.Authorization.substring(0, 20)}...`);
      } else {
        console.log(`🔓 Using public access (no auth credentials)`);
        console.log(`🔍 Environment check - WP_JWT: ${process.env.WP_JWT ? 'present' : 'missing'}, WP_BASIC_USER: ${process.env.WP_BASIC_USER ? 'present' : 'missing'}`);
      }
      
      const page = await fetchPage(id, WP_URL, headers);
      const slug = page.slug || String(id);
      console.log(`✅ Page fetched: ${slug} (ID: ${id})`);
      
      // ファイル名はID名を使用（日本語エンコード問題を回避）
      const filename = String(id);
      idSlug[String(id)] = slug;
      idSlug[slug] = Number(id);

      // Try get ACF via v2 embed first, then acf/v3
      let acf = page.acf || {};
      console.log(`📋 ACF from embed: ${Object.keys(acf).length} fields`);
      if (!acf || Object.keys(acf).length === 0) {
        try {
          console.log(`🔄 Trying ACF v3 API...`);
          const acfResp = await fetchACF(id, WP_URL, headers);
          if (acfResp && acfResp.acf) {
            acf = acfResp.acf;
            console.log(`✅ ACF v3: ${Object.keys(acf).length} fields`);
          }
        } catch (e) {
          console.warn(`⚠️ ACF v3 failed: ${e.message}`);
        }
      }

      // テンプレート検出
      const templateSlug = page.template || 'default';
      const templateType = detectTemplateType(templateSlug);
      
      // 基本コンテンツオブジェクト
      const content = {
        id: Number(id),
        slug,
        template: templateType,
        title: acf.title || page.title?.rendered || slug,
        modified: page.modified || page.date || new Date().toISOString(),
        // 共通フィールド（PDFには出力しない）
        pdf_page_number: acf.pdf_page_number || null
      };

      // テンプレート別フィールド処理
      processTemplateFields(content, acf, templateType);

      await enrichImages(content, WP_URL, headers);
      
      // デバッグ: 生成されたコンテンツの詳細をログ出力
      // ファイル名はID名を使用（日本語エンコード問題を回避）
      writeJSON(`content-${filename}.json`, content);
      console.log(`✅ Page fetched: ${slug} (ID: ${id})`);
      wroteAny = true;
    } catch (e) {
      console.error(`❌ Error fetching id=${id}:`, e.message);
      if (e.status) console.error(`❌ HTTP Status: ${e.status}`);
      if (e.body) console.error(`❌ Response: ${e.body.slice(0, 500)}`);
    }
  }

  if (!Object.keys(idSlug).length || !wroteAny) {
    if (ALLOW_DUMMY) { console.warn('ALLOW_DUMMY=1: writing dummy fallback.'); dummy(); return; }
    console.error('Failed to fetch any page.');
    process.exit(1);
  }
  writeJSON('id-slug-map.json', idSlug);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
