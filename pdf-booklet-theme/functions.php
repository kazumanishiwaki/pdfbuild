<?php
/**
 * functions.php – Page‑specific PDF generation & button
 */

// 出力バッファリングを開始してヘッダーエラーを防ぐ
if (!headers_sent()) {
    ob_start();
}

// デバッグ用: functions.phpが読み込まれているかを確認
error_log('PDF Booklet functions.php loaded at ' . date('Y-m-d H:i:s'));

// ========================================
// ACF JSON設定
// ========================================

// ACF JSONファイルの保存パスを設定
add_filter('acf/settings/save_json', function($path) {
    return get_stylesheet_directory() . '/acf-json';
});

// ACF JSONファイルの読み込みパスを設定
add_filter('acf/settings/load_json', function($paths) {
    // 既存のパスを削除
    unset($paths[0]);
    
    // 新しいパスを追加
    $paths[] = get_stylesheet_directory() . '/acf-json';
    
    return $paths;
});

// ACFフィールドグループの読み込み確認（デバッグ用）
add_action('acf/init', function() {
    error_log('ACF initialized - checking field groups');
    
    if (function_exists('acf_get_field_groups')) {
        $groups = acf_get_field_groups();
        error_log('ACF field groups found: ' . count($groups));
        
        foreach ($groups as $group) {
            error_log('Field group: ' . $group['title'] . ' (key: ' . $group['key'] . ')');
        }
    }
});

// 固定ページから本文エディタを外す
add_action('init', function () {
    remove_post_type_support('page', 'editor');
});

// 固定ページからコメント・ディスカッション機能を削除
add_action('init', function () {
    remove_post_type_support('page', 'comments');
    remove_post_type_support('page', 'trackbacks');
});

// Mixed Content問題を解決: HTTPSでの画像URL強制
add_filter('wp_get_attachment_url', function($url) {
    return str_replace('http://', 'https://', $url);
});

add_filter('wp_get_attachment_image_src', function($image) {
    if (is_array($image) && isset($image[0])) {
        $image[0] = str_replace('http://', 'https://', $image[0]);
    }
    return $image;
});

// デバッグ用: WordPressの管理画面でアラートを表示
add_action('admin_notices', function() {
    if (current_user_can('manage_options')) {
        $current_theme = wp_get_theme();
        $theme_name = $current_theme->get('Name');
        $theme_dir = get_template_directory();
        
        echo '<div class="notice notice-info is-dismissible">';
        echo '<p><strong>PDF Booklet Debug:</strong></p>';
        echo '<ul>';
        echo '<li>functions.phpが正常に読み込まれました</li>';
        echo '<li>現在のテーマ: ' . esc_html($theme_name) . '</li>';
        echo '<li>テーマディレクトリ: ' . esc_html($theme_dir) . '</li>';
        echo '<li>PDF対応テンプレート: ' . implode(', ', array_keys(pdf_booklet_get_supported_templates())) . '</li>';
        echo '</ul>';
        echo '</div>';
    }
});

/**
 * PDF Booklet システム
 * 
 * このシステムでは、PDFブックレット用のテンプレートを固定配列として定義しています。
 * 
 * テンプレートの命名規則:
 * - PDFブックレットテンプレートのファイル名は 'template-' で始まることを推奨
 * - テンプレートファイル内には「Template Name: PDF Booklet XXX」のヘッダーが必要です
 */

// PDF対応テンプレートの配列を定義（ハードコーディング方式）
function pdf_booklet_get_supported_templates() {
    // PDFブックレット対応テンプレートをハードコーディングで定義
    $templates = [
        // 既存テンプレート
        'template-heading-text.php'                          => '① 見出し＋本文',
        'template-main-heading-2.php'                        => '② 大見出し＋（見出し＋本文）×２',
        'template-main-heading-3.php'                        => '③ 大見出し＋（見出し＋本文）×３',
        'template-image-caption-1.php'                       => '④ 画像＋キャプション',
        'template-image-caption-2.php'                       => '⑤ （画像＋キャプション）×２',
        'template-image-caption-3.php'                       => '⑥ （画像＋キャプション）×３',
        'template-image-caption-4.php'                       => '⑦ （画像＋キャプション）×４',
        'template-timeline.php'                              => '⑧ 年表（年、月、出来事）×100',
        
        // 新規追加テンプレート
        'template-heading-two-columns-text.php'              => '⑨ 見出し＋左右カラム本文',
        'template-heading-text-image-1.php'                  => '⑩ 見出し＋左本文＋右画像キャプション',
        'template-heading-text-image-2.php'                  => '⑪ 見出し＋左本文＋右画像キャプション×2',
        'template-heading-text-image-3-large-medium.php'     => '⑫ 見出し＋左本文＋右画像キャプション×3（大1中2）',
        'template-heading-text-image-4-large-small.php'      => '⑬ 見出し＋左本文＋右画像キャプション×4（大1小3）',
        'template-heading-text-image-4-medium.php'           => '⑭ 見出し＋左本文＋右画像キャプション×4（中4）',
        'template-heading-text-image-5-medium-small.php'     => '⑮ 見出し＋左本文＋右画像キャプション×5（中2小3）',
        'template-image-caption-only.php'                    => '⑯ 画像キャプションのみ',
        'template-image-caption-3-medium-small.php'          => '⑰ 画像キャプション×3（中1小2）'
    ];
    
    return $templates;
}

// 現在のテンプレートがPDF対応か判定する関数
function is_pdf_booklet_template($template) {
    $supported_templates = pdf_booklet_get_supported_templates();
    return array_key_exists($template, $supported_templates);
}

// 日本時間でのタイムスタンプを取得する関数
function get_jst_timestamp($timestamp = null) {
    if ($timestamp === null) {
        $timestamp = current_time('timestamp');
    }
    
    $date = new DateTime();
    $date->setTimestamp($timestamp);
    $date->setTimezone(new DateTimeZone('Asia/Tokyo'));
    
    return $date->format('Y-m-d H:i:s');
}

// ページ属性メタボックスを強制的に有効化
add_action('add_meta_boxes', function() {
    // ページ属性メタボックスを追加（存在しない場合）
    add_meta_box(
        'pageparentdiv',
        __('Page Attributes'),
        'page_attributes_meta_box',
        'page',
        'side',
        'core'
    );
});

