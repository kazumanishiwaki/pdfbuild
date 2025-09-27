#!/usr/bin/env node
/*
  見開きPDF診断スクリプト
  現在の設定と動作を確認
*/

import fs from 'node:fs';
import path from 'node:path';

console.log('🔍 見開きPDF診断を開始します...\n');

// 1. ファイルの存在確認
console.log('📁 重要ファイルの確認:');
const importantFiles = [
  'scripts/build-one.js',
  'scripts/build-batch.js', 
  'scripts/fetch-acf.js',
  '.github/workflows/generate-pdf.yml',
  'pdf-booklet-theme/functions.php'
];

importantFiles.forEach(file => {
  if (fs.existsSync(file)) {
    const stats = fs.statSync(file);
    console.log(`  ✅ ${file} (${Math.round(stats.size / 1024)}KB, ${stats.mtime.toISOString()})`);
  } else {
    console.log(`  ❌ ${file} - 見つかりません`);
  }
});

// 2. build-one.jsの見開きモード対応確認
console.log('\n🔧 build-one.js の見開きモード対応:');
try {
  const buildOneContent = fs.readFileSync('scripts/build-one.js', 'utf-8');
  
  const checks = [
    { name: 'spread パラメータ', pattern: /--spread/ },
    { name: 'A3 landscape設定', pattern: /format: spread \? 'A3' : 'A4'/ },
    { name: 'leftPageData変数', pattern: /let leftPageData = null/ },
    { name: 'rightPageData変数', pattern: /let rightPageData = null/ },
    { name: '見開きレイアウトCSS', pattern: /\.spread-layout/ },
    { name: '隣接ページ読み込み', pattern: /leftPageId !== currentPageId/ }
  ];
  
  checks.forEach(check => {
    if (check.pattern.test(buildOneContent)) {
      console.log(`  ✅ ${check.name}`);
    } else {
      console.log(`  ❌ ${check.name} - 見つかりません`);
    }
  });
} catch (error) {
  console.log(`  ❌ build-one.js読み込みエラー: ${error.message}`);
}

// 3. GitHub Actionsワークフローの確認
console.log('\n⚙️ GitHub Actionsワークフローの確認:');
try {
  const workflowContent = fs.readFileSync('.github/workflows/generate-pdf.yml', 'utf-8');
  
  const workflowChecks = [
    { name: 'spread_mode入力', pattern: /spread_mode:/ },
    { name: 'output_suffix入力', pattern: /output_suffix:/ },
    { name: '隣接ページ取得ロジック', pattern: /EXPANDED_IDS/ },
    { name: 'FETCH_PAGE_IDS使用', pattern: /FETCH_PAGE_IDS/ }
  ];
  
  workflowChecks.forEach(check => {
    if (check.pattern.test(workflowContent)) {
      console.log(`  ✅ ${check.name}`);
    } else {
      console.log(`  ❌ ${check.name} - 見つかりません`);
    }
  });
} catch (error) {
  console.log(`  ❌ ワークフローファイル読み込みエラー: ${error.message}`);
}

// 4. functions.phpの確認
console.log('\n🔌 functions.php の見開きPDF対応:');
try {
  const functionsContent = fs.readFileSync('pdf-booklet-theme/functions.php', 'utf-8');
  
  const phpChecks = [
    { name: '見開きPDF生成ボタン', pattern: /見開きPDF生成/ },
    { name: '見開きPDF削除ボタン', pattern: /見開きPDF削除/ },
    { name: 'spread_mode パラメータ', pattern: /'spread_mode' => 'true'/ },
    { name: 'output_suffix パラメータ', pattern: /'output_suffix' => '-spread'/ },
    { name: 'generate_spread_pdf AJAX', pattern: /wp_ajax_generate_spread_pdf/ },
    { name: 'delete_spread_pdf AJAX', pattern: /wp_ajax_delete_spread_pdf/ }
  ];
  
  phpChecks.forEach(check => {
    if (check.pattern.test(functionsContent)) {
      console.log(`  ✅ ${check.name}`);
    } else {
      console.log(`  ❌ ${check.name} - 見つかりません`);
    }
  });
} catch (error) {
  console.log(`  ❌ functions.php読み込みエラー: ${error.message}`);
}

// 5. 最新のコミット情報
console.log('\n📝 最新のコミット情報:');
try {
  const { execSync } = await import('node:child_process');
  const lastCommit = execSync('git log -1 --oneline', { encoding: 'utf-8' }).trim();
  const status = execSync('git status --porcelain', { encoding: 'utf-8' }).trim();
  
  console.log(`  最新コミット: ${lastCommit}`);
  if (status) {
    console.log(`  未コミットの変更: あり`);
    console.log(`  ${status}`);
  } else {
    console.log(`  未コミットの変更: なし`);
  }
  
  // リモートとの同期状況
  try {
    const ahead = execSync('git rev-list --count origin/main..HEAD', { encoding: 'utf-8' }).trim();
    const behind = execSync('git rev-list --count HEAD..origin/main', { encoding: 'utf-8' }).trim();
    
    if (ahead === '0' && behind === '0') {
      console.log(`  リモート同期: ✅ 同期済み`);
    } else {
      console.log(`  リモート同期: ⚠️ ahead:${ahead}, behind:${behind}`);
    }
  } catch (e) {
    console.log(`  リモート同期: ❓ 確認できません`);
  }
} catch (error) {
  console.log(`  ❌ Git情報取得エラー: ${error.message}`);
}

console.log('\n✨ 診断完了！');
console.log('\n💡 トラブルシューティング:');
console.log('  1. WordPressで見開きPDF生成ボタンが表示されない → functions.phpの更新確認');
console.log('  2. 見開きPDFが縦長のまま → GitHub Actionsログを確認');
console.log('  3. 隣接ページが表示されない → fetch-acf.jsでのデータ取得確認');
console.log('  4. ボタンをクリックしても反応しない → ブラウザのキャッシュクリア');
