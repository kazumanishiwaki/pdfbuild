# PDF ブックレット生成 仕様（平易版）

## 目的
- WordPress で作成した固定ページの内容（ACF フィールド含む）をもとに、Vivliostyle + Tailwind（または同等ツール）で A4 横向き PDF ブックレットを自動生成・配布する。

## 全体像
- 入力: WordPress の固定ページ + ACF フィールド。
- ビルド: GitHub Actions から Node.js スクリプトを実行し、WP REST API でデータ取得 → PDF 生成。
- 出力: 生成 PDF を WordPress サーバの `wp-content/uploads/pdf-booklet/` に配置し、公開ページや管理画面から配布。

## 関係ファイル（このリポジトリ）
- `pdf-booklet-theme/` … 最小構成の WordPress テーマ
  - `style.css` … テーマ定義
  - `functions.php` … 管理 UI、GitHub Actions 連携、PDF 一覧/削除
  - `template-text-photo2.php` … ページテンプレート（ACF 内容のプレビューと PDF リンク表示）
- `generate-pdf.yml` … GitHub Actions（手動トリガ: `workflow_dispatch`）
- `docs/spec.md` … 本仕様書
- `scripts/` … ローカル/CI 共通で使える簡易ビルドスクリプト（本書で追加）
- `examples/dummy/` … ダミー JSON（本書で追加）

## GitHub Actions（`generate-pdf.yml`）
- 手動起動（`workflow_dispatch`）時の inputs:
  - `wp_post_ids`: ページ ID のカンマ区切り（例: `123,456`）
  - `template_type`: テンプレート種別（未指定なら自動判定）
  - `concurrency`: 同時生成数（Vivliostyle 安定のため 2〜3 推奨）
  - `skip_schema`: スキーマ検証スキップフラグ
- 期待するスクリプト/ファイル（いずれか）
  - `scripts/fetch-acf.js` または `./fetch-acf.js`（ACF JSON の取得）
  - `scripts/build-batch.js` または `./build.js`（PDF 生成）
  - 生成物チェック: `booklet-*.pdf`

## WordPress 側
- テーマを有効化し、固定ページでテンプレート「PDF Booklet: テキスト+写真2枚形式」を選択。
- 管理画面: 設定画面に GitHub トークン/リポジトリ/ワークフロー ID を保存。ページ編集画面から PDF 生成をトリガ可能。
- PDF は `uploads/pdf-booklet/` 配下に配置（なければ自動作成）。
- ファイル名ルール（いずれか）：
  - `booklet-{slug}.pdf`
  - `{slug}.pdf`
  - `booklet-{post_id}.pdf`

## データ形式（ダミー JSON 例）
- `id-slug-map.json` … ページ ID とスラッグの対照表
  - 例: `{ "123": "demo", "demo": 123 }`
- `content-<slug>.json` … ページ 1 件分のコンテンツ
  - 主なフィールド（例）: `title`, `content`, `photo1`, `caption1`, `photo2`, `caption2`

## ローカルでの PDF 生成テスト（簡易）
1. ダミー JSON を用意（`examples/dummy/` に配置済み）
2. Node.js スクリプトで HTML を生成 → Puppeteer があれば PDF も生成
   - `scripts/build-batch.js` … バッチ実行
   - `scripts/build-one.js` … 1 件実行（HTML 作成、Puppeteer が存在すれば PDF 化）
3. 実行例（HTML のみ生成）:
   - `node scripts/build-batch.js --src examples/dummy --out out`
4. PDF まで生成したい場合（任意）:
   - `npm i -D puppeteer`
   - `node scripts/build-batch.js --src examples/dummy --out out`

出力の既定: `out/booklet-<slug>.html` と `out/booklet-<slug>.pdf`（PDF は Puppeteer がある場合）

## 役割分担
- このリポジトリ: テーマ、ワークフロー、JS ビルド土台、ダミーデータ、ローカル検証手段。
- 外部ビルダー: 実運用の ACF 取得、実スキーマ、Vivliostyle + Tailwind の本実装、アップロード（SFTP/REST 等）の仕組み。

## 今後の拡張
- Vivliostyle/テンプレートの細部（余白・見開き・柱など）をテンプレート化
- ファイル命名の一本化（ID か slug）
- `ref` や環境の切替（main 固定→可変）
- アップロード方式の統一（WP REST/S3 など）

