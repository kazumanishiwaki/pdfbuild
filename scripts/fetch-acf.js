import fs from 'fs';
import dotenv from 'dotenv';
import fetch from 'node-fetch';

// 環境変数の読み込み
dotenv.config();

const {
  WP_URL,
  WP_PAGE_ID,
  WP_JWT
} = process.env;

async function fetchACFData() {
  try {
    // WordPress REST APIからACFデータを取得
    const response = await fetch(
      `${WP_URL}/wp-json/wp/v2/pages/${WP_PAGE_ID}`,
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
    
    // ACFデータを抽出
    const acfData = data.acf || {};
    
    // content.jsonとして保存
    fs.writeFileSync('content.json', JSON.stringify(acfData, null, 2));
    console.log('✅ ACF data fetched and saved to content.json');
  } catch (error) {
    console.error('Error fetching ACF data:', error);
    process.exit(1);
  }
}

fetchACFData();