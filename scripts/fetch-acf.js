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
    const lib = url.startsWith('https') ? https : http;
    const req = lib.get(url, { headers }, (res) => {
      let data = '';
      res.on('data', (c) => (data += c));
      res.on('end', () => {
        if (res.statusCode && res.statusCode >= 200 && res.statusCode < 300) {
          resolve({ status: res.statusCode, data });
        } else {
          resolve({ status: res.statusCode || 0, data });
        }
      });
    });
    req.on('error', reject);
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
  throw err;
}

async function fetchPage(id, base, headers) {
  const url = `${base.replace(/\/$/, '')}/wp-json/wp/v2/pages/${encodeURIComponent(id)}?_embed`;
  return getJSON(url, headers);
}

async function fetchACF(id, base, headers) {
  // Requires ACF to REST API plugin
  const url = `${base.replace(/\/$/, '')}/wp-json/acf/v3/pages/${encodeURIComponent(id)}`;
  return getJSON(url, headers);
}

async function fetchMedia(id, base, headers) {
  const url = `${base.replace(/\/$/, '')}/wp-json/wp/v2/media/${encodeURIComponent(id)}`;
  return getJSON(url, headers);
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

  if (!WP_URL || !ids.length) {
    console.error('WP_URL and ids are required to fetch real data.');
    if (ALLOW_DUMMY) { console.warn('ALLOW_DUMMY=1: writing dummy files.'); dummy(); return; }
    process.exit(1);
  }

  const idSlug = {};
  let wroteAny = false;
  for (const id of ids) {
    try {
      const headers = { 'Accept': 'application/json' };
      if (WP_JWT) headers['Authorization'] = `Bearer ${WP_JWT}`;
      const page = await fetchPage(id, WP_URL, headers);
      const slug = page.slug || String(id);
      idSlug[String(id)] = slug;
      idSlug[slug] = Number(id);

      // Try get ACF via v2 embed first, then acf/v3
      let acf = page.acf || {};
      if (!acf || Object.keys(acf).length === 0) {
        try {
          const acfResp = await fetchACF(id, WP_URL, headers);
          if (acfResp && acfResp.acf) acf = acfResp.acf;
        } catch {}
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
      wroteAny = true;
    } catch (e) {
      console.warn(`Error fetching id=${id}:`, e.message);
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
