import fs from 'fs';

export function resolveInput(identifier) {
  let slug = identifier;
  let file = identifier ? `content-${identifier}.json` : 'content.json';
  if (!fs.existsSync(file)) {
    throw new Error(`content not found: ${file}`);
  }
  const data = JSON.parse(fs.readFileSync(file, 'utf-8'));
  return { data, slug: identifier || 'sample' };
}
