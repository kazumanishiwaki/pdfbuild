import registry from '../../templates/registry.js' assert { type: 'javascript' };
const _registry = registry || {};

const defaults = {
  'text-photo2': {
    detect: (d) => d.content && (d.photo1 || d.photo2),
    prepare: (d) => ({
      title: d.title || '',
      content: d.content || '',
      photo1: d.photo1 || 'https://placehold.co/800x500.png',
      caption1: d.caption1 || '',
      photo2: d.photo2 || 'https://placehold.co/800x500.png',
      caption2: d.caption2 || ''
    })
  }
};

const TYPES = { ...defaults, ..._registry };

export function selectTemplate({ data, templateType }) {
  if (templateType) {
    const t = TYPES[templateType];
    if (!t) throw new Error(`unknown template: ${templateType}`);
    return { type: templateType, context: t.prepare ? t.prepare(data) : data };
  }
  for (const [name, t] of Object.entries(TYPES)) {
    if (t.detect && t.detect(data)) {
      return { type: name, context: t.prepare ? t.prepare(data) : data };
    }
  }
  return { type: 'text-photo2', context: defaults['text-photo2'].prepare(data) };
}