// ページでテンプレート選択を有効にする
add_filter('theme_page_templates', function($templates) {
    $pdf_templates = pdf_booklet_get_supported_templates();
    foreach ($pdf_templates as $file => $name) {
        $templates[$file] = 'PDF Booklet: ' . $name;
    }
    return $templates;
});

// ページ属性の表示を強制
add_action('admin_head-post.php', function() {
    global $post;
    if ($post && $post->post_type === 'page') {
        ?>
        <script>
        // ページ属性メタボックスが非表示の場合は表示する
        jQuery(document).ready(function($) {
            if ($('#pageparentdiv').length === 0) {
                console.log('Page attributes metabox not found, will add custom selector');
            } else if ($('#pageparentdiv').is(':hidden')) {
                console.log('Page attributes metabox is hidden, showing it');
                $('#pageparentdiv').show();
            }
        });
        </script>
        <?php
    }
});

// PDFブックレット設定ページを追加
add_action('admin_menu', function(){
    add_options_page('PDFブックレット設定', 'PDFブックレット', 'manage_options', 'pdf-booklet-settings', 'render_pdf_settings_page');
    
    // PDFファイル管理ページを追加
    add_menu_page(
        'PDFブックレット管理', 
        'PDFブックレット', 
        'manage_options', 
        'pdf-booklet-manager', 
        'render_pdf_manager_page',
        'dashicons-book',
        30
    );
});

// 設定ページの登録
add_action('admin_init', function(){
    register_setting('pdf-booklet-settings-group', 'github_actions_token');
    register_setting('pdf-booklet-settings-group', 'github_repo');
    register_setting('pdf-booklet-settings-group', 'github_workflow_id');
    register_setting('pdf-booklet-settings-group', 'additional_page_ids');
});

// 設定ページの表示
function render_pdf_settings_page(){
    ?>
    <div class="wrap">
        <h1>PDFブックレット設定</h1>
        <form method="post" action="options.php">
            <?php settings_fields('pdf-booklet-settings-group'); ?>
            <?php do_settings_sections('pdf-booklet-settings-group'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">GitHub トークン</th>
                    <td>
                        <input type="text" name="github_actions_token" value="<?php echo esc_attr(get_option('github_actions_token')); ?>" class="regular-text" />
                        <p class="description">GitHubのパーソナルアクセストークン。以下の権限が必要：<code>repo</code>（リポジトリアクセス）と<code>workflow</code>（Actionsのトリガー）</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">GitHubリポジトリ</th>
                    <td>
                        <input type="text" name="github_repo" value="<?php echo esc_attr(get_option('github_repo')); ?>" class="regular-text" />
                        <p class="description">例: owner/repository</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">ワークフローID</th>
                    <td>
                        <input type="text" name="github_workflow_id" value="<?php echo esc_attr(get_option('github_workflow_id')); ?>" class="regular-text" />
                        <p class="description">例: generate-pdf.yml （または数値ID）</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">追加のページID</th>
                    <td>
                        <input type="text" name="additional_page_ids" value="<?php echo esc_attr(get_option('additional_page_ids')); ?>" class="regular-text" />
                        <p class="description">（オプション）複数ページのデータを取得する場合、カンマ区切りでIDを指定（例: 123,456,789）</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// PDFファイル管理ページの表示
function render_pdf_manager_page() {
    $pdf_dir = wp_upload_dir()['basedir'] . '/pdf-booklet/';
    $pdf_url = wp_upload_dir()['baseurl'] . '/pdf-booklet/';
    
    // ディレクトリが存在しなければ作成
    if (!file_exists($pdf_dir)) {
        wp_mkdir_p($pdf_dir);
    }
    
    // PDFファイルを検索
    $pdf_files = glob($pdf_dir . '*.pdf');
    
    // 削除処理があれば実行
    if (isset($_POST['delete_pdf']) && isset($_POST['pdf_file']) && check_admin_referer('delete_pdf_file')) {
        $file_to_delete = sanitize_text_field($_POST['pdf_file']);
        $full_path = $pdf_dir . basename($file_to_delete);
        
        if (file_exists($full_path) && unlink($full_path)) {
            echo '<div class="notice notice-success"><p>PDFファイルを削除しました。</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>PDFファイルの削除に失敗しました。</p></div>';
        }
        
        // 削除後にファイルリストを更新
        $pdf_files = glob($pdf_dir . '*.pdf');
    }
    
    // 一括生成処理
    if (isset($_POST['generate_all_pdfs']) && check_admin_referer('generate_all_pdfs')) {
        $token = get_option('github_actions_token');
        $repo = get_option('github_repo');
        $wf_id = get_option('github_workflow_id');
        
        if (!$token || !$repo || !$wf_id) {
            echo '<div class="notice notice-error"><p>GitHub設定が不完全です。設定ページでtoken/repo/workflow_idを設定してください。</p></div>';
        } else {
            // PDF Bookletテンプレートを使用しているページを取得
            $pdf_pages = get_pages([
                'meta_key' => '_wp_page_template',
                'meta_value' => array_keys(pdf_booklet_get_supported_templates()),
                'meta_compare' => 'IN'
            ]);
            
            $page_ids = [];
            foreach ($pdf_pages as $page) {
                $page_ids[] = $page->ID;
            }
            
            if (empty($page_ids)) {
                echo '<div class="notice notice-warning"><p>PDFブックレットテンプレートを使用しているページが見つかりません。</p></div>';
            } else {
                $all_ids = implode(',', $page_ids);
                $requests_sent = 0;
                
                // 各ページIDごとにGitHub Actionsを起動
                foreach ($page_ids as $pid) {
                    $body = json_encode([
                        'ref' => 'main',
                        'inputs' => [
                            'wp_post_ids' => $all_ids,
                            'target_slug' => (string)$pid
                        ]
                    ]);
                    
                    $resp = wp_remote_post(
                        "https://api.github.com/repos/{$repo}/actions/workflows/{$wf_id}/dispatches",
                        ['headers' => ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/vnd.github.v3+json', 'Content-Type' => 'application/json'], 'body' => $body]
                    );
                    
                    if (!is_wp_error($resp)) {
                        $status_code = wp_remote_retrieve_response_code($resp);
                        if ($status_code >= 200 && $status_code < 300) {
                            $requests_sent++;
                        }
                    }
                }
                
                if ($requests_sent > 0) {
                    echo '<div class="notice notice-success"><p>' . $requests_sent . 'ページのPDFジョブを開始しました。生成には数分かかる場合があります。</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>PDFジョブの起動に失敗しました。GitHub設定を確認してください。</p></div>';
                }
            }
        }
    }
    
    ?>
    <div class="wrap">
        <h1>PDFブックレット管理</h1>
        
        <div class="postbox" style="padding: 15px; margin-bottom: 20px;">
            <h2>PDF一括生成</h2>
            <p>PDFブックレットテンプレートを使用している全ページのPDFを一括生成します</p>
            <form method="post" action="">
                <?php wp_nonce_field('generate_all_pdfs'); ?>
                <input type="submit" name="generate_all_pdfs" class="button button-primary" value="全ページのPDFを生成" onclick="return confirm('全ページのPDFを生成します。よろしいですか？');">
            </form>
        </div>
        
        <h2>生成済みPDFファイル一覧</h2>
        <?php if (empty($pdf_files)): ?>
            <p>PDFファイルはまだ生成されていません。</p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ファイル名</th>
                        <th>関連ページ</th>
                        <th>サイズ</th>
                        <th>最終更新日</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pdf_files as $pdf_file): 
                        $filename = basename($pdf_file);
                        $filesize = size_format(filesize($pdf_file));
                        $modified = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), filemtime($pdf_file));
                        $file_url = $pdf_url . $filename;
                        
                        // 関連ページを特定する
                        $related_page_id = null;
                        $related_page_title = '';
                        
                        // booklet-123.pdfの形式からIDを抽出
                        if (preg_match('/booklet-(\d+)\.pdf/', $filename, $matches)) {
                            $related_page_id = $matches[1];
                        } 
                        // スラッグベースのファイル名からページを探す
                        else {
                            $slug = pathinfo($filename, PATHINFO_FILENAME);
                            $pages = get_posts([
                                'name' => $slug,
                                'post_type' => 'page',
                                'post_status' => 'publish',
                                'posts_per_page' => 1
                            ]);
                            
                            if (!empty($pages)) {
                                $related_page_id = $pages[0]->ID;
                            }
                        }
                        
                        if ($related_page_id) {
                            $related_page_title = get_the_title($related_page_id);
                            $edit_link = get_edit_post_link($related_page_id);
                            $view_link = get_permalink($related_page_id);
                        }
                    ?>
                    <tr>
                        <td>
                            <strong><a href="<?php echo esc_url($file_url); ?>" target="_blank"><?php echo esc_html($filename); ?></a></strong>
                        </td>
                        <td>
                            <?php if ($related_page_id): ?>
                                <a href="<?php echo esc_url($edit_link); ?>"><?php echo esc_html($related_page_title); ?></a>
                                (<a href="<?php echo esc_url($view_link); ?>" target="_blank">表示</a>)
                            <?php else: ?>
                                <em>関連ページが見つかりません</em>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($filesize); ?></td>
                        <td><?php echo esc_html($modified); ?></td>
                        <td>
                            <a href="<?php echo esc_url($file_url); ?>" target="_blank" class="button button-small">表示</a>
                            
                            <form method="post" action="" style="display:inline-block;">
                                <?php wp_nonce_field('delete_pdf_file'); ?>
                                <input type="hidden" name="pdf_file" value="<?php echo esc_attr($filename); ?>">
                                <input type="submit" name="delete_pdf" class="button button-small button-link-delete" value="削除" onclick="return confirm('このPDFを削除してもよろしいですか？');">
                            </form>
                            
                            <?php if ($related_page_id): ?>
                            <a href="<?php echo esc_url(admin_url('post.php?post=' . $related_page_id . '&action=edit')); ?>" class="button button-small">ページを編集</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}

