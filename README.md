# PDF Booklet Refactor Skeleton

このディレクトリは、複数テンプレート対応・バッチ生成対応のための分割構成です。

## 主要コマンド例
- 事前に `npm i ejs p-limit` と Vivliostyle / Tailwind の依存を用意してください。
- JSON 取得（例）: `node scripts/fetch-acf.js "123,456,789"`
- 単発ビルド: `node scripts/build-single.js my-page-slug`
- 一括ビルド: `CONCURRENCY=2 node scripts/build-batch.js`

## 環境変数
- `TEMPLATE_TYPE`: 明示テンプレート（空なら自動検出）
- `SKIP_SCHEMA`: `1` でスキーマ検証をスキップ
- `PDF_PAGE_SIZE` / `PDF_MARGIN`: EJS に渡る印刷設定
- `CONCURRENCY`: 一括ビルドの同時生成数
