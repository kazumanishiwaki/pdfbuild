#!/usr/bin/env node
/*
  見開きPDFのテストスクリプト
  Usage: node test-spread.js
*/

import { spawn } from 'node:child_process';
import fs from 'node:fs';

console.log('🧪 見開きPDFテストを開始します...');

// テスト用のページ4も作成
const page4Data = {
  "id": 4,
  "slug": "page4",
  "template": "heading-text",
  "title": "ページ4（左ページ）",
  "heading": "これは4ページ目です",
  "content": "見開きの左ページとして表示されるはずです。偶数ページなので左側に配置されます。4:5の見開きをテストします。"
};

const page5Data = {
  "id": 5,
  "slug": "page5", 
  "template": "heading-text",
  "title": "ページ5（右ページ）",
  "heading": "これは5ページ目です",
  "content": "見開きの右ページとして表示されるはずです。奇数ページなので右側に配置されます。4:5の見開きをテストします。"
};

// テストファイルを作成
fs.writeFileSync('examples/dummy/content-4.json', JSON.stringify(page4Data, null, 2));
fs.writeFileSync('examples/dummy/content-5.json', JSON.stringify(page5Data, null, 2));

console.log('📄 テストページ4, 5を作成しました');

// 複数のテストケースを実行
const testCases = [
  { page: 2, desc: "偶数ページ2（2:3の見開き）" },
  { page: 3, desc: "奇数ページ3（2:3の見開き）" },
  { page: 4, desc: "偶数ページ4（4:5の見開き）" },
  { page: 5, desc: "奇数ページ5（4:5の見開き）" }
];

async function runTest(pageId, description) {
  return new Promise((resolve, reject) => {
    console.log(`\n🔍 テスト: ${description}`);
    
    const args = [
      'scripts/build-one.js',
      '--json', `examples/dummy/content-${pageId}.json`,
      '--out', 'out',
      '--pdf',
      '--spread',
      '--suffix', '-spread'
    ];
    
    const child = spawn(process.execPath, args, { stdio: 'inherit' });
    
    child.on('exit', (code) => {
      if (code === 0) {
        console.log(`✅ ${description} - 成功`);
        resolve();
      } else {
        console.log(`❌ ${description} - 失敗 (exit code: ${code})`);
        reject(new Error(`Test failed for page ${pageId}`));
      }
    });
  });
}

async function main() {
  try {
    for (const testCase of testCases) {
      await runTest(testCase.page, testCase.desc);
    }
    
    console.log('\n🎉 すべてのテストが完了しました！');
    console.log('\n📁 生成されたファイル:');
    
    // 生成されたファイルを確認
    const files = fs.readdirSync('out').filter(f => f.includes('spread'));
    files.forEach(file => {
      const stats = fs.statSync(`out/${file}`);
      console.log(`  - ${file} (${Math.round(stats.size / 1024)}KB)`);
    });
    
  } catch (error) {
    console.error('❌ テスト中にエラーが発生しました:', error.message);
    process.exit(1);
  }
}

main();
