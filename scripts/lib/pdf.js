import { execFileSync } from 'child_process';

export async function generatePdf({ slug }) {
  const out = `booklet-${slug}.pdf`;
  execFileSync('./node_modules/.bin/vivliostyle',
    ['build','index.html','-o', out, '--no-sandbox'],
    { stdio: 'inherit' });
  return out;
}
