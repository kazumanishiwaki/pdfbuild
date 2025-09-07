# WordPress PDF Booklet テーマ設定手順

## 🔧 テーマのインストールと有効化

### 1. テーマファイルのアップロード
```
wp-content/themes/pdf-booklet-theme/
├── functions.php          # メイン機能ファイル
├── style.css             # テーマ情報
├── index.php             # 基本テンプレート
├── template-text-photo2.php  # PDF Booklet用テンプレート
└── WORDPRESS_SETUP.md    # この設定ファイル
```

### 2. WordPressでの確認手順

#### ステップ1: テーマの有効化
1. WordPress管理画面にログイン
2. **外観** → **テーマ** に移動
3. **PDF Booklet Theme** を探して **有効化** をクリック

#### ステップ2: デバッグ情報の確認
1. 管理画面のトップに以下のような青い通知が表示されるかを確認：
   ```
   PDF Booklet Debug:
   • functions.phpが正常に読み込まれました
   • 現在のテーマ: PDF Booklet Theme
   • テーマディレクトリ: /path/to/wp-content/themes/pdf-booklet-theme
   • PDF対応テンプレート: template-text-photo2.php
   ```

#### ステップ3: 固定ページでの動作確認
1. **ページ** → **新規追加** または既存ページを編集
2. **ページ属性** で **PDF Booklet Text Photo2** テンプレートを選択
3. 以下が確認できるか：
   - 本文エディタが非表示になる
   - 「📝 コンテンツの入力について」メッセージが表示される
   - 「📖 PDFブックレット」ウィジェットが表示される

## 🐛 トラブルシューティング

### 問題1: テーマが表示されない
**原因**: ファイルが正しい場所にアップロードされていない
**解決策**: 
- FTPまたはファイルマネージャーで `wp-content/themes/pdf-booklet-theme/` フォルダが存在するか確認
- `style.css` ファイルが存在し、テーマヘッダーが正しく記述されているか確認

### 問題2: 機能が動作しない
**原因**: テーマが有効化されていない、またはPHPエラーが発生している
**解決策**:
1. **外観** → **テーマ** で **PDF Booklet Theme** が有効になっているか確認
2. WordPressのデバッグログを確認：
   ```php
   // wp-config.phpに追加
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```
3. `/wp-content/debug.log` ファイルでエラーを確認

### 問題3: 本文エディタが非表示にならない
**原因**: ページテンプレートが正しく設定されていない
**解決策**:
1. ページ編集画面で **ページ属性** → **テンプレート** を確認
2. **PDF Booklet Text Photo2** が選択されているか確認
3. ページを保存してから再読み込み

### 問題4: PDFウィジェットが表示されない
**原因**: ACFプラグインがインストールされていない、またはフィールドが設定されていない
**解決策**:
1. **Advanced Custom Fields** プラグインをインストール・有効化
2. フィールドグループ「PDFブックレット設定」が作成されているか確認
3. 以下のフィールドが設定されているか確認：
   - `title` (テキスト)
   - `content` (テキストエリア)
   - `photo1` (画像)
   - `caption1` (テキスト)
   - `photo2` (画像)
   - `caption2` (テキスト)

## 📋 デバッグ用ログの確認方法

### エラーログの場所
- サーバーのエラーログ: `/var/log/apache2/error.log` または `/var/log/nginx/error.log`
- WordPressデバッグログ: `/wp-content/debug.log`

### 確認すべきログメッセージ
```
PDF Booklet functions.php loaded at 2025-01-15 14:30:25
admin_head-post.php hook triggered
Page ID: 125, Template: template-text-photo2.php
PDF Booklet template detected, hiding content editor
edit_form_after_title hook triggered for post ID: 125
Adding PDF Booklet widget for page 125
```

## ⚙️ GitHub Actions設定

PDF生成機能を使用するには、以下の設定が必要です：

### 1. GitHub設定
**設定** → **PDFブックレット** で以下を設定：
- GitHub Personal Access Token
- リポジトリ名 (例: `username/repository`)
- ワークフローID (例: `generate-pdf.yml`)

### 2. GitHub Secrets
GitHubリポジトリの **Settings** → **Secrets and variables** → **Actions** で以下を設定：
- `WP_URL`: WordPressサイトのURL
- `WP_USER`: WordPress管理者ユーザー名
- `WP_PASS`: WordPress管理者パスワード
- `FTP_SERVER`: FTPサーバーアドレス
- `FTP_USERNAME`: FTPユーザー名
- `FTP_PASSWORD`: FTPパスワード
- `FTP_DESTINATION_PATH`: アップロード先パス

## 📞 サポート

問題が解決しない場合は、以下の情報を含めてお問い合わせください：
1. WordPressのバージョン
2. 使用中のテーマ名
3. エラーログの内容
4. 実行した手順の詳細

