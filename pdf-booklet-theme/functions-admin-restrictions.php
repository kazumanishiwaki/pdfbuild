<?php
/**
 * functions.phpに追加するコード
 * WordPress管理画面メニュー制限機能
 */

// 管理画面メニュー制限ファイルを読み込み
require_once get_template_directory() . '/admin-menu-restrictions.php';

// または、直接functions.phpに以下のコードを追加してください：

/*
// 管理画面メニューの制限
add_action('admin_menu', function() {
    // 管理者（administrator）以外のユーザーに対してメニューを制限
    if (!current_user_can('administrator')) {
        
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
        
        // ユーザーメニューを削除
        remove_menu_page('users.php');
    }
}, 999);

// メニューラベルのカスタマイズ
add_action('admin_menu', function() {
    global $menu;
    
    // 固定ページのラベルを「ページ」に変更
    foreach ($menu as $key => $item) {
        if ($item[2] == 'edit.php?post_type=page') {
            $menu[$key][0] = 'ページ';
            break;
        }
    }
    
    // 管理者以外の場合、メディアライブラリのラベルを「画像」に変更
    if (!current_user_can('administrator')) {
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
    
    if (!current_user_can('administrator')) {
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
*/