// PDFブックレットテンプレート選択用のドロップダウンを追加
add_filter('theme_page_templates', function($post_templates) {
    $pdf_templates = pdf_booklet_get_supported_templates();
    foreach ($pdf_templates as $file => $name) {
        $post_templates[$file] = 'PDF Booklet: ' . $name;
    }
    return $post_templates;
});




// ACFテンプレート使用時に本文欄を非表示にする
add_action('admin_init', function() {
    // テンプレートに基づいて本文欄の表示/非表示を切り替えるための処理
    add_action('add_meta_boxes', function() {
        global $post;
        if (!$post) return;
        
        $template = get_page_template_slug($post->ID);
        if (is_pdf_booklet_template($template)) {
            remove_post_type_support('page', 'editor');
        }
    }, 10);
    
    // タイトルは残して、エディタ領域のみを非表示にするCSSを追加
    add_action('admin_head', function() {
        global $post;
        if (!$post) return;
        
        $template = get_page_template_slug($post->ID);
        if (!is_pdf_booklet_template($template)) return;
        ?>
        <style>
            /* エディタ領域のみを非表示にする強力なセレクタ */
            .block-editor-writing-flow__click-redirect,
            .wp-block[data-type="core/paragraph"],
            .wp-block[data-type="core/code"],
            .wp-block-post-content,
            .editor-styles-wrapper .wp-block,
            .wp-block-freeform,
            .block-editor-default-block-appender,
            .components-placeholder,
            .wp-block-post-content-placeholder,
            .block-editor-block-list__layout,
            .block-editor-block-contextual-toolbar {
                display: none !important;
            }
            
            /* タイトルを確実に表示 */
            .editor-post-title, 
            .editor-post-title__block,
            .edit-post-visual-editor__post-title-wrapper {
                display: block !important;
                margin-bottom: 20px !important;
            }
            
            /* 本文欄なしの警告メッセージを非表示 */
            .editor-post-content .components-notice,
            .block-editor-warning {
                display: none !important;
            }
            
            /* ACFメタボックスをより見やすく */
            .acf-postbox {
                margin-top: 20px !important;
            }
            
            /* ACFフィールド内の余分なスクロールを防止 */
            .acf-fields {
                max-height: none !important;
            }
            
            /* ACFフィールドの表示を改善 */
            .acf-fields > .acf-field {
                padding: 15px 12px !important;
                border-top: 1px solid #eee !important;
            }
        </style>
        <?php
    });
});

