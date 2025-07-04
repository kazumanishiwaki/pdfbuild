import fs from 'fs';
import ejs from 'ejs';
import path from 'path';
import { execSync } from 'child_process';

// スラッグまたはページIDの取得（GitHub Actionsで使用）
const identifier = process.env.SLUG || process.env.PAGE_ID || 'sample';

// テンプレートディレクトリのパス
const TEMPLATES_DIR = './templates';

// テンプレートタイプの定義
const TEMPLATE_TYPES = {
  'peoplelist': {
    name: 'peoplelist',
    description: 'メンバーリスト形式',
    detect: (data) => data.members || (data.member1_name && data.member1_photo),
    prepareContext: (data) => {
      // peoplelistテンプレート用のデータ準備
      const members = [];
      // リピーターフィールドの場合
      if (data.members && Array.isArray(data.members)) {
        members.push(...data.members);
      } else {
        // 固定フィールドの場合（下位互換性のため）
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
      }
      
      return {
        title: data.title || '',
        lead: data.lead || '',
        members
      };
    }
  },
  'text-photo2': {
    name: 'text-photo2',
    description: 'テキスト+写真2枚形式',
    detect: (data) => data.content && (data.photo1 || data.photo2),
    prepareContext: (data) => ({
      title: data.title || '',
      content: data.content || '',
      photo1: data.photo1 || 'https://placehold.co/800x500.png',
      caption1: data.caption1 || '',
      photo2: data.photo2 || 'https://placehold.co/800x500.png',
      caption2: data.caption2 || ''
    })
  }
  // 新しいテンプレートタイプをここに追加するだけでOK
  // 例:
  // 'event-flyer': {
  //   name: 'event-flyer',
  //   description: 'イベントフライヤー形式',
  //   detect: (data) => data.event_date && data.venue,
  //   prepareContext: (data) => ({
  //     title: data.title || '',
  //     date: data.event_date || '',
  //     venue: data.venue || '',
  //     description: data.description || ''
  //   })
  // }
};

// 利用可能なテンプレートの検出
function detectAvailableTemplates() {
  const templates = {};
  
  try {
    if (fs.existsSync(TEMPLATES_DIR)) {
      const files = fs.readdirSync(TEMPLATES_DIR);
      
      // .ejsファイルを検索
      files.forEach(file => {
        if (path.extname(file) === '.ejs' && file !== 'index.ejs') {
          const templateName = path.basename(file, '.ejs');
          templates[templateName] = {
            file: path.join(TEMPLATES_DIR, file),
            exists: true,
            // TEMPLATE_TYPESに定義がある場合はそれを使用、なければ基本情報のみ
            ...TEMPLATE_TYPES[templateName]
          };
        }
      });
    }
  } catch (err) {
    console.error('テンプレートディレクトリの読み込みエラー:', err);
  }
  
  return templates;
}

