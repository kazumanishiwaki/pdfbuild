import fs from 'fs';
import path from 'path';

export function validateData(templateType, data) {
  if (process.env.SKIP_SCHEMA === '1' || process.env.SKIP_SCHEMA === 'true') return;
  const schemaPath = path.join('schemas', `${templateType}.schema.json`);
  if (!fs.existsSync(schemaPath)) return;
  const schema = JSON.parse(fs.readFileSync(schemaPath, 'utf-8'));
  const props = schema.properties || {};
  const required = schema.required || [];
  const errors = [];

  for (const key of required) {
    const v = data[key];
    if (v === undefined || v === null || (typeof v === 'string' && v.length === 0)) {
      errors.push(`required: ${key}`);
    }
  }
  for (const [key, def] of Object.entries(props)) {
    if (data[key] == null) continue;
    if (def.type && typeof data[key] !== def.type) {
      errors.push(`type: ${key} should be ${def.type} but got ${typeof data[key]}`);
    }
  }
  if (errors.length) throw new Error(`schema error (${templateType}):\n - ${errors.join('\n - ')}`);
}