// 固定ページ編集画面で本文エディタを非表示にする（強制実行版）
add_action('admin_head-post.php', function() {
    global $post, $typenow;
    
    // デバッグ出力
    error_log('admin_head-post.php hook triggered');
    
    if (($post && $post->post_type === 'page') || $typenow === 'page') {
        $template = '';
        if ($post) {
            $template = get_page_template_slug($post->ID);
        }
        error_log('Page ID: ' . ($post ? $post->ID : 'new') . ', Template: ' . $template);
        
        // 常にPDF Booklet用のJavaScriptとCSSを読み込み（動的対応）
        error_log('Loading PDF Booklet scripts for all page editing');
        ?>
        <style>
        /* PDF Booklet用スタイル */
        .pdf-booklet-active #postdivrich,
        .pdf-booklet-active #wp-content-editor-tools,
        .pdf-booklet-active .wp-editor-container {
            display: none !important;
        }
        
        /* Gutenbergエディタも非表示 */
        .pdf-booklet-active .block-editor-writing-flow,
        .pdf-booklet-active .edit-post-visual-editor,
        .pdf-booklet-active .editor-styles-wrapper {
            display: none !important;
        }
        
        /* 本文エディタの代替メッセージ */
        .content-editor-replacement {
            background: #fff8e1;
            border: 1px solid #ffb900;
            border-radius: 4px;
            padding: 15px;
            margin: 20px 0;
        }
        
        .content-editor-replacement h3 {
            margin-top: 0;
            color: #8a6914;
        }
        
        .content-editor-replacement p {
            margin-bottom: 0;
            color: #8a6914;
        }
        
        /* PDF Bookletウィジェット用スタイル */
        .pdf-booklet-meta {
            background: #f9f9f9;
            border: 1px solid #ddd;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            console.log('PDF Booklet script loaded');
            
            // デバッグ: ページ属性関連の要素をすべて検索
            console.log('=== DEBUG: Searching for template elements ===');
            console.log('All select elements:', $('select').map(function() { return this.id + ' (' + this.name + ')'; }).get());
            console.log('Elements with "template" in id:', $('[id*="template"]').map(function() { return this.id + ' (' + this.tagName + ')'; }).get());
            console.log('Elements with "template" in name:', $('[name*="template"]').map(function() { return this.name + ' (' + this.tagName + ')'; }).get());
            console.log('Page attributes metabox:', $('#pageparentdiv').length ? 'Found' : 'Not found');
            console.log('=== END DEBUG ===');
            
            // カスタムテンプレートセレクターを直接JavaScriptで追加
            function addCustomTemplateSelector() {
                if ($('#pdf-booklet-template-selector').length === 0) {
                    console.log('Adding custom template selector via JavaScript');
                    
                    var currentTemplate = '<?php echo esc_js(get_page_template_slug($post->ID ?? 0)); ?>';
                    console.log('Current template from PHP:', currentTemplate);
                    
                    var templateOptions = {
                        '': 'デフォルトテンプレート',
                        'template-heading-text.php': '① 見出し＋本文',
                        'template-main-heading-2.php': '② 大見出し＋（見出し＋本文）×２',
                        'template-main-heading-3.php': '③ 大見出し＋（見出し＋本文）×３',
                        'template-image-caption-1.php': '④ 画像＋キャプション',
                        'template-image-caption-2.php': '⑤ （画像＋キャプション）×２',
                        'template-image-caption-3.php': '⑥ （画像＋キャプション）×３',
                        'template-image-caption-4.php': '⑦ （画像＋キャプション）×４',
                        'template-timeline.php': '⑧ 年表（年、月、出来事）×100',
                        'template-heading-two-columns-text.php': '⑨ 見出し＋左右カラム本文',
                        'template-heading-text-image-1.php': '⑩ 見出し＋左本文＋右画像キャプション',
                        'template-heading-text-image-2.php': '⑪ 見出し＋左本文＋右画像キャプション×2',
                        'template-heading-text-image-3-large-medium.php': '⑫ 見出し＋左本文＋右画像キャプション×3（大1中2）',
                        'template-heading-text-image-4-large-small.php': '⑬ 見出し＋左本文＋右画像キャプション×4（大1小3）',
                        'template-heading-text-image-4-medium.php': '⑭ 見出し＋左本文＋右画像キャプション×4（中4）',
                        'template-heading-text-image-5-medium-small.php': '⑮ 見出し＋左本文＋右画像キャプション×5（中2小3）',
                        'template-image-caption-only.php': '⑯ 画像キャプションのみ',
                        'template-image-caption-3-medium-small.php': '⑰ 画像キャプション×3（中1小2）'
                    };
                    
                    var optionsHtml = '';
                    for (var value in templateOptions) {
                        var selected = (currentTemplate === value) ? ' selected' : '';
                        optionsHtml += '<option value="' + value + '"' + selected + '>' + templateOptions[value] + '</option>';
                    }
                    
                    var selectorHtml = '<div id="pdf-booklet-template-selector" style="background: #f0f6fc; border: 1px solid #c3c4c7; padding: 15px; margin: 20px 0; border-radius: 4px;">' +
                        '<h3 style="margin-top: 0;">🎨 PDFブックレット テンプレート選択</h3>' +
                        '<p style="margin-bottom: 10px;">このページで使用するテンプレートを選択してください：</p>' +
                        '<select id="pdf-custom-template-selector" name="page_template" style="width: 100%; padding: 8px;">' +
                        optionsHtml +
                        '</select>' +
                        '<p style="margin-top: 10px; font-size: 12px; color: #666;">' +
                        '💡 PDFブックレットテンプレートを選択すると、PDF生成機能が有効になります。' +
                        '</p>' +
                        '</div>' +
                    
                    // タイトルの後に挿入
                    if ($('#titlewrap').length) {
                        $('#titlewrap').after(selectorHtml);
                        console.log('Custom selector added after title');
                    } else if ($('#title').length) {
                        $('#title').closest('.wrap').find('h1').after(selectorHtml);
                        console.log('Custom selector added after h1');
                    } else {
                        $('.wrap').prepend(selectorHtml);
                        console.log('Custom selector prepended to wrap');
                    }
                    
                    // イベントリスナーを追加
                    $('#pdf-custom-template-selector').on('change', function() {
                        var selectedTemplate = $(this).val();
                        console.log('Custom template selector changed to:', selectedTemplate);
                        
                        // 標準のpage_templateがあれば同期
                        if ($('#page_template').length) {
                            $('#page_template').val(selectedTemplate);
                        }
                        
                        // 手動でテンプレート変更イベントをトリガー
                        $(document).trigger('pdf-template-changed', [selectedTemplate]);
                    });
                    
                    // 初期状態でテンプレートが選択されている場合はイベントを発火
                    if (currentTemplate) {
                        setTimeout(function() {
                            $(document).trigger('pdf-template-changed', [currentTemplate]);
                        }, 100);
                    }
                } else {
                    console.log('Custom template selector already exists');
                }
            }
            
            // DOM読み込み後に追加
            setTimeout(addCustomTemplateSelector, 500);
            
            // テンプレート変更を監視する関数
            function handleTemplateChange() {
                // 複数のセレクターを試す
                var templateElement = $('#page_template').length ? $('#page_template') : 
                                    $('select[name="page_template"]').length ? $('select[name="page_template"]') :
                                    $('select[id*="template"]').length ? $('select[id*="template"]') : null;
                
                var template = templateElement ? templateElement.val() : 'not_found';
                
                console.log('Template element found:', templateElement ? templateElement.attr('id') : 'none');
                console.log('Template changed to:', template);
                console.log('Available templates:', templateElement ? templateElement.find('option').map(function() { return $(this).val() + ':' + $(this).text(); }).get() : 'none');
                
                // PDF Bookletテンプレートかどうかを判定（新しいテンプレート対応）
                var pdfTemplates = [
                    'template-heading-text.php',
                    'template-main-heading-2.php',
                    'template-main-heading-3.php',
                    'template-image-caption-1.php',
                    'template-image-caption-2.php',
                    'template-image-caption-3.php',
                    'template-image-caption-4.php',
                    'template-timeline.php',
                    'template-heading-two-columns-text.php',
                    'template-heading-text-image-1.php',
                    'template-heading-text-image-2.php',
                    'template-heading-text-image-3-large-medium.php',
                    'template-heading-text-image-4-large-small.php',
                    'template-heading-text-image-4-medium.php',
                    'template-heading-text-image-5-medium-small.php',
                    'template-image-caption-only.php',
                    'template-image-caption-3-medium-small.php'
                ];
                
                var isPdfBookletTemplate = pdfTemplates.indexOf(template) !== -1 ||
                                         (templateElement && templateElement.find('option:selected').text().indexOf('PDF Booklet') !== -1) ||
                                         (templateElement && templateElement.find('option:selected').text().indexOf('①') !== -1) ||
                                         (templateElement && templateElement.find('option:selected').text().indexOf('②') !== -1) ||
                                         (templateElement && templateElement.find('option:selected').text().indexOf('③') !== -1) ||
                                         (templateElement && templateElement.find('option:selected').text().indexOf('④') !== -1) ||
                                         (templateElement && templateElement.find('option:selected').text().indexOf('⑤') !== -1) ||
                                         (templateElement && templateElement.find('option:selected').text().indexOf('⑥') !== -1) ||
                                         (templateElement && templateElement.find('option:selected').text().indexOf('⑦') !== -1) ||
                                         (templateElement && templateElement.find('option:selected').text().indexOf('⑧') !== -1);
                
                console.log('Is PDF Booklet template:', isPdfBookletTemplate);
                console.log('Selected option text:', templateElement ? templateElement.find('option:selected').text() : 'none');
                
                if (isPdfBookletTemplate) {
                    console.log('PDF Booklet template selected');
                    
                    // bodyにクラスを追加
                    $('body').addClass('pdf-booklet-active');
                    
                    // 説明メッセージを追加（重複チェック）
                    if ($('.content-editor-replacement').length === 0) {
                        $('#postdivrich').after('<div class="content-editor-replacement"><h3>📝 コンテンツの入力について</h3><p><strong>このページでは固定ページの本文は使用されません。</strong></p><p>PDFに表示するコンテンツは、下記の「PDFブックレット設定」フィールドで入力してください。</p></div>');
                    }
                    
                    // タイトル下の説明を追加（重複チェック）
                    if ($('#title').next('p').length === 0) {
                        $('#title').after('<p style="margin: 10px 0; color: #666; font-size: 13px;">💡 このページタイトルはPDFには表示されません。PDFタイトルは下記のACFフィールドで設定してください。</p>');
                    }
                    
                    // PDF Bookletウィジェットを追加（ACFがない場合の代替）
                    addPdfBookletWidget();
                    
                } else {
                    console.log('Other template selected');
                    $('body').removeClass('pdf-booklet-active');
                    $('.content-editor-replacement').remove();
                    $('#title').next('p').remove();
                    $('.pdf-booklet-meta').remove();
                }
            }
            
            // PDF Bookletウィジェットを追加する関数
            function addPdfBookletWidget() {
                if ($('.pdf-booklet-meta').length === 0) {
                    var postId = $('#post_ID').val() || 'new';
                    var widgetHtml = '<div class="pdf-booklet-meta">' +
                        '<h3 style="margin-top: 0;">📖 PDFブックレット</h3>' +
                        '<div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">' +
                        '<div style="flex: 1;">' +
                        '<span class="dashicons dashicons-warning" style="color: orange;"></span>' +
                        '<strong>PDF未生成</strong>' +
                        '</div>' +
                        '<div>' +
                        '<button type="button" class="button button-primary" disabled>PDF生成 (保存後に利用可能)</button>' +
                        '</div>' +
                        '</div>' +
                        '<div style="font-size: 12px; color: #666; border-top: 1px solid #ddd; padding-top: 10px;">' +
                        '<strong>注意:</strong> PDFを生成するには、まずページを保存してください。' +
                        '</div>' +
                        '</div>';
                    
                    $('#postdivrich').before(widgetHtml);
                }
            }
            
            // カスタムテンプレート変更処理関数
            function handleCustomTemplateChange(template) {
                console.log('Handling custom template change:', template);
                
                // PDF Bookletテンプレートかどうかを判定
                var pdfTemplates = [
                    'template-heading-text.php',
                    'template-main-heading-2.php',
                    'template-main-heading-3.php',
                    'template-image-caption-1.php',
                    'template-image-caption-2.php',
                    'template-image-caption-3.php',
                    'template-image-caption-4.php',
                    'template-timeline.php',
                    'template-heading-two-columns-text.php',
                    'template-heading-text-image-1.php',
                    'template-heading-text-image-2.php',
                    'template-heading-text-image-3-large-medium.php',
                    'template-heading-text-image-4-large-small.php',
                    'template-heading-text-image-4-medium.php',
                    'template-heading-text-image-5-medium-small.php',
                    'template-image-caption-only.php',
                    'template-image-caption-3-medium-small.php'
                ];
                
                var isPdfBookletTemplate = pdfTemplates.indexOf(template) !== -1;
                
                console.log('Is PDF Booklet template (custom):', isPdfBookletTemplate);
                
                if (isPdfBookletTemplate) {
                    console.log('PDF Booklet template selected via custom selector');
                    
                    // bodyにクラスを追加
                    $('body').addClass('pdf-booklet-active');
                    
                    // 説明メッセージを追加（重複チェック）
                    if ($('.content-editor-replacement').length === 0) {
                        $('#postdivrich').after('<div class="content-editor-replacement"><h3>📝 コンテンツの入力について</h3><p><strong>このページでは固定ページの本文は使用されません。</strong></p><p>PDFに表示するコンテンツは、下記の「PDFブックレット設定」フィールドで入力してください。</p></div>');
                    }
                    
                    // タイトル下の説明を追加（重複チェック）
                    if ($('#title').next('p').length === 0) {
                        $('#title').after('<p style="margin: 10px 0; color: #666; font-size: 13px;">💡 このページタイトルはPDFには表示されません。PDFタイトルは下記のACFフィールドで設定してください。</p>');
                    }
                    
                    // PDF Bookletウィジェットを追加（ACFがない場合の代替）
                    addPdfBookletWidget();
                    
                } else {
                    console.log('Other template selected via custom selector');
                    $('body').removeClass('pdf-booklet-active');
                    $('.content-editor-replacement').remove();
                    $('#title').next('p').remove();
                    $('.pdf-booklet-meta').remove();
                }
            }
            
            // 初期状態をチェック
            setTimeout(function() {
                console.log('Initial template check...');
                handleTemplateChange();
            }, 500);
            
            // テンプレート変更イベントを監視（複数のセレクターに対応）
            $(document).on('change', '#page_template, select[name="page_template"], select[id*="template"], #pdf-custom-template-selector', function() {
                console.log('Template change event triggered');
                handleTemplateChange();
            });
            
            // カスタムイベントも監視
            $(document).on('pdf-template-changed', function(event, template) {
                console.log('Custom PDF template changed event:', template);
                // カスタムセレクターの値を使用してテンプレート変更を処理
                handleCustomTemplateChange(template);
            });
            
            // ページ読み込み時に再度チェック（遅延実行）
            setTimeout(function() {
                console.log('Delayed template check...');
                handleTemplateChange();
            }, 2000);
            
            // DOM変更を監視（テンプレート要素が後から追加される場合に対応）
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'childList') {
                        var templateElement = $('#page_template, select[name="page_template"], select[id*="template"]');
                        if (templateElement.length && !templateElement.data('listener-added')) {
                            console.log('Template element detected via MutationObserver');
                            templateElement.data('listener-added', true);
                            templateElement.on('change', handleTemplateChange);
                            handleTemplateChange();
                        }
                    }
                });
            });
            
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        });
        </script>
        <?php
    }
});

