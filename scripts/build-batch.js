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
  }
  return opts;
}

function listContentFiles(srcDir) {
  return fs.readdirSync(srcDir)
    .filter(f => /^content-.*\.json$/.test(f))
    .map(f => path.join(srcDir, f));
}

function runOne(jsonPath, outDir, forcePdf) {
  return new Promise((resolve, reject) => {
    const args = ['scripts/build-one.js', '--json', jsonPath, '--out', outDir];
    if (forcePdf) args.push('--pdf');
    const child = spawn(process.execPath, args, { stdio: 'inherit' });
    child.on('exit', (code) => {
      if (code === 0) resolve(); else reject(new Error(`build-one failed: ${jsonPath}`));
    });
  });
}

async function main() {
  const { src, out, pdf: forcePdf } = parseArgs();
  const srcDir = path.resolve(process.cwd(), src);
  const outDir = path.resolve(process.cwd(), out);

  const files = listContentFiles(srcDir);
  if (!files.length) {
    console.error('No content-*.json found in', srcDir);
    process.exit(1);
  }

  console.log(`Found ${files.length} content file(s).`);
  for (const f of files) {
    console.log('→ Building', f);
    await runOne(f, outDir, !!forcePdf).catch((e) => {
      console.error(e.message);
    });
  }
  console.log('Done.');
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});

