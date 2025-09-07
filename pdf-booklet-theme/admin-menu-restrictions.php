<?php
/**
 * WordPress管理画面メニュー制限
 * 管理人以外のユーザーのメニューアクセスを制限
 */

// 管理画面メニューの制限
add_action('admin_menu', function() {
    // 管理者（administrator）以外のユーザーに対してメニューを制限
    if (!current_user_can('manage_options')) {
        
        // 投稿メニューを削除
        remove_menu_page('edit.php');
        
        // コメントメニューを削除
        remove_menu_page('edit-comments.php');
        
        // 外観メニューを削除
        remove_menu_page('themes.php');
        
        // プラグインメニューを削除
        remove_menu_page('plugins.php');
        
        // ツールメニューを削除
        remove_menu_page('tools.php');
        
        // 設定メニューを削除
        remove_menu_page('options-general.php');
        
        // ACFメニューを削除（Advanced Custom Fields）
        remove_menu_page('edit.php?post_type=acf-field-group');
        
        // ユーザーメニューを削除（必要に応じて）
        remove_menu_page('users.php');
        
        // メディアライブラリは残す（画像アップロードに必要）
        // remove_menu_page('upload.php');
    }
}, 999); // 優先度を高くして他のプラグインの後に実行

// サブメニューの制限
add_action('admin_menu', function() {
    if (!current_user_can('manage_options')) {
        
        // 固定ページのサブメニューを制限
        // 「新規追加」は残して「固定ページ一覧」のみアクセス可能にする
        // remove_submenu_page('edit.php?post_type=page', 'post-new.php?post_type=page');
        
        // プロフィール以外のユーザー関連メニューを削除
        if (menu_page_url('users.php', false)) {
            remove_submenu_page('users.php', 'users.php');
            remove_submenu_page('users.php', 'user-new.php');
            // プロフィールは残す
            // remove_submenu_page('users.php', 'profile.php');
        }
    }
}, 999);

// メニューラベルのカスタマイズ
add_action('admin_menu', function() {
    global $menu, $submenu;
    
    // 固定ページのラベルを「ページ」に変更
    foreach ($menu as $key => $item) {
        if ($item[2] == 'edit.php?post_type=page') {
            $menu[$key][0] = 'ページ';
            break;
        }
    }
    
    // 管理者以外の場合、メディアライブラリのラベルを「画像」に変更
    if (!current_user_can('manage_options')) {
        foreach ($menu as $key => $item) {
            if ($item[2] == 'upload.php') {
                $menu[$key][0] = '画像';
                break;
            }
        }
    }
}, 999);

// 管理バーの制限
add_action('wp_before_admin_bar_render', function() {
    global $wp_admin_bar;
    
    if (!current_user_can('manage_options')) {
        // 新規投稿リンクを削除
        $wp_admin_bar->remove_menu('new-post');
        
        // コメントリンクを削除
        $wp_admin_bar->remove_menu('comments');
        
        // WordPress.orgへのリンクを削除
        $wp_admin_bar->remove_menu('wp-logo');
        
        // 更新通知を削除
        $wp_admin_bar->remove_menu('updates');
        
        // カスタマイザーリンクを削除
        $wp_admin_bar->remove_menu('customize');
    }
});

// ダッシュボードウィジェットの制限
add_action('wp_dashboard_setup', function() {
    if (!current_user_can('manage_options')) {
        
        // WordPressニュースウィジェットを削除
        remove_meta_box('dashboard_primary', 'dashboard', 'side');
        
        // クイックドラフトウィジェットを削除
        remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
        
        // 最近のコメントウィジェットを削除
        remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
        
        // 最近の投稿ウィジェットを削除
        remove_meta_box('dashboard_recent_drafts', 'dashboard', 'side');
        
        // WordPressイベントとニュースウィジェットを削除
        remove_meta_box('dashboard_primary', 'dashboard', 'side');
        remove_meta_box('dashboard_secondary', 'dashboard', 'side');
        
        // アクティビティウィジェットを削除
        remove_meta_box('dashboard_activity', 'dashboard', 'normal');
    }
});

// カスタムダッシュボードウィジェットを追加
add_action('wp_dashboard_setup', function() {
    if (!current_user_can('manage_options')) {
        
        // PDFブックレット専用ダッシュボードウィジェットを追加
        wp_add_dashboard_widget(
            'pdf_booklet_dashboard',
            '📖 PDFブックレット',
            'pdf_booklet_dashboard_widget'
        );
        
        // 使い方ガイドウィジェットを追加
        wp_add_dashboard_widget(
            'pdf_usage_guide',
            '📋 使い方ガイド',
            'pdf_usage_guide_widget'
        );
    }
});

