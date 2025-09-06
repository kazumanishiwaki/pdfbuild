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
    
    console.log(`→ GET ${url}`);
    console.log(`→ Headers: ${JSON.stringify(debugHeaders)}`);

    const lib = url.startsWith('https') ? https : http;
    const req = lib.get(url, { headers: debugHeaders }, (res) => {
      console.log(`← Status: ${res.statusCode} ${res.statusMessage}`);
      console.log(`← Resp headers: ${JSON.stringify(res.headers)}`);
      
      let data = '';
      res.on('data', (c) => (data += c));
      res.on('end', () => {
        if (res.statusCode && res.statusCode >= 200 && res.statusCode < 300) {
          console.log(`← Body length: ${data.length} chars`);
          resolve({ status: res.statusCode, data });
        } else {
          // 本文先頭だけでも出すとWAF系メッセージやプラグイン名が見える
          console.error(`← Body head: ${data.slice(0, 500)}`);
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
    caption2: '写真2のキャプション（ダミー）'
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
  if (typeof val === 'object' && val.url) return val; // already in ACF image object form
  if (typeof val === 'number') return { id: val }; // will resolve later
  return val;
}

async function enrichImages(content, base, headers) {
  for (const key of ['photo1', 'photo2']) {
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
      const headers = { 'Accept': 'application/json' };
      console.log(`🔓 Trying public access first...`);
      const page = await fetchPage(id, WP_URL, headers);
      const slug = page.slug || String(id);
      console.log(`✅ Page fetched: ${slug} (ID: ${id})`);
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

      const content = {
        id: Number(id),
        slug,
        template: 'text-photo2',
        title: page.title?.rendered || page.title || slug,
        content: (page.content?.rendered || '').replace(/<[^>]+>/g, '').trim(),
        photo1: normalizeImage(acf.photo1 || null),
        caption1: acf.caption1 || '',
        photo2: normalizeImage(acf.photo2 || null),
        caption2: acf.caption2 || ''
      };

      await enrichImages(content, WP_URL, headers);
      writeJSON(`content-${slug}.json`, content);
      console.log(`💾 Saved: content-${slug}.json`);
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
