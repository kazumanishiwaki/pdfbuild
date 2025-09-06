# PDF Booklet Theme (WordPress)

最小構成の WordPress テーマです。管理画面でページを作成し、ACF（Advanced Custom Fields）で
`title / content / photo1 / caption1 / photo2 / caption2` などのフィールドを入力。
外部の Node.js ビルダー（`fetch-acf.js` / `build.js` など）から WP REST API でデータを取得し、
Vivliostyle + Tailwind で PDF ブックレットを生成するワークフローを想定しています。

## 同梱ファイル
- `style.css` … テーマヘッダのみ（有効化に必要）
- `functions.php` … 必要最低限の設定
- `index.php` … フォールバック
- `template-text-photo2.php` … ページテンプレート（管理画面から選択）

## 使い方
1. このテーマを `wp-content/themes/pdf-booklet-theme/` に配置して有効化。
2. ページを作成して「テンプレート: Text + Photo (2)」を選択。
3. 必要なら ACF のフィールドグループを追加（photo1/caption1/photo2/caption2 など）。
4. GitHub Actions から `fetch-acf.js` 等を実行して JSON を取得 → EJS + Vivliostyle で PDF 化。

## 注意
- JWT が必要な場合は GitHub Actions 側でトークン発行 or Secrets に保存して渡してください。
- 公開ページのみを扱う場合は JWT なしでも動作します。
