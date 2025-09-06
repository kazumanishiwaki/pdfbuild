<?php
/**
 * functions.php – Page‑specific PDF generation & button
 */

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
?>
