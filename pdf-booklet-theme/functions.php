<?php
/**
 * functions.php – Page‑specific PDF generation & button
 */

// デバッグ用: functions.phpが読み込まれているかを確認
error_log('PDF Booklet functions.php loaded at ' . date('Y-m-d H:i:s'));

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
        'template-text-photo2.php'   => 'テキスト+写真2枚形式'
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

// ページ編集画面にPDFボックスを追加
add_action('add_meta_boxes', function(){
    global $post;
    if(!$post) return;
    
    $template = get_page_template_slug($post->ID);
    if(!is_pdf_booklet_template($template)) return;
    
    add_meta_box('pdf_box','PDF Booklet','render_pdf_box','page','side','high');
});

function render_pdf_box(){
    global $post;
    $slug = $post->post_name;
    $u = wp_upload_dir();
    
    // アップロードディレクトリの作成を確認
    $pdf_dir = $u['basedir'].'/pdf-booklet/';
    if (!file_exists($pdf_dir)) {
        wp_mkdir_p($pdf_dir);
    }
    
    // 従来のスラッグベースのパス
    $pdf_path = $pdf_dir.$slug.'.pdf';
    $pdf_url = $u['baseurl'].'/pdf-booklet/'.$slug.'.pdf';
    $pdf_exists = file_exists($pdf_path);
    
    // ID基準のPDF（新形式）
    $new_pdf_path = $pdf_dir.'booklet-'.$post->ID.'.pdf';
    $new_pdf_url = $u['baseurl'].'/pdf-booklet/booklet-'.$post->ID.'.pdf';
    $new_pdf_exists = file_exists($new_pdf_path);
    
    // どちらかが存在すればOK
    $any_pdf_exists = $pdf_exists || $new_pdf_exists;
    
    // 表示するPDFのURLとパス
    $display_pdf_url = $pdf_exists ? $pdf_url : $new_pdf_url;
    $display_pdf_path = $pdf_exists ? $pdf_path : $new_pdf_path;
    
    $nonce = wp_create_nonce('dispatch_pdf_nonce');
    
    // 追加ページIDの取得
    $additional_ids = get_option('additional_page_ids', '');
    
    // GitHub設定の確認
    $token = get_option('github_actions_token');
    $repo  = get_option('github_repo');
    $wf_id = get_option('github_workflow_id');
    $github_config_ok = ($token && $repo && $wf_id);
    ?>
    <div id="pdf-ui">
        <button type="button" class="button button-primary" id="pdf-gen-btn">PDF再生成</button>
        <?php if($any_pdf_exists): ?>
            <p style="margin-top:.5rem"><a href="<?php echo esc_url($display_pdf_url);?>" target="_blank">最新PDF</a></p>
            <p style="margin-top:.5rem">最終更新: <?php echo date('Y-m-d H:i:s', filemtime($display_pdf_path)); ?></p>
        <?php else: ?>
            <p style="margin-top:.5rem">PDFはまだ生成されていないか、アップロードされていません。</p>
            <p style="margin-top:.5rem">検索パス:</p>
            <ul style="margin-top:.3rem">
                <li><code><?php echo esc_html($pdf_path); ?></code></li>
                <li><code><?php echo esc_html($new_pdf_path); ?></code></li>
            </ul>
        <?php endif;?>
        
        <?php if($additional_ids): ?>
            <div style="margin-top:.5rem; padding:5px; background:#f0f0f0; border:1px solid #ddd;">
                <p>追加ページID: <strong><?php echo esc_html($additional_ids); ?></strong></p>
            </div>
        <?php endif; ?>
        
        <p id="pdf-status" style="margin-top:.5rem"></p>
        <?php if (!$github_config_ok): ?>
            <p style="color:red;margin-top:.5rem">GitHub設定が不完全です。<a href="<?php echo admin_url('options-general.php?page=pdf-booklet-settings'); ?>">設定ページ</a>でtoken/repo/workflow_idを設定してください。</p>
        <?php endif; ?>
        
        <div style="margin-top:1rem; border-top:1px solid #ddd; padding-top:.5rem;">
            <a href="<?php echo admin_url('admin.php?page=pdf-booklet-manager'); ?>">PDFファイル管理画面を開く</a>
        </div>
    </div>
    <script>
    jQuery(function($){
        $('#pdf-gen-btn').on('click',function(){
            $('#pdf-status').text('GitHubへリクエスト送信中…');
            $.post(ajaxurl,{
                action:'dispatch_page_pdf',
                _wpnonce:'<?php echo $nonce;?>',
                post_id:<?php echo intval($post->ID); ?>
            },function(res){
                if(res.success){
                    $('#pdf-status').html('<span style="color:green">✓ ワークフローを起動しました。数分後にリロードしてください</span>');
                }else{
                    $('#pdf-status').html('<span style="color:red">✗ エラー: ' + (res.data || '不明なエラー') + '</span>');
                }
            }).fail(function(xhr, status, error) {
                $('#pdf-status').html('<span style="color:red">✗ Ajaxリクエスト失敗: ' + status + ' - ' + error + '</span>');
            });
        });
    });
    </script>
<?php
}