try {
  // 利用可能なテンプレートを検出
  const availableTemplates = detectAvailableTemplates();
  console.log('📄 利用可能なテンプレート:', Object.keys(availableTemplates).join(', '));
  
  // 出力ファイル名には常にスラッグを使用するための変数
  let slugForFile = 'sample'; // デフォルト値

  // IDとスラッグのマッピング情報を読み込み
  let idSlugMap = {};
  if (fs.existsSync('id-slug-map.json')) {
    idSlugMap = JSON.parse(fs.readFileSync('id-slug-map.json', 'utf-8'));
    console.log('📄 ID-スラッグマッピング情報を読み込みました');
    
    // もしIDがマッピングに存在する場合、そのスラッグを使用
    if (idSlugMap[identifier]) {
      slugForFile = idSlugMap[identifier];
      console.log(`📄 マッピング情報から識別子「${identifier}」に対応するスラッグ「${slugForFile}」を取得しました`);
    }
  } else {
    console.log('⚠️ ID-スラッグマッピング情報が見つかりません。直接ファイル検索を行います。');
  }

  // コンテンツファイルの決定
  let contentFile = 'content.json';
  let fileFound = false;
  let actualSlug = slugForFile; // デフォルトはマッピングから取得したスラッグ

  // 1. スラッグベースのファイルを検索
  const slugSpecificFile = `content-${identifier}.json`;
  if (fs.existsSync(slugSpecificFile)) {
    contentFile = slugSpecificFile;
    fileFound = true;
    actualSlug = identifier; // スラッグとして使用
    slugForFile = identifier;
    console.log(`📄 スラッグ「${identifier}」用のコンテンツファイル ${slugSpecificFile} を使用します`);
  } 
  // 2. IDベースのファイルを検索
  else {
    const idSpecificFile = `content-id-${identifier}.json`;
    if (fs.existsSync(idSpecificFile)) {
      contentFile = idSpecificFile;
      fileFound = true;
      
      // スラッグ情報がまだ取得できていなければ、ファイル名からスラッグを推測
      // スラッグ情報がすでにマッピングから取得されていれば、それを優先
      if (slugForFile === 'sample' && fs.existsSync(`content-${actualSlug}.json`)) {
        // コンテンツディレクトリ内の全ファイルを取得
        const files = fs.readdirSync('./');
        
        // スラッグファイルパターンを検索
        const slugPattern = /^content-([^-]+)\.json$/;
        for (const file of files) {
          const match = file.match(slugPattern);
          if (match && match[1] !== 'id') {
            // content.jsonファイルの内容を取得
            try {
              const testContent = JSON.parse(fs.readFileSync(file, 'utf-8'));
              const idContent = JSON.parse(fs.readFileSync(idSpecificFile, 'utf-8'));
              
              // タイトルが一致するか確認
              if (testContent.title === idContent.title) {
                // スラッグを抽出
                slugForFile = match[1];
                actualSlug = slugForFile;
                console.log(`📄 コンテンツ一致により「${identifier}」に対応するスラッグ「${slugForFile}」を特定しました`);
                break;
              }
            } catch (e) {
              // ファイル読み込みエラーは無視
            }
          }
        }
      }
      
      console.log(`📄 ページID「${identifier}」用のコンテンツファイル ${idSpecificFile} を使用します`);
      console.log(`📄 PDF生成には スラッグ「${slugForFile}」を使用します`);
    }
    // 3. マッピング情報から対応するスラッグを取得してファイルを検索
    else if (slugForFile !== 'sample') {
      const mappedFile = `content-${slugForFile}.json`;
      if (fs.existsSync(mappedFile)) {
        contentFile = mappedFile;
        actualSlug = slugForFile;
        fileFound = true;
        console.log(`📄 マッピング情報から「${identifier}」に対応するファイル ${mappedFile} を使用します`);
      }
    }
  }

  // ファイルが見つからない場合はデフォルトを使用
  if (!fileFound) {
    console.log(`📄 「${identifier}」用のコンテンツファイルが見つからないため、デフォルトの ${contentFile} を使用します`);
    // デフォルトの場合は識別子をスラッグとして使用
    slugForFile = identifier;
  }

  // Load raw ACF data
  const data = JSON.parse(fs.readFileSync(contentFile, 'utf-8'));

  // 環境変数からテンプレートタイプを取得（指定があれば優先）
  let templateType = process.env.TEMPLATE_TYPE || null;
  
  // 環境変数での指定がなければ、コンテンツに基づいて自動判定
  if (!templateType) {
    // デフォルトテンプレート
    templateType = 'peoplelist';
    
    // 各テンプレートタイプの検出関数を実行
    for (const [type, config] of Object.entries(TEMPLATE_TYPES)) {
      if (config.detect && config.detect(data)) {
        templateType = type;
        console.log(`🔍 テンプレートタイプ: ${type} を検出しました (${config.description})`);
        break;
      }
    }
  } else {
    console.log(`🔍 テンプレートタイプ: ${templateType} を環境変数から設定しました`);
  }

  // テンプレートタイプが有効か確認
  if (!availableTemplates[templateType]) {
    console.warn(`⚠️ 指定されたテンプレート「${templateType}」が見つかりません。デフォルトテンプレートに戻します。`);
    templateType = 'peoplelist'; // デフォルトに戻す
    
    // デフォルトも存在しない場合はエラー
    if (!availableTemplates[templateType]) {
      throw new Error(`デフォルトテンプレート「${templateType}」も見つかりません。テンプレートを確認してください。`);
    }
  }

  // テンプレートに応じたコンテキストデータの準備
  let context;
  
  // 定義済みのテンプレートタイプであればその処理を使用
  if (TEMPLATE_TYPES[templateType] && TEMPLATE_TYPES[templateType].prepareContext) {
    context = TEMPLATE_TYPES[templateType].prepareContext(data);
  } else {
    // 未定義のテンプレートタイプの場合はデータをそのまま渡す
    console.log(`⚠️ テンプレート「${templateType}」の処理が定義されていないため、データをそのまま使用します`);
    context = data;
  }

  // テンプレートファイルの選択
  const templateFile = `templates/${templateType}.ejs`;
  
  // テンプレートファイルが存在するか確認
  if (!fs.existsSync(templateFile)) {
    throw new Error(`テンプレートファイル「${templateFile}」が見つかりません。`);
  }
  
  // Compile EJS
  const tpl = fs.readFileSync(templateFile, 'utf-8');
  const html = ejs.render(tpl, context);

  // Write HTML
  fs.writeFileSync('index.html', html);
  console.log(`✅ index.html generated for identifier: ${identifier} using template: ${templateType}`);

  // Tailwind CSSのビルド
  execSync('./node_modules/.bin/tailwindcss -i ./src/input.css -o ./dist/output.css', { stdio: 'inherit' });
  console.log('✅ Tailwind CSS built');

  // 常に取得したスラッグを使用してPDFファイルを生成
  const pdfFilename = `booklet-${slugForFile}.pdf`;
  console.log(`🔍 PDFファイル名: ${pdfFilename} でビルドします`);
  execSync(`./node_modules/.bin/vivliostyle build index.html -o ${pdfFilename} --no-sandbox`, { stdio: 'inherit' });
  console.log(`✅ PDF generated: ${pdfFilename}`);

  // 念のため識別子が数値（ID）の場合はファイルをコピー
  if (/^\d+$/.test(identifier) && slugForFile !== identifier) {
    const idPdfFilename = `booklet-${identifier}.pdf`;
    fs.copyFileSync(pdfFilename, idPdfFilename);
    console.log(`✅ 互換性のためページIDを使用したファイルも作成: ${idPdfFilename}`);
  }

  // 環境変数SLUGを設定してGitHub Actionsに実際のスラッグを伝える（可能な場合）
  if (process.env.GITHUB_ENV) {
    fs.appendFileSync(process.env.GITHUB_ENV, `SLUG=${slugForFile}\n`);
    console.log(`✅ GitHub Actions環境変数SLUGを${slugForFile}に設定しました`);
  }

} catch (error) {
  console.error('Error:', error);
  process.exit(1);
}
