import fetch from 'node-fetch';
import dotenv from 'dotenv';

// 環境変数の読み込み
dotenv.config();

// コマンドライン引数またはデフォルト値からトークンを取得
const getToken = () => {
  if (process.argv[2]) {
    return process.argv[2];
  }
  
  if (process.env.WP_JWT) {
    return process.env.WP_JWT;
  }
  
  throw new Error('JWTトークンが指定されていません。引数または環境変数WP_JWTで指定してください。');
};

// URLの設定
const API_BASE_URL = process.env.WP_URL || 'http://kazumanishiwaki.net/ks';

// テスト用のエンドポイント
const TEST_ENDPOINTS = [
  '/wp-json',                  // WP APIのルート（認証不要のはず）
  '/wp-json/jwt-auth/v1/token/validate', // JWT検証エンドポイント
  '/wp-json/wp/v2/users/me',   // 現在のユーザー情報（認証必要）
  '/wp-json/wp/v2/pages'       // ページ一覧（認証の有無で結果が異なる可能性）
];

/**
 * エンドポイントへのリクエストを実行
 */
async function testEndpoint(endpoint, token = null) {
  const url = `${API_BASE_URL}${endpoint}`;
  console.log(`\n🔍 テスト: ${url}`);
  
  const headers = {};
  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }
  
  try {
    // リクエスト実行
    console.log(`${token ? '認証あり' : '認証なし'}でリクエスト送信...`);
    const response = await fetch(url, { headers });
    
    // ステータスコード
    console.log(`ステータス: ${response.status} ${response.statusText}`);
    
    // レスポンスの内容
    const contentType = response.headers.get('content-type') || '';
    if (contentType.includes('application/json')) {
      const data = await response.json();
      console.log('レスポンス (JSON):');
      console.log(JSON.stringify(data, null, 2).substring(0, 500) + '...');
    } else {
      const text = await response.text();
      console.log('レスポンス (非JSON):');
      console.log(text.substring(0, 200) + (text.length > 200 ? '...' : ''));
    }
    
    return response.ok;
  } catch (error) {
    console.error(`エラー: ${error.message}`);
    return false;
  }
}

/**
 * JWTトークンのデコード（検証ではなく内容確認のみ）
 */
function decodeJWT(token) {
  try {
    const parts = token.split('.');
    if (parts.length !== 3) {
      return { valid: false, message: 'JWTの形式が不正（3つのセクションでない）' };
    }
    
    const header = JSON.parse(Buffer.from(parts[0], 'base64').toString());
    const payload = JSON.parse(Buffer.from(parts[1], 'base64').toString());
    
    return {
      valid: true,
      header,
      payload,
      exp: payload.exp ? new Date(payload.exp * 1000).toISOString() : 'なし',
      isExpired: payload.exp ? Date.now() > payload.exp * 1000 : false
    };
  } catch (error) {
    return { valid: false, message: `デコード失敗: ${error.message}` };
  }
}

async function runTests() {
  try {
    const token = getToken();
    console.log('=== JWT検証テスト開始 ===');
    console.log(`トークン長: ${token.length}文字`);
    console.log(`トークン先頭: ${token.substring(0, 20)}...`);
    
    // トークンのデコード（基本的な構造確認）
    console.log('\n📝 JWTデコード結果:');
    const decoded = decodeJWT(token);
    if (decoded.valid) {
      console.log(`ヘッダ: ${JSON.stringify(decoded.header)}`);
      console.log(`ペイロード: ${JSON.stringify(decoded.payload, null, 2)}`);
      console.log(`有効期限: ${decoded.exp}`);
      if (decoded.isExpired) {
        console.log('⚠️ トークンの有効期限が切れています！');
      }
    } else {
      console.log(`⚠️ ${decoded.message}`);
    }
    
    // 各エンドポイントでのテスト
    for (const endpoint of TEST_ENDPOINTS) {
      // 認証なしでのテスト
      await testEndpoint(endpoint);
      
      // 認証ありでのテスト
      await testEndpoint(endpoint, token);
    }
    
    console.log('\n=== JWT検証テスト完了 ===');
  } catch (error) {
    console.error(`テスト実行エラー: ${error.message}`);
    process.exit(1);
  }
}

runTests(); 