add_action('wp_ajax_dispatch_page_pdf', function(){
    check_ajax_referer('dispatch_pdf_nonce');
    $pid = intval($_POST['post_id']??0);
    if(!$pid) wp_send_json_error('no post');
    $slug = get_post_field('post_name',$pid);
    $title = get_the_title($pid);

    // 追加のページIDを取得
    $additional_ids = get_option('additional_page_ids', '');
    
    // 現在のページIDを含めたすべてのページID
    $all_page_ids = $pid;
    if (!empty($additional_ids)) {
        $all_page_ids = $pid . ',' . $additional_ids;
    }

    $token = get_option('github_actions_token');
    $repo  = get_option('github_repo');
    $wf_id = get_option('github_workflow_id');
    if(!$token||!$repo||!$wf_id) wp_send_json_error('GitHub設定未完: token='.($token?'有':'無').', repo='.($repo?'有':'無').', workflow_id='.($wf_id?'有':'無'));

    // GitHub Actionsワークフローの起動パラメータ
    $body = json_encode([
        'ref' => 'main',
        'inputs' => [
            'wp_post_ids' => (string)$all_page_ids,
            'target_slug' => (string)$pid
        ]
    ]);
    
    $resp = wp_remote_post(
        "https://api.github.com/repos/{$repo}/actions/workflows/{$wf_id}/dispatches",
        ['headers'=>['Authorization'=>'Bearer '.$token,'Accept'=>'application/vnd.github.v3+json','Content-Type'=>'application/json'],'body'=>$body]
    );
    
    if(is_wp_error($resp)) {
        wp_send_json_error('GitHub API呼び出し失敗: '.$resp->get_error_message());
    }
    
    $status_code = wp_remote_retrieve_response_code($resp);
    if ($status_code < 200 || $status_code >= 300) {
        $body = wp_remote_retrieve_body($resp);
        wp_send_json_error('GitHub API応答エラー: ステータスコード='.$status_code.', レスポンス='.$body);
    }
    
    wp_send_json_success();
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
                
                // PDF Bookletテンプレートかどうかを判定（複数の条件で判定）
                var isPdfBookletTemplate = template === 'template-text-photo2.php' || 
                                         template === 'PDF Booklet Text Photo2' ||
                                         template === 'PDF Booklet:テキスト+写真２枚形式' ||
                                         (templateElement && templateElement.find('option:selected').text().indexOf('PDF Booklet') !== -1) ||
                                         (templateElement && templateElement.find('option:selected').text().indexOf('テキスト+写真') !== -1);
                
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
            
            // 初期状態をチェック
            setTimeout(function() {
                console.log('Initial template check...');
                handleTemplateChange();
            }, 500);
            
            // テンプレート変更イベントを監視（複数のセレクターに対応）
            $(document).on('change', '#page_template, select[name="page_template"], select[id*="template"]', function() {
                console.log('Template change event triggered');
                handleTemplateChange();
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

// ページ編集画面にPDF Bookletウィジェットを追加（日本時間対応）
add_action('edit_form_after_title', function($post) {
    error_log('edit_form_after_title hook triggered for post ID: ' . $post->ID);
    
    if ($post->post_type !== 'page') {
        error_log('Not a page, skipping PDF widget');
        return;
    }
    
    $template = get_page_template_slug($post->ID);
    error_log('Template for page ' . $post->ID . ': ' . $template);
    
    if (!is_pdf_booklet_template($template)) {
        error_log('Not a PDF Booklet template, skipping widget');
        return;
    }
    
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

?>
