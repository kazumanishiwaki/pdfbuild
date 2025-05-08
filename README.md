# PDF Booklet 生成システム

## 概要
このプロジェクトは、JSON形式のデータから美しいレイアウトのPDFブックレットを自動生成するシステムです。EJSテンプレートエンジン、TailwindCSS、Vivliostyleを使用して、一貫性のあるデザインのPDFを生成します。

## 動作の流れ

1. **データの読み込み**: `content.json`から記事タイトル、リード文、メンバー情報などのデータを読み込みます
2. **HTML生成**: EJSテンプレート(`templates/index.ejs`)を使用してHTMLを生成します
3. **CSS処理**: TailwindCSSを使用してスタイルシートをビルドします
4. **PDF生成**: VivliostyleでHTMLからPDF(`booklet-{slug}.pdf`)を生成します

## 主要ファイルと役割

### コア機能ファイル
- `build.js`: メインビルドスクリプト。データの読み込み、HTML生成、CSSビルド、PDF生成の全処理を実行します
- `content.json`: 記事データを含むJSONファイル。タイトル、リード文、メンバー情報などが格納されています
- `templates/index.ejs`: HTMLテンプレートファイル。EJS構文を使用してデータを埋め込みます

### スタイリング関連
- `src/input.css`: TailwindCSSの基本スタイル定義ファイル
- `dist/output.css`: コンパイル後のCSSファイル
- `tailwind.config.js`: TailwindCSSの設定ファイル

### 設定ファイル
- `package.json`: プロジェクトの依存関係とスクリプト定義
- `.env`: 環境変数設定ファイル（APIキーなど）

## 実行方法

```bash
# 依存パッケージのインストール
npm install

# PDFの生成
npm run build

# ローカル環境での実行（APIからデータ取得してビルド）
npm run build:local
```

## 拡張・カスタマイズ方法

1. **テンプレートの編集**: `templates/index.ejs`を編集することでHTMLの構造を変更できます
2. **スタイルの編集**: インラインCSSまたはTailwindCSSクラスを使用してスタイルを変更できます
3. **データ構造の編集**: `content.json`のフォーマットを変更する場合は、`build.js`も適宜修正してください