// PDFブックレットダッシュボードウィジェット
function pdf_booklet_dashboard_widget() {
    // PDF対応ページの統計を取得
    $pdf_pages = get_pages([
        'meta_key' => '_wp_page_template',
        'meta_value' => array_keys(pdf_booklet_get_supported_templates()),
        'meta_compare' => 'IN'
    ]);
    
    $total_pages = count($pdf_pages);
    $pdf_dir = wp_upload_dir()['basedir'] . '/pdf-booklet/';
    $generated_count = 0;
    
    foreach ($pdf_pages as $page) {
        $pdf_file = $pdf_dir . 'booklet-' . $page->ID . '.pdf';
        if (file_exists($pdf_file)) {
            $generated_count++;
        }
    }
    
    ?>
    <div class="pdf-dashboard-widget">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
            <div style="text-align: center; padding: 10px; background: #f0f6fc; border-radius: 4px;">
                <div style="font-size: 24px; font-weight: bold; color: #2271b1;"><?php echo $total_pages; ?></div>
                <div style="font-size: 12px; color: #646970;">総ページ数</div>
            </div>
            <div style="text-align: center; padding: 10px; background: #f0f8f0; border-radius: 4px;">
                <div style="font-size: 24px; font-weight: bold; color: #00a32a;"><?php echo $generated_count; ?></div>
                <div style="font-size: 12px; color: #646970;">PDF生成済み</div>
            </div>
        </div>
        
        <div style="text-align: center;">
            <a href="<?php echo admin_url('edit.php?post_type=page'); ?>" class="button button-primary">
                ページ管理
            </a>
            <a href="<?php echo admin_url('post-new.php?post_type=page'); ?>" class="button button-secondary" style="margin-left: 10px;">
                新規ページ作成
            </a>
        </div>
        
        <?php if ($total_pages - $generated_count > 0): ?>
        <div style="margin-top: 15px; padding: 10px; background: #fff8e1; border-left: 4px solid #ffb900; border-radius: 4px;">
            <strong>⚠️ 未生成のPDFがあります</strong><br>
            <small><?php echo $total_pages - $generated_count; ?>ページのPDFが未生成です。</small>
        </div>
        <?php endif; ?>
    </div>
    <?php
}

// 使い方ガイドウィジェット
function pdf_usage_guide_widget() {
    ?>
    <div class="pdf-usage-guide">
        <h4 style="margin-top: 0;">📝 ページの作成手順</h4>
        <ol style="margin-left: 20px;">
            <li><strong>新規ページを作成</strong><br>
                <small>左メニューの「ページ」→「新規追加」をクリック</small>
            </li>
            <li><strong>テンプレートを選択</strong><br>
                <small>ページ属性で「テキスト+写真2枚形式」を選択</small>
            </li>
            <li><strong>ACFフィールドを入力</strong><br>
                <small>PDFタイトル、本文、写真、キャプションを入力</small>
            </li>
            <li><strong>ページを公開</strong><br>
                <small>「公開」ボタンをクリックしてページを保存</small>
            </li>
            <li><strong>PDFを生成</strong><br>
                <small>編集画面の「PDF生成」ボタンをクリック</small>
            </li>
        </ol>
        
        <h4>💡 ヒント</h4>
        <ul style="margin-left: 20px;">
            <li>画像は「画像」メニューからアップロードできます</li>
            <li>PDFの生成には数分かかる場合があります</li>
            <li>固定ページの本文は使用されません（ACFフィールドのみ）</li>
        </ul>
    </div>
    <?php
}

// 管理画面のフッターテキストをカスタマイズ
add_filter('admin_footer_text', function($text) {
    if (!current_user_can('manage_options')) {
        return 'PDFブックレット管理システム';
    }
    return $text;
});

// 管理画面のタイトルをカスタマイズ
add_filter('admin_title', function($admin_title, $title) {
    if (!current_user_can('manage_options')) {
        return $title . ' - PDFブックレット管理';
    }
    return $admin_title;
}, 10, 2);

// 不要な管理画面通知を非表示
add_action('admin_head', function() {
    if (!current_user_can('manage_options')) {
        ?>
        <style>
        /* 更新通知を非表示 */
        .update-nag,
        .updated,
        .error,
        .notice {
            display: none !important;
        }
        
        /* PDF関連の通知のみ表示 */
        .notice.pdf-booklet-notice {
            display: block !important;
        }
        
        /* 管理画面のスタイル調整 */
        #wpadminbar .ab-top-menu > li.menupop:hover > .ab-item,
        #wpadminbar .ab-top-menu > li:hover > .ab-item {
            background: #32373c;
        }
        
        /* ダッシュボードウィジェットのスタイル */
        .pdf-dashboard-widget,
        .pdf-usage-guide {
            font-size: 13px;
            line-height: 1.5;
        }
        
        .pdf-dashboard-widget .button {
            font-size: 12px;
            padding: 4px 8px;
            height: auto;
        }
        </style>
        <?php
    }
});

