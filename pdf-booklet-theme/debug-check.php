<?php
/**
 * デバッグ用チェックファイル
 * このファイルをWordPressのルートディレクトリに配置して実行
 */

// WordPressを読み込み
require_once('wp-config.php');
require_once('wp-load.php');

echo "<h1>PDF Booklet デバッグ情報</h1>";

// 1. テーマ情報
echo "<h2>1. テーマ情報</h2>";
$current_theme = wp_get_theme();
echo "<ul>";
echo "<li>テーマ名: " . $current_theme->get('Name') . "</li>";
echo "<li>テーマディレクトリ: " . get_template_directory() . "</li>";
echo "<li>functions.php存在: " . (file_exists(get_template_directory() . '/functions.php') ? 'Yes' : 'No') . "</li>";
echo "</ul>";

// 2. PDF Booklet関数の存在確認
echo "<h2>2. 関数の存在確認</h2>";
echo "<ul>";
echo "<li>pdf_booklet_get_supported_templates: " . (function_exists('pdf_booklet_get_supported_templates') ? 'Yes' : 'No') . "</li>";
echo "<li>is_pdf_booklet_template: " . (function_exists('is_pdf_booklet_template') ? 'Yes' : 'No') . "</li>";
echo "<li>get_jst_timestamp: " . (function_exists('get_jst_timestamp') ? 'Yes' : 'No') . "</li>";
echo "</ul>";

// 3. フック登録の確認
echo "<h2>3. フック登録確認</h2>";
global $wp_filter;
echo "<ul>";
echo "<li>admin_head-post.php: " . (isset($wp_filter['admin_head-post.php']) ? count($wp_filter['admin_head-post.php']) . ' callbacks' : 'No callbacks') . "</li>";
echo "<li>edit_form_after_title: " . (isset($wp_filter['edit_form_after_title']) ? count($wp_filter['edit_form_after_title']) . ' callbacks' : 'No callbacks') . "</li>";
echo "<li>admin_notices: " . (isset($wp_filter['admin_notices']) ? count($wp_filter['admin_notices']) . ' callbacks' : 'No callbacks') . "</li>";
echo "</ul>";

// 4. テンプレート確認
echo "<h2>4. テンプレート確認</h2>";
if (function_exists('pdf_booklet_get_supported_templates')) {
    $templates = pdf_booklet_get_supported_templates();
    echo "<ul>";
    foreach ($templates as $file => $name) {
        $template_path = get_template_directory() . '/' . $file;
        echo "<li>$name ($file): " . (file_exists($template_path) ? 'Exists' : 'Missing') . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color: red;'>pdf_booklet_get_supported_templates 関数が見つかりません</p>";
}

// 5. 特定ページの情報（ID: 125の場合）
echo "<h2>5. ページ情報 (ID: 125)</h2>";
$page = get_post(125);
if ($page) {
    $template = get_page_template_slug(125);
    echo "<ul>";
    echo "<li>ページタイトル: " . $page->post_title . "</li>";
    echo "<li>ページタイプ: " . $page->post_type . "</li>";
    echo "<li>テンプレート: " . ($template ?: 'default') . "</li>";
    if (function_exists('is_pdf_booklet_template')) {
        echo "<li>PDF Bookletテンプレート: " . (is_pdf_booklet_template($template) ? 'Yes' : 'No') . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>ページID: 125 が見つかりません</p>";
}

// 6. エラーログの最新エントリ
echo "<h2>6. 最新のエラーログ</h2>";
$error_log = ini_get('error_log');
if ($error_log && file_exists($error_log)) {
    $lines = file($error_log);
    $recent_lines = array_slice($lines, -10);
    echo "<pre style='background: #f0f0f0; padding: 10px; overflow-x: auto;'>";
    foreach ($recent_lines as $line) {
        if (strpos($line, 'PDF Booklet') !== false) {
            echo "<strong style='color: blue;'>" . htmlspecialchars($line) . "</strong>";
        } else {
            echo htmlspecialchars($line);
        }
    }
    echo "</pre>";
} else {
    echo "<p>エラーログファイルが見つかりません</p>";
}

echo "<hr>";
echo "<p><strong>このファイルは確認後に削除してください。</strong></p>";
?>

