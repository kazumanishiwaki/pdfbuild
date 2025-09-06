#!/usr/bin/env node
/*
  Minimal fetcher for CI bootstrap.
  Usage: node scripts/fetch-acf.js "123,456"
  Behavior:
    - If WP_URL is set, it will attempt to GET /wp-json/wp/v2/pages/{id}
      and read known fields, but will not fail build on fetch errors.
    - If WP_URL is not set or fetch fails, it writes a single dummy content
      so downstream build can proceed.
  Output files in CWD:
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

async function main() {
  const idsArg = process.argv[2] || '';
  const ids = idsArg.split(',').map((s) => s.trim()).filter(Boolean);
  const WP_URL = process.env.WP_URL || '';
  const WP_JWT = process.env.WP_JWT || '';

  if (!WP_URL || !ids.length) {
    console.warn('No WP_URL or empty id list — writing dummy files.');
    dummy();
    return;
  }

  const idSlug = {};
  let wroteAny = false;
  for (const id of ids) {
    try {
      const headers = { 'Accept': 'application/json' };
      if (WP_JWT) headers['Authorization'] = `Bearer ${WP_JWT}`;
      const url = `${WP_URL.replace(/\/$/, '')}/wp-json/wp/v2/pages/${encodeURIComponent(id)}?_embed`;
      const { status, data } = await httpGet(url, headers);
      if (status >= 200 && status < 300) {
        const page = JSON.parse(data);
        const slug = page.slug || String(id);
        idSlug[String(id)] = slug;
        idSlug[slug] = Number(id);
        const acf = page.acf || {};
        const content = {
          id: Number(id),
          slug,
          template: 'text-photo2',
          title: page.title?.rendered || page.title || slug,
          content: (page.content?.rendered || '').replace(/<[^>]+>/g, '').slice(0, 200),
          photo1: acf.photo1 || null,
          caption1: acf.caption1 || '',
          photo2: acf.photo2 || null,
          caption2: acf.caption2 || ''
        };
        writeJSON(`content-${slug}.json`, content);
        wroteAny = true;
      } else {
        console.warn(`Fetch failed for id=${id} status=${status}. Falling back to dummy.`);
      }
    } catch (e) {
      console.warn(`Error fetching id=${id}:`, e.message);
    }
  }

  if (!Object.keys(idSlug).length) {
    dummy();
  } else {
    writeJSON('id-slug-map.json', idSlug);
    if (!wroteAny) dummy();
  }
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});

