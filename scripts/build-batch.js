import fs from 'fs';
import pLimit from 'p-limit';
import { buildCssOnce } from './lib/css.js';
import { resolveInput } from './lib/resolve-input.js';
import { selectTemplate } from './lib/select-template.js';
import { validateData } from './lib/validate.js';
import { renderHtml } from './lib/render.js';
import { generatePdf } from './lib/pdf.js';

const CONCURRENCY = Number(process.env.CONCURRENCY || 2);

async function buildOne(slug, templateType) {
  const { data } = resolveInput(slug);
  const { type, context } = selectTemplate({ data, templateType: templateType || null });
  validateData(type, data);
  await renderHtml({ type, context });
  const out = await generatePdf({ slug });
  console.log(`✅ ${slug}: ${type} -> ${out}`);
}

async function main() {
  await buildCssOnce();
  const idSlugPath = 'id-slug-map.json';
  if (!fs.existsSync(idSlugPath)) {
    throw new Error('id-slug-map.json が見つかりません。先に fetch-acf を実行してください。');
  }
  const map = JSON.parse(fs.readFileSync(idSlugPath, 'utf-8'));
  const slugs = Object.keys(map).filter(k => isNaN(Number(k)));
  if (!slugs.length) throw new Error('slug が見つかりません。');
  console.log(`🧾 Targets (${slugs.length}): ${slugs.join(', ')}`);

  const limit = pLimit(CONCURRENCY);
  const templateType = process.env.TEMPLATE_TYPE || null;
  const tasks = slugs.map(s => limit(() => buildOne(s, templateType)));

  const results = await Promise.allSettled(tasks);
  const failed = results.filter(r => r.status === 'rejected');
  if (failed.length) {
    console.error(`❌ Failed ${failed.length}/${slugs.length}`);
    process.exit(1);
  }
  console.log('🎉 All done');
}

main().catch(e => { console.error(e); process.exit(1); });
