import fs from 'fs';
import dotenv from 'dotenv';
import fetch from 'node-fetch';

// 環境変数の読み込み
dotenv.config();

const {
  WP_URL,
  WP_JWT
} = process.env;

// 環境変数のデバッグ情報
console.log('環境変数情報:');
console.log(`WP_URL: ${WP_URL ? '設定されています' : '未設定'}`);
console.log(`WP_JWT: ${WP_JWT ? '設定されています' : '未設定'}`);
console.log(`WP_PAGE_IDS: ${process.env.WP_PAGE_IDS || '未設定'}`);

// 環境変数の検証
if (!WP_URL) {
  console.error('エラー: WP_URL環境変数が設定されていません。');
  process.exit(1);
}

// URLの検証
function validateUrl(url) {
  try {
    new URL(url);
    return true;
  } catch (error) {
    return false;
  }
}

// URLが有効かチェック
if (!validateUrl(WP_URL)) {
  console.error(`エラー: 無効なURL形式です: ${WP_URL}`);
  console.error('正しい形式の例: https://example.com');
  process.exit(1);
}

// 第一引数としてページIDを受け取るか、環境変数から取得
const getPageIds = () => {
  // コマンドライン引数からページIDを取得（カンマ区切りで複数指定可能）
  if (process.argv[2]) {
    return process.argv[2].split(',');
  }
  
  // 環境変数からページIDを取得
  if (process.env.WP_PAGE_IDS) {
    return process.env.WP_PAGE_IDS.split(',');
  }
  
  // 単一ページIDの場合
  if (process.env.WP_PAGE_ID) {
    return [process.env.WP_PAGE_ID];
  }
  
  throw new Error('ページIDが指定されていません。コマンドライン引数または環境変数で指定してください。');
};

async function fetchPageData(pageId) {
  // WordPress REST APIからページデータを取得
  const apiUrl = `${WP_URL}/wp-json/wp/v2/pages/${pageId}`;
  console.log(`APIリクエスト: ${apiUrl}`);
  
  if (!validateUrl(apiUrl)) {
    throw new Error(`無効なAPI URL: ${apiUrl}`);
  }
  
  const response = await fetch(
    apiUrl,
    {
      headers: {
        'Authorization': `Bearer ${WP_JWT}`
      }
    }
  );

  if (!response.ok) {
    throw new Error(`HTTP error! status: ${response.status}`);
  }

  const data = await response.json();
  
  // スラッグとACFデータを返す
  return {
    slug: data.slug,
    acf: data.acf || {}
  };
}

async function fetchACFData() {
  try {
    const pageIds = getPageIds();
    console.log(`🔍 ${pageIds.length}個のページからACFデータを取得します...`);
    
    for (const pageId of pageIds) {
      const pageData = await fetchPageData(pageId);
      const { slug, acf } = pageData;
      
      // content-{slug}.jsonとして保存
      const outputFile = `content-${slug}.json`;
      fs.writeFileSync(outputFile, JSON.stringify(acf, null, 2));
      console.log(`✅ ${pageId}(${slug})のACFデータを${outputFile}に保存しました`);
      
      // 後方互換性のために、最初のページデータはcontent.jsonにも保存
      if (pageIds.indexOf(pageId) === 0) {
        fs.writeFileSync('content.json', JSON.stringify(acf, null, 2));
        console.log(`✅ 互換性のため最初のページのデータをcontent.jsonにも保存しました`);
      }
    }
  } catch (error) {
    console.error('Error fetching ACF data:', error);
    process.exit(1);
  }
}

fetchACFData();