// 新規ページ作成画面でも同様の処理
add_action('admin_head-post-new.php', function() {
    global $typenow;
    
    if ($typenow === 'page') {
        ?>
        <script>
        jQuery(document).ready(function($) {
            console.log('New page script loaded');
            
            // 初期状態でPDF Bookletテンプレートを選択
            function autoSelectTemplate() {
                var templateElement = $('#page_template').length ? $('#page_template') : 
                                    $('select[name="page_template"]').length ? $('select[name="page_template"]') :
                                    $('select[id*="template"]').length ? $('select[id*="template"]') : null;
                
                if (templateElement && templateElement.length) {
                    console.log('Template element found for auto-selection:', templateElement.attr('id'));
                    console.log('Available options:', templateElement.find('option').map(function() { return $(this).val() + ':' + $(this).text(); }).get());
                    
                    // PDF Bookletテンプレートを探して選択
                    var pdfOption = templateElement.find('option').filter(function() {
                        var text = $(this).text();
                        var value = $(this).val();
                        return value === 'template-text-photo2.php' || 
                               text.indexOf('PDF Booklet') !== -1 || 
                               text.indexOf('テキスト+写真') !== -1;
                    }).first();
                    
                    if (pdfOption.length) {
                        templateElement.val(pdfOption.val()).trigger('change');
                        console.log('Auto-selected PDF Booklet template:', pdfOption.val(), pdfOption.text());
                    } else {
                        console.log('PDF Booklet template option not found');
                    }
                } else {
                    console.log('Template element not found, retrying...');
                    setTimeout(autoSelectTemplate, 500);
                }
            }
            
            setTimeout(autoSelectTemplate, 500);
        });
        </script>
        <?php
    }
});

