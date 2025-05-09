import fs from 'fs';
import ejs from 'ejs';
import { execSync } from 'child_process';

// スラッグまたはページIDの取得（GitHub Actionsで使用）
const identifier = process.env.SLUG || process.env.PAGE_ID || 'sample';

try {
  // IDとスラッグのマッピング情報を読み込み
  let idSlugMap = {};
  if (fs.existsSync('id-slug-map.json')) {
    idSlugMap = JSON.parse(fs.readFileSync('id-slug-map.json', 'utf-8'));
  } else {
    console.log('⚠️ ID-スラッグマッピング情報が見つかりません。直接ファイル検索を行います。');
  }

  // コンテンツファイルの決定
  let contentFile = 'content.json';
  let fileFound = false;
  let actualSlug = identifier;

  // 1. スラッグベースのファイルを検索
  const slugSpecificFile = `content-${identifier}.json`;
  if (fs.existsSync(slugSpecificFile)) {
    contentFile = slugSpecificFile;
    fileFound = true;
    console.log(`📄 スラッグ「${identifier}」用のコンテンツファイル ${slugSpecificFile} を使用します`);
  } 
  // 2. IDベースのファイルを検索
  else {
    const idSpecificFile = `content-id-${identifier}.json`;
    if (fs.existsSync(idSpecificFile)) {
      contentFile = idSpecificFile;
      fileFound = true;
      // マッピング情報からスラッグを取得
      if (idSlugMap[identifier]) {
        actualSlug = idSlugMap[identifier];
      }
      console.log(`📄 ページID「${identifier}」用のコンテンツファイル ${idSpecificFile} を使用します`);
    }
    // 3. マッピング情報から対応するスラッグを取得してファイルを検索
    else if (idSlugMap[identifier]) {
      const mappedSlug = idSlugMap[identifier];
      const mappedFile = `content-${mappedSlug}.json`;
      if (fs.existsSync(mappedFile)) {
        contentFile = mappedFile;
        actualSlug = mappedSlug;
        fileFound = true;
        console.log(`📄 マッピング情報から「${identifier}」に対応するファイル ${mappedFile} を使用します`);
      }
    }
  }

  // ファイルが見つからない場合はデフォルトを使用
  if (!fileFound) {
    console.log(`📄 「${identifier}」用のコンテンツファイルが見つからないため、デフォルトの ${contentFile} を使用します`);
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
  console.log(`✅ index.html generated for identifier: ${identifier} (ACF free build)`);

  // Tailwind CSSのビルド
  execSync('./node_modules/.bin/tailwindcss -i ./src/input.css -o ./dist/output.css', { stdio: 'inherit' });
  console.log('✅ Tailwind CSS built');

  // VivliostyleでPDFを生成（実際のスラッグを使用）
  execSync(`./node_modules/.bin/vivliostyle build index.html -o booklet-${actualSlug}.pdf --no-sandbox`, { stdio: 'inherit' });
  console.log(`✅ PDF generated: booklet-${actualSlug}.pdf`);

} catch (error) {
  console.error('Error:', error);
  process.exit(1);
}
