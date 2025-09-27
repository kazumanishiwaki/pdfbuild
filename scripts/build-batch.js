#!/usr/bin/env node
/*
  Batch runner for local testing.
  Usage:
    node scripts/build-batch.js --src examples/dummy --out out
  It looks for:
    <src>/id-slug-map.json
    <src>/content-*.json
*/

import fs from 'node:fs';
import path from 'node:path';
import url from 'node:url';
import { spawn } from 'node:child_process';

const __dirname = path.dirname(url.fileURLToPath(import.meta.url));

function parseArgs() {
  const args = process.argv.slice(2);
  const opts = { src: '.', out: 'out' };
  for (let i = 0; i < args.length; i++) {
    const a = args[i];
    if (a === '--src') opts.src = args[++i];
    else if (a === '--out') opts.out = args[++i];
    else if (a === '--pdf') opts.pdf = true; // force attempt
    else if (a === '--spread') opts.spread = true; // spread mode
    else if (a === '--suffix') opts.suffix = args[++i]; // output suffix
  }
  return opts;
}

function listContentFiles(srcDir) {
  return fs.readdirSync(srcDir)
    .filter(f => /^content-.*\.json$/.test(f))
    .map(f => path.join(srcDir, f));
}

function runOne(jsonPath, outDir, forcePdf, spread, suffix) {
  return new Promise((resolve, reject) => {
    const args = ['scripts/build-one.js', '--json', jsonPath, '--out', outDir];
    if (forcePdf) args.push('--pdf');
    if (spread) args.push('--spread');
    if (suffix) args.push('--suffix', suffix);
    const child = spawn(process.execPath, args, { stdio: 'inherit' });
    child.on('exit', (code) => {
      if (code === 0) resolve(); else reject(new Error(`build-one failed: ${jsonPath}`));
    });
  });
}

async function main() {
  const { src, out, pdf: forcePdf, spread, suffix } = parseArgs();
  const srcDir = path.resolve(process.cwd(), src);
  const outDir = path.resolve(process.cwd(), out);

  const files = listContentFiles(srcDir);
  if (!files.length) {
    console.error('No content-*.json found in', srcDir);
    process.exit(1);
  }

  // Build a quick page index for efficient adjacent-page lookup
  // Map: pdf_page_number (number) -> absolute file path
  try {
    const index = {};
    let minNum = Infinity;
    let maxNum = -Infinity;
    for (const f of files) {
      try {
        const raw = fs.readFileSync(f, 'utf-8');
        const data = JSON.parse(raw);
        const n = parseInt(data.pdf_page_number || data.id);
        if (!Number.isNaN(n)) {
          index[n] = f;
          if (n < minNum) minNum = n;
          if (n > maxNum) maxNum = n;
        }
      } catch (e) {
        // ignore malformed files but continue
      }
    }
    const indexPath = path.join(srcDir, 'page-index.json');
    fs.writeFileSync(indexPath, JSON.stringify(index, null, 2));
    const count = Object.keys(index).length;
    console.log(`Built page-index.json with ${count} page(s).`);
    if (count) console.log(`  Range: ${minNum}..${maxNum}`);
  } catch (e) {
    console.warn('⚠️ Failed to build page-index.json:', e.message);
  }

  console.log(`Found ${files.length} content file(s).`);
  for (const f of files) {
    console.log('→ Building', f);
    if (spread) console.log('  📖 Spread mode enabled');
    if (suffix) console.log(`  📝 Output suffix: ${suffix}`);
    await runOne(f, outDir, !!forcePdf, spread, suffix).catch((e) => {
      console.error(e.message);
    });
  }
  console.log('Done.');
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
