import { resolveInput } from './lib/resolve-input.js';
import { selectTemplate } from './lib/select-template.js';
import { validateData } from './lib/validate.js';
import { renderHtml } from './lib/render.js';
import { generatePdf } from './lib/pdf.js';
import { buildCssOnce } from './lib/css.js';

const identifier = process.argv[2] || process.env.SLUG || process.env.PAGE_ID || null;

async function main() {
  if (!identifier) {
    console.error('Usage: node scripts/build-single.js <slug|identifier>');
    process.exit(1);
  }
  const templateType = process.env.TEMPLATE_TYPE || '';
  await buildCssOnce();
  const { data, slug } = resolveInput(identifier);
  const { type, context } = selectTemplate({ data, templateType: templateType || null });
  validateData(type, data);
  const htmlPath = await renderHtml({ type, context });
  const outPdf = await generatePdf({ slug });
  console.log(`✅ Built: slug=${slug} template=${type} html=${htmlPath} pdf=${outPdf}`);
}

main().catch(e => { console.error(e); process.exit(1); });
