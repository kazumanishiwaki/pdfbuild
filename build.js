import { execSync } from 'child_process';
const slug = process.env.SLUG || 'sample';
execSync(`vivliostyle build index.html -o booklet-${slug}.pdf`, { stdio: 'inherit' });