// カスタムテンプレートセレクターを追加（ページ属性が見つからない場合の代替）
add_action('edit_form_after_title', function($post) {
    if ($post->post_type !== 'page') {
        return;
    }
    
    $current_template = get_page_template_slug($post->ID);
    $available_templates = pdf_booklet_get_supported_templates();
    
    ?>
    <div id="pdf-booklet-template-selector" style="background: #f0f6fc; border: 1px solid #c3c4c7; padding: 15px; margin: 20px 0; border-radius: 4px;">
        <h3 style="margin-top: 0;">🎨 PDFブックレット テンプレート選択</h3>
        <p style="margin-bottom: 10px;">このページで使用するテンプレートを選択してください：</p>
        
        <select id="pdf-custom-template-selector" name="page_template" style="width: 100%; padding: 8px;">
            <option value="">デフォルトテンプレート</option>
            <?php foreach ($available_templates as $file => $name): ?>
                <option value="<?php echo esc_attr($file); ?>" <?php selected($current_template, $file); ?>>
                    <?php echo esc_html($name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <p style="margin-top: 10px; font-size: 12px; color: #666;">
            💡 「テキスト+写真2枚形式」を選択すると、PDF生成機能が有効になります。
        </p>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        console.log('Custom template selector added');
        
        // カスタムセレクターの変更を監視
        $('#pdf-custom-template-selector').on('change', function() {
            var selectedTemplate = $(this).val();
            console.log('Custom template selector changed to:', selectedTemplate);
            
            // 標準のpage_templateがあれば同期
            if ($('#page_template').length) {
                $('#page_template').val(selectedTemplate);
            }
            
            // 手動でテンプレート変更イベントをトリガー
            $(document).trigger('pdf-template-changed', [selectedTemplate]);
        });
        
        // 初期値を設定
        setTimeout(function() {
            var currentTemplate = $('#pdf-custom-template-selector').val();
            if (currentTemplate) {
                $(document).trigger('pdf-template-changed', [currentTemplate]);
            }
        }, 500);
    });
    </script>
    <?php
});

