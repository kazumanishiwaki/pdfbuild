import fs from 'fs';
import ejs from 'ejs';
import { execSync } from 'child_process';

// slugの取得（GitHub Actionsで使用）
const slug = process.env.SLUG || 'sample';

try {
  // コンテンツファイルの決定（スラッグ指定のJSONファイルがあれば使用、なければデフォルトのcontent.json）
  let contentFile = 'content.json';
  const slugSpecificFile = `content-${slug}.json`;
  
  if (fs.existsSync(slugSpecificFile)) {
    contentFile = slugSpecificFile;
    console.log(`📄 スラッグ「${slug}」用のコンテンツファイル ${slugSpecificFile} を使用します`);
  } else {
    console.log(`📄 スラッグ「${slug}」用のコンテンツファイルが見つからないため、デフォルトの ${contentFile} を使用します`);
  }

  // Load raw ACF data (flat structure)
  const data = JSON.parse(fs.readFileSync(contentFile, 'utf-8'));

  // Convert fixed member fields → array
  const members = [];
  for (let i = 1; i <= 10; i++) {
    const name = data[`member${i}_name`];
    if (name && name.trim() !== '') {
      members.push({
        name,
        photo: data[`member${i}_photo`] || 'https://placehold.co/380x380.png',
        bio: data[`member${i}_bio`] || ''
      });
    }
  }

  // Merge into template context
  const context = {
    title: data.title || '',
    lead: data.lead || '',
    members
  };

  // Compile EJS
  const tpl = fs.readFileSync('templates/index.ejs', 'utf-8');
  const html = ejs.render(tpl, context);

  // Write HTML
  fs.writeFileSync('index.html', html);
  console.log(`✅ index.html generated for slug: ${slug} (ACF free build)`);

  // Tailwind CSSのビルド
  execSync('./node_modules/.bin/tailwindcss -i ./src/input.css -o ./dist/output.css', { stdio: 'inherit' });
  console.log('✅ Tailwind CSS built');

  // VivliostyleでPDFを生成
  execSync(`./node_modules/.bin/vivliostyle build index.html -o booklet-${slug}.pdf --no-sandbox`, { stdio: 'inherit' });
  console.log(`✅ PDF generated: booklet-${slug}.pdf`);

} catch (error) {
  console.error('Error:', error);
  process.exit(1);
}