// ページ編集画面でのACFフィールドの説明を強化
add_action('acf/render_field_settings/type=text', function($field) {
    if ($field['name'] === 'title') {
        acf_render_field_setting($field, [
            'label' => '説明',
            'instructions' => 'このフィールドはPDFのタイトルとして使用されます。空の場合はページタイトルが使用されます。',
            'type' => 'message',
        ]);
    }
});

// 権限チェック関数
function is_pdf_editor() {
    return current_user_can('edit_pages') && !current_user_can('manage_options');
}

// PDF編集者用の権限設定
add_action('init', function() {
    // PDF編集者用のカスタム権限を追加
    $role = get_role('editor');
    if ($role) {
        $role->add_cap('edit_pdf_booklets');
        $role->add_cap('publish_pdf_booklets');
    }
    
    // 寄稿者レベルのユーザーにもページ編集権限を付与（必要に応じて）
    $contributor = get_role('contributor');
    if ($contributor) {
        $contributor->add_cap('edit_pages');
        $contributor->add_cap('edit_published_pages');
        $contributor->add_cap('publish_pages');
    }
});

// ページ一覧画面のカスタマイズ
add_filter('manage_pages_columns', function($columns) {
    if (!current_user_can('manage_options')) {
        // PDF状態列を追加
        $columns['pdf_status'] = 'PDF状態';
    }
    return $columns;
});

add_action('manage_pages_custom_column', function($column, $post_id) {
    if ($column === 'pdf_status' && !current_user_can('administrator')) {
        $template = get_page_template_slug($post_id);
        
        if (is_pdf_booklet_template($template)) {
            $pdf_file = wp_upload_dir()['basedir'] . '/pdf-booklet/booklet-' . $post_id . '.pdf';
            $pdf_url = wp_upload_dir()['baseurl'] . '/pdf-booklet/booklet-' . $post_id . '.pdf';
            
            if (file_exists($pdf_file)) {
                echo '<span class="dashicons dashicons-yes-alt" style="color: green;"></span> ';
                echo '<a href="' . esc_url($pdf_url) . '" target="_blank">PDF表示</a>';
            } else {
                echo '<span class="dashicons dashicons-warning" style="color: orange;"></span> 未生成';
            }
        } else {
            echo '<span style="color: #666;">対象外</span>';
        }
    }
}, 10, 2);

// ページ編集画面での不要な要素を非表示
add_action('admin_head-post.php', function() {
    global $post;
    
    if (!current_user_can('administrator') && $post && $post->post_type === 'page') {
        ?>
        <style>
        /* 固定ページの本文エディタを非表示（ACFフィールドのみ使用） */
        #postdivrich {
            display: none;
        }
        
        /* ページ属性のテンプレート選択以外を非表示 */
        #pageparentdiv .inside > p:not(.page-template) {
            display: none;
        }
        
        /* 不要なメタボックスを非表示 */
        #commentstatusdiv,
        #slugdiv,
        #authordiv {
            display: none;
        }
        
        /* ACFフィールドを強調 */
        .acf-field-group {
            border: 2px solid #2271b1;
            border-radius: 4px;
            background: #f0f6fc;
        }
        
        .acf-field-group .acf-field-group-title {
            background: #2271b1;
            color: white;
            padding: 10px 15px;
            margin: -1px -1px 15px -1px;
            font-weight: bold;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // ページタイトルの下に説明を追加
            $('#title').after('<p style="margin: 10px 0; color: #666; font-size: 13px;">💡 このページタイトルはPDFには表示されません。PDFタイトルは下記のACFフィールドで設定してください。</p>');
            
            // 固定ページ本文エディタの代わりに説明を表示
            $('#postdivrich').after('<div style="background: #fff8e1; border: 1px solid #ffb900; border-radius: 4px; padding: 15px; margin: 20px 0;"><h3 style="margin-top: 0;">📝 コンテンツの入力について</h3><p>このページでは<strong>固定ページの本文は使用されません</strong>。</p><p>PDFに表示するコンテンツは、下記の「PDFブックレット設定」フィールドで入力してください。</p></div>');
        });
        </script>
        <?php
    }
});

// 新規ページ作成時のデフォルトテンプレート設定
add_action('admin_head-post-new.php', function() {
    global $typenow;
    
    if (!current_user_can('administrator') && $typenow === 'page') {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // デフォルトでPDFブックレットテンプレートを選択
            $('#page_template').val('template-text-photo2.php').trigger('change');
            
            // 説明を追加
            $('#page_template').after('<p style="margin: 10px 0; color: #666; font-size: 13px;">💡 PDFブックレット用のテンプレートが自動選択されています。</p>');
        });
        </script>
        <?php
    }
});
