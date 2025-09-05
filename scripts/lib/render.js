import fs from 'fs';
import ejs from 'ejs';
import path from 'path';

export async function renderHtml({ type, context }) {
  const tplPath = path.join('templates', `${type}.ejs`);
  if (!fs.existsSync(tplPath)) throw new Error(`template not found: ${tplPath}`);

  const env = {
    PDF_PAGE_SIZE: process.env.PDF_PAGE_SIZE || 'A4',
    PDF_MARGIN: process.env.PDF_MARGIN || '12mm',
  };
  const tpl = fs.readFileSync(tplPath, 'utf-8');
  const html = ejs.render(tpl, { ...context, _env: env });
  fs.writeFileSync('index.html', html);
  return path.resolve('index.html');
}