// ページ編集画面にPDF Bookletウィジェットを追加（日本時間対応）
add_action('edit_form_after_title', function($post) {
    error_log('edit_form_after_title hook triggered for post ID: ' . $post->ID);
    
    if ($post->post_type !== 'page') {
        error_log('Not a page, skipping PDF widget');
        return;
    }
    
    $template = get_page_template_slug($post->ID);
    error_log('Template for page ' . $post->ID . ': ' . $template);
    
    // 常にPDF Bookletウィジェットを表示（テンプレートに関係なく）
    error_log('Adding PDF Booklet widget for page ' . $post->ID);
    
    $pdf_file = wp_upload_dir()['basedir'] . '/pdf-booklet/booklet-' . $post->ID . '.pdf';
    $pdf_url = wp_upload_dir()['baseurl'] . '/pdf-booklet/booklet-' . $post->ID . '.pdf';
    $pdf_exists = file_exists($pdf_file);
    
    // 日本時間でのタイムスタンプを取得
    $pdf_date_jst = '';
    if ($pdf_exists) {
        $pdf_timestamp = filemtime($pdf_file);
        $pdf_date_jst = get_jst_timestamp($pdf_timestamp);
    }
    
    // ページの最終更新日時も日本時間で表示
    $page_modified_jst = get_jst_timestamp(strtotime($post->post_modified));
    
    ?>
    <div class="pdf-booklet-meta" style="background: #f9f9f9; border: 1px solid #ddd; padding: 15px; margin: 20px 0; border-radius: 4px;">
        <h3 style="margin-top: 0;">📖 PDFブックレット</h3>
        
        <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
            <div style="flex: 1;">
                <?php if ($pdf_exists): ?>
                    <span class="dashicons dashicons-yes-alt" style="color: green;"></span>
                    <strong>PDF生成済み</strong>
                    <br><small>PDF更新日時: <?php echo esc_html($pdf_date_jst); ?> (JST)</small>
                <?php else: ?>
                    <span class="dashicons dashicons-warning" style="color: orange;"></span>
                    <strong>PDF未生成</strong>
                <?php endif; ?>
            </div>
            
            <div>
                <button type="button" class="button button-primary generate-pdf-single" data-page-id="<?php echo $post->ID; ?>">
                    PDF生成
                </button>
                
                <?php if ($pdf_exists): ?>
                <a href="<?php echo esc_url($pdf_url); ?>" target="_blank" class="button" style="margin-left: 5px;">
                    PDF表示
                </a>
                <button type="button" class="button delete-pdf-single" data-page-id="<?php echo $post->ID; ?>" style="margin-left: 5px;">
                    PDF削除
                </button>
                <?php endif; ?>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; font-size: 12px; color: #666; border-top: 1px solid #ddd; padding-top: 10px;">
            <div>
                <strong>使用テンプレート:</strong><br>
                <?php echo esc_html(pdf_booklet_get_supported_templates()[$template] ?? $template); ?>
            </div>
            <div>
                <strong>ページ最終更新:</strong><br>
                <?php echo esc_html($page_modified_jst); ?> (JST)
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // PDF生成ボタン（単体）
        $('.generate-pdf-single').on('click', function() {
            var pageId = $(this).data('page-id');
            var button = $(this);
            
            if (!confirm('PDFを生成しますか？完了まで数分かかる場合があります。')) {
                return;
            }
            
            button.prop('disabled', true).text('生成中...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'generate_pdf_single',
                    page_id: pageId,
                    nonce: '<?php echo wp_create_nonce('pdf_booklet_single'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert('PDF生成を開始しました。完了まで数分かかる場合があります。');
                        // 5秒後にページをリロード（PDF状態を更新）
                        setTimeout(function() {
                            location.reload();
                        }, 5000);
                    } else {
                        alert('エラー: ' + response.data);
                    }
                },
                error: function() {
                    alert('通信エラーが発生しました。');
                },
                complete: function() {
                    button.prop('disabled', false).text('PDF生成');
                }
            });
        });
        
        // PDF削除ボタン（単体）
        $('.delete-pdf-single').on('click', function() {
            if (!confirm('PDFファイルを削除しますか？')) {
                return;
            }
            
            var pageId = $(this).data('page-id');
            var button = $(this);
            
            button.prop('disabled', true).text('削除中...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'delete_pdf_single',
                    page_id: pageId,
                    nonce: '<?php echo wp_create_nonce('pdf_booklet_single'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert('PDFファイルを削除しました。');
                        location.reload();
                    } else {
                        alert('エラー: ' + response.data);
                    }
                },
                error: function() {
                    alert('通信エラーが発生しました。');
                },
                complete: function() {
                    button.prop('disabled', false).text('PDF削除');
                }
            });
        });
    });
    </script>
    <?php
});

