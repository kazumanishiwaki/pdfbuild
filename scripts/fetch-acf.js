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
  
  const commonUA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';
  const headers = { 'User-Agent': commonUA };
  if (WP_JWT) headers['Authorization'] = `Bearer ${WP_JWT}`;

  // 1st try (with Authorization if available)
  let response = await fetch(apiUrl, { headers });
  if (!response.ok) {
    const body = await response.text().catch(() => '');
    // If auth failed and we sent Authorization, retry without it
    if ((response.status === 401 || response.status === 403) && headers['Authorization']) {
      console.warn(`警告: 認証付きリクエストが失敗 (${response.status})。Authorization ヘッダなしで再試行します。`);
      response = await fetch(apiUrl, { headers: { 'User-Agent': commonUA } });
      if (!response.ok) {
        const body2 = await response.text().catch(() => '');
        throw new Error(`HTTP error! status: ${response.status}. body: ${body2.slice(0, 500)}`);
      }
    } else {
      throw new Error(`HTTP error! status: ${response.status}. body: ${body.slice(0, 500)}`);
    }
  }

  const data = await response.json();
  
  // スラッグとACFデータを返す
  return {
    id: data.id,
    slug: data.slug,
    acf: data.acf || {}
  };
}

async function fetchACFData() {
  try {
    const pageIds = getPageIds();
    console.log(`🔍 ${pageIds.length}個のページからACFデータを取得します...`);

    // ページIDとスラッグのマッピング情報を保存するためのオブジェクト
    const idSlugMap = {};
    const collected = [];

    for (const pageId of pageIds) {
      try {
        const pageData = await fetchPageData(pageId);
        const { id, slug, acf } = pageData;

        // マッピング情報を追加
        idSlugMap[id] = slug;
        idSlugMap[slug] = id;

        // content-{slug}.jsonとして保存
        const outputFile = `content-${slug}.json`;
        fs.writeFileSync(outputFile, JSON.stringify(acf, null, 2));
        console.log(`✅ ${pageId}(${slug})のACFデータを${outputFile}に保存しました`);

        // ID用のファイルも作成
        const idOutputFile = `content-id-${id}.json`;
        fs.writeFileSync(idOutputFile, JSON.stringify(acf, null, 2));
        console.log(`✅ ID参照用に${idOutputFile}も作成しました`);

        collected.push({ id, slug, acf });
      } catch (e) {
        console.error(`❌ ページ ${pageId} の取得に失敗しました:`, e.message);
        if (process.env.ON_ERROR === 'continue') {
          console.warn('ON_ERROR=continue のため処理を継続します');
          continue;
        }
        throw e;
      }
    }

    if (collected.length === 0) {
      throw new Error('いずれのページからもデータを取得できませんでした');
    }

    // 後方互換性のために、最初のページデータはcontent.jsonにも保存
    fs.writeFileSync('content.json', JSON.stringify(collected[0].acf, null, 2));
    console.log(`✅ 互換性のため最初のページのデータをcontent.jsonにも保存しました`);

    // ID-スラッグのマッピング情報を保存
    fs.writeFileSync('id-slug-map.json', JSON.stringify(idSlugMap, null, 2));
    console.log('✅ ID-スラッグマッピング情報をid-slug-map.jsonに保存しました');

  } catch (error) {
    console.error('Error fetching ACF data:', error);
    process.exit(1);
  }
}

fetchACFData();