// AJAX: 単体PDF生成
add_action('wp_ajax_generate_pdf_single', function() {
    check_ajax_referer('pdf_booklet_single', 'nonce');
    
    $page_id = intval($_POST['page_id']);
    if (!$page_id) {
        wp_send_json_error('無効なページIDです。');
    }
    
    // GitHub Actions APIを呼び出し
    $result = trigger_github_actions_for_page($page_id);
    
    if ($result['success']) {
        wp_send_json_success('PDF生成を開始しました。');
    } else {
        wp_send_json_error($result['message']);
    }
});

// AJAX: 単体PDF削除
add_action('wp_ajax_delete_pdf_single', function() {
    check_ajax_referer('pdf_booklet_single', 'nonce');
    
    $page_id = intval($_POST['page_id']);
    if (!$page_id) {
        wp_send_json_error('無効なページIDです。');
    }
    
    $pdf_file = wp_upload_dir()['basedir'] . '/pdf-booklet/booklet-' . $page_id . '.pdf';
    
    if (file_exists($pdf_file) && unlink($pdf_file)) {
        wp_send_json_success('PDFファイルを削除しました。');
    } else {
        wp_send_json_error('PDFファイルの削除に失敗しました。');
    }
});






// GitHub Actions API呼び出し関数
function trigger_github_actions_for_page($page_id) {
    $token = get_option('github_actions_token');
    $repo = get_option('github_repo');
    $wf_id = get_option('github_workflow_id');
    
    if (!$token || !$repo || !$wf_id) {
        return [
            'success' => false,
            'message' => 'GitHub設定が不完全です。設定ページで確認してください。'
        ];
    }
    
    $url = "https://api.github.com/repos/{$repo}/actions/workflows/{$wf_id}/dispatches";
    
    $data = [
        'ref' => 'main',
        'inputs' => [
            'wp_post_ids' => (string)$page_id,
            'target_slug' => '',
            'template_type' => '',
            'concurrency' => '2',
            'skip_schema' => '0',
            'allow_dummy' => '0'
        ]
    ];
    
    $response = wp_remote_post($url, [
        'headers' => [
            'Authorization' => 'token ' . $token,
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => 'WordPress-PDF-Booklet'
        ],
        'body' => json_encode($data),
        'timeout' => 30
    ]);
    
    if (is_wp_error($response)) {
        return [
            'success' => false,
            'message' => 'GitHub APIへの接続に失敗しました: ' . $response->get_error_message()
        ];
    }
    
    $status_code = wp_remote_retrieve_response_code($response);
    
    if ($status_code === 204) {
        return [
            'success' => true,
            'message' => 'PDF生成を開始しました。'
        ];
    } else {
        $body = wp_remote_retrieve_body($response);
        $error_data = json_decode($body, true);
        
        return [
            'success' => false,
            'message' => 'GitHub API エラー (HTTP ' . $status_code . '): ' . ($error_data['message'] ?? 'Unknown error')
        ];
    }
}

// ========================================
// 固定ページ一覧のカスタマイズ
// ========================================

// 固定ページ一覧に「該当ページ数」カラムを追加
add_filter('manage_pages_columns', function($columns) {
    // 「日付」カラムの前に新しいカラムを挿入
    $new_columns = [];
    foreach ($columns as $key => $value) {
        if ($key === 'date') {
            $new_columns['pdf_page_number'] = 'ページ数';
        }
        $new_columns[$key] = $value;
    }
    return $new_columns;
});

// カラムの内容を表示
add_action('manage_pages_custom_column', function($column, $post_id) {
    if ($column === 'pdf_page_number') {
        $page_number = get_field('pdf_page_number', $post_id);
        if ($page_number) {
            echo '<span style="font-weight: bold; color: #0073aa;">' . esc_html($page_number) . 'ページ</span>';
        } else {
            echo '—';
        }
    }
}, 10, 2);

// カラムをソート可能にする
add_filter('manage_edit-page_sortable_columns', function($columns) {
    $columns['pdf_page_number'] = 'pdf_page_number';
    return $columns;
});


// ACFフィールドを使用したソート処理
add_action('pre_get_posts', function($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }
    
    $orderby = $query->get('orderby');
    
    // ページ数でのソート
    if ($orderby === 'pdf_page_number') {
        $query->set('meta_key', 'pdf_page_number');
        $query->set('orderby', 'meta_value_num');
    }
    
    // 固定ページ一覧でのデフォルトソート（ページ数昇順）
    if ($query->get('post_type') === 'page' && !$query->get('orderby')) {
        $query->set('meta_key', 'pdf_page_number');
        $query->set('orderby', 'meta_value_num');
        $query->set('order', 'ASC');
        $query->set('meta_query', [
            'relation' => 'OR',
            [
                'key' => 'pdf_page_number',
                'compare' => 'EXISTS'
            ],
            [
                'key' => 'pdf_page_number',
                'compare' => 'NOT EXISTS'
            ]
        ]);
    }
});

// 固定ページ一覧にPDFステータス表示を追加
add_filter('manage_pages_columns', function($columns) {
    $new_columns = [];
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'title') {
            $new_columns['pdf_status'] = 'PDF状態';
        }
    }
    return $new_columns;
});

add_action('manage_pages_custom_column', function($column, $post_id) {
    if ($column === 'pdf_status') {
        $template = get_page_template_slug($post_id);
        
        // PDFブックレットテンプレートかチェック
        $pdf_templates = array_keys(pdf_booklet_get_supported_templates());
        
        if (in_array($template, $pdf_templates)) {
            // PDF生成状況をチェック（簡易版）
            $pdf_url = 'https://kazumanishiwaki.net/ks/wp-content/uploads/pdf-booklet/booklet-' . $post_id . '.pdf';
            $response = wp_remote_head($pdf_url, ['timeout' => 5]);
            
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $modified = get_the_modified_time('Y-m-d H:i:s', $post_id);
                echo '<span style="color: #46b450; font-weight: bold;">✓ PDF生成済</span><br>';
                echo '<small style="color: #666;">更新: ' . esc_html($modified) . '</small>';
            } else {
                echo '<span style="color: #dc3232;">⚠ PDF未生成</span>';
            }
        } else {
            echo '<span style="color: #999;">—</span>';
        }
    }
}, 10, 2);

?>
