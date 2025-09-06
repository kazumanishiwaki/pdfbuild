<?php
/* Template Name: PDF Booklet Text Photo2 */

get_header(); ?>

<main id="primary" class="site-main">
  <h1><?php the_title(); ?></h1>
  <p>このページは PDF Booklet 生成用のプレースホルダーです。</p>
  
  <?php
    // ACF初期化状況の確認
    $acf_initialized = false;
    if (function_exists('acf') && is_object(acf())) {
        $acf_initialized = true;
    }
    
    // ACF fields 取得（get_fields()は動作しているため直接使用）
    $acf = null;
    if (function_exists('get_fields')) {
        $acf = get_fields();
    }
    
    if ($acf) {
        echo '<div style="background:#f5f5f5;padding:1em;border:1px solid #ddd;margin-bottom:20px;">';
        echo '<h3>ACFフィールド確認</h3>';
        echo '<pre>';
        print_r($acf);
        echo '</pre>';
        echo '</div>';
    } else {
        echo '<div style="background:#ffe6e6;padding:1em;border:1px solid #f99;margin-bottom:20px;">';
        echo '<p><strong>ACF フィールドがまだ設定されていないか、正しく保存されていません。</strong></p>';
        echo '<p>可能性のある問題:</p>';
        echo '<ul>';
        echo '<li>フィールドグループが正しく設定されていない</li>';
        echo '<li>このテンプレートとフィールドの関連付けが機能していない</li>';
        echo '<li>保存処理に問題がある</li>';
        echo '</ul>';
        echo '</div>';
    }
  ?>
  
  <?php 
  // プレビュー表示 - より単純な方法でACFフィールドを表示
  if ($acf && is_array($acf) && !empty($acf)) {
      echo '<div style="background:#e9f7f9;padding:1.5em;border:1px solid #add8e6;margin-bottom:20px;border-radius:5px;">';
      echo '<h3 style="border-bottom:2px solid #78c2d2;padding-bottom:10px;margin-top:0;">コンテンツプレビュー</h3>';
      
      echo '<div style="display:grid;grid-template-columns:1fr 2fr;gap:15px;">';
      foreach ($acf as $field_name => $field_value) {
          // フィールド名を表示（WordPressのエスケープ関数が使えない場合は代替方法）
          echo '<div style="font-weight:bold;padding:8px;background:#f5f5f5;">' . htmlspecialchars($field_name, ENT_QUOTES, 'UTF-8') . '</div>';
          echo '<div style="padding:8px;background:#fff;border:1px solid #eee;">';
          
          // 値の表示処理
          if (is_array($field_value)) {
              // 画像フィールドの処理
              if (isset($field_value['url'])) {
                  // ACFの画像フィールド形式
                  $img_url = $field_value['url'];
                  $img_alt = isset($field_value['alt']) ? $field_value['alt'] : '';
                  $img_title = isset($field_value['title']) ? $field_value['title'] : '';
                  
                  // 表示サイズ調整用のスタイル
                  echo '<div style="max-width:300px;max-height:300px;overflow:hidden;border:1px solid #ddd;padding:5px;background:#fff;border-radius:3px;">';
                  echo '<img src="' . htmlspecialchars($img_url, ENT_QUOTES, 'UTF-8') . '" 
                      alt="' . htmlspecialchars($img_alt, ENT_QUOTES, 'UTF-8') . '" 
                      title="' . htmlspecialchars($img_title, ENT_QUOTES, 'UTF-8') . '"
                      style="max-width:100%;height:auto;display:block;">';
                  echo '</div>';
                  
                  // 画像情報を表示
                  if (!empty($img_title) || !empty($img_alt)) {
                      echo '<div style="margin-top:5px;font-size:0.9em;color:#666;">';
                      if (!empty($img_title)) {
                          echo '<div>タイトル: ' . htmlspecialchars($img_title, ENT_QUOTES, 'UTF-8') . '</div>';
                      }
                      if (!empty($img_alt)) {
                          echo '<div>代替テキスト: ' . htmlspecialchars($img_alt, ENT_QUOTES, 'UTF-8') . '</div>';
                      }
                      echo '</div>';
                  }
              } elseif (isset($field_value[0]) && is_array($field_value[0]) && isset($field_value[0]['url'])) {
                  // 画像ギャラリーの処理
                  echo '<div style="display:flex;flex-wrap:wrap;gap:10px;">';
                  foreach ($field_value as $image) {
                      if (is_array($image) && isset($image['url'])) {
                          $img_url = $image['url'];
                          $img_alt = isset($image['alt']) ? $image['alt'] : '';
                          
                          echo '<div style="max-width:300px;max-height:300px;overflow:hidden;border:1px solid #ddd;padding:3px;background:#fff;border-radius:3px;margin:5px;">';
                          echo '<img src="' . htmlspecialchars($img_url, ENT_QUOTES, 'UTF-8') . '" 
                              alt="' . htmlspecialchars($img_alt, ENT_QUOTES, 'UTF-8') . '" 
                              style="max-width:100%;height:auto;display:block;">';
                          echo '</div>';
                      }
                  }
                  echo '</div>';
              } elseif (isset($field_value[0]) && is_array($field_value[0])) {
                  // リピーターフィールドの処理
                  echo '<div style="border:1px solid #ddd;padding:10px;margin-bottom:10px;">';
                  foreach ($field_value as $row_index => $row) {
                      echo '<div style="border-bottom:1px dashed #ccc;padding:5px 0;margin-bottom:10px;">';
                      echo '<h5 style="margin:0 0 5px 0;">アイテム ' . ($row_index + 1) . '</h5>';
                      
                      if (is_array($row)) {
                          foreach ($row as $sub_field_name => $sub_field_value) {
                              echo '<div style="margin-bottom:5px;"><strong>' . htmlspecialchars($sub_field_name, ENT_QUOTES, 'UTF-8') . ':</strong> ';
                              
                              // サブフィールドの値表示
                              if (is_array($sub_field_value) && isset($sub_field_value['url'])) {
                                  // 画像の場合
                                  $img_url = $sub_field_value['url'];
                                  echo '<div style="max-width:300px;max-height:300px;overflow:hidden;border:1px solid #ddd;padding:3px;background:#fff;border-radius:3px;margin-top:5px;">';
                                  echo '<img src="' . htmlspecialchars($img_url, ENT_QUOTES, 'UTF-8') . '" 
                                      alt="" style="max-width:100%;height:auto;display:block;">';
                                  echo '</div>';
                              } else {
                                  echo htmlspecialchars(is_array($sub_field_value) ? json_encode($sub_field_value, JSON_UNESCAPED_UNICODE) : $sub_field_value, ENT_QUOTES, 'UTF-8');
                              }
                              
                              echo '</div>';
                          }
                      } else {
                          echo htmlspecialchars($row, ENT_QUOTES, 'UTF-8');
                      }
                      
                      echo '</div>';
                  }
                  echo '</div>';
              } else {
                  // その他の配列の場合はJSON形式で表示
                  echo '<pre>' . htmlspecialchars(json_encode($field_value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') . '</pre>';
              }
          } elseif ($field_value === true) {
              echo '有効';
          } elseif ($field_value === false) {
              echo '無効';
          } else {
              // 文字列その他の場合
              echo htmlspecialchars($field_value, ENT_QUOTES, 'UTF-8');
          }
          
          echo '</div>';
      }
      echo '</div>'; // grid end
      
      echo '</div>'; // preview container end
  }
  ?>
  
  <?php
  // PDFの最新情報を取得
  function get_latest_pdf_info() {
      global $post;
      $slug = $post->post_name;
      $post_id = $post->ID;
      $pdf_dir = wp_upload_dir()['basedir'] . '/pdf-booklet/';
      $pdf_url = wp_upload_dir()['baseurl'] . '/pdf-booklet/';
      
      // ディレクトリが存在しなければ作成
      if (!file_exists($pdf_dir)) {
          wp_mkdir_p($pdf_dir);
          return [
              'exists' => false,
              'message' => 'PDFディレクトリを作成しました。まだPDFはアップロードされていません。',
              'expected_paths' => [
                  $pdf_dir . 'booklet-' . $slug . '.pdf',
                  $pdf_dir . 'booklet-' . $post_id . '.pdf'
              ]
          ];
      }
      
      // 検索するPDFファイルパターンの配列
      $pdf_patterns = [
          [
              'path' => $pdf_dir . 'booklet-' . $slug . '.pdf',
              'url' => $pdf_url . 'booklet-' . $slug . '.pdf',
              'name' => 'booklet-' . $slug . '.pdf'
          ],
          [
              'path' => $pdf_dir . $slug . '.pdf',
              'url' => $pdf_url . $slug . '.pdf',
              'name' => $slug . '.pdf'
          ],
          [
              'path' => $pdf_dir . 'booklet-' . $post_id . '.pdf',
              'url' => $pdf_url . 'booklet-' . $post_id . '.pdf',
              'name' => 'booklet-' . $post_id . '.pdf'
          ]
      ];
      
      // 存在するPDFを探す
      foreach ($pdf_patterns as $pattern) {
          if (file_exists($pattern['path'])) {
              return [
                  'exists' => true,
                  'url' => $pattern['url'],
                  'path' => $pattern['path'],
                  'name' => $pattern['name'],
                  'last_modified' => date('Y-m-d H:i:s', filemtime($pattern['path'])),
                  'file_size' => size_format(filesize($pattern['path']))
              ];
          }
      }
      
      // どのパターンも存在しない場合
      $expected_paths = array_map(function($item) { return $item['path']; }, $pdf_patterns);
      return [
          'exists' => false,
          'message' => 'PDFファイルが見つかりませんでした',
          'expected_paths' => $expected_paths
      ];
  }
  
  // PDFの情報を表示
  $pdf_info = get_latest_pdf_info();
  if ($pdf_info['exists']) {
      ?>
      <div class="pdf-download-section" style="margin: 20px 0; padding: 20px; background: #e7f7e7; border: 1px solid #afa; border-radius: 5px;">
          <h3 style="margin-top: 0;">📄 PDFダウンロード</h3>
          
          <div style="display: flex; align-items: center; margin: 15px 0;">
              <div style="flex: 0 0 60px; margin-right: 15px;">
                  <img src="<?php echo esc_url(plugins_url('pdf-icon.png', __FILE__)) ?: 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA1MTIgNTEyIj48IS0tISBGb250IEF3ZXNvbWUgRnJlZSA2LjUuMSBieSBAZm9udGF3ZXNvbWUgLSBodHRwczovL2ZvbnRhd2Vzb21lLmNvbSBMaWNlbnNlIC0gaHR0cHM6Ly9mb250YXdlc29tZS5jb20vbGljZW5zZS9mcmVlIENvcHlyaWdodCAyMDI0IEZvbnRpY29ucywgSW5jLiAtLT48cGF0aCBkPSJNMTI4IDBoMjU2djEyOGgtODBjLTI2LjUgMC00OCAyMS41LTQ4IDQ4djgwSDEyOFYwek00MCA5NnY0MHY0OE0xMjggNDE2aDI1NmMyNi41IDAgNDgtMjEuNSA0OC00OFYyMjRoLTUwLjdjLTMgMC01LjgtMS4wLTgtMi44bC00Ni0zOS4xYy0yLjItMS44LTQuOS0yLjgtNy45LTIuOEgyNTZjLTguOCAwLTE2IDcuMi0xNiAxNnY0Ny40YzAgMjEuOSAxOC4xIDQwIDQwIDQwaDIxLjZjNi43IDAgMTAuNCA3LjggNS44IDEzLjJMMjYwLjYgMzQ2Yy0zLjIgMy43LTcuOCA1LjgtMTIuNiA1LjhIMTc2Yy02LjYgMC0xMiA1LjQtMTIgMTJWMzg0YzAgMTcuNyAxNC4zIDMyIDMyIDMyaDY0aDEyOE0zODQgNDMyYzE3LjcgMCAzMi0xNC4zIDMyLTMycy0xNC4zLTMyLTMyLTMycy0zMiAxNC4zLTMyIDMyIDE0LjMgMzIgMzIgMzJ6Ii8+PC9zdmc+'; ?>" alt="PDF" style="width: 50px; height: auto;">
              </div>
              <div style="flex: 1;">
                  <h4 style="margin: 0 0 5px 0;"><?php echo esc_html($pdf_info['name']); ?></h4>
                  <p style="margin: 0 0 5px 0; color: #666; font-size: 0.9em;">
                      サイズ: <?php echo esc_html($pdf_info['file_size']); ?> | 
                      最終更新: <?php echo esc_html($pdf_info['last_modified']); ?>
                  </p>
                  <a href="<?php echo esc_url($pdf_info['url']); ?>" 
                     download="<?php echo esc_attr($pdf_info['name']); ?>"
                     class="pdf-download-button"
                     style="display: inline-block; background: #0066cc; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; margin-top: 8px; font-weight: bold;">
                      <span style="margin-right: 5px;">📥</span> PDFをダウンロード
                  </a>
              </div>
          </div>
          
          <div style="margin-top: 15px; font-size: 0.9em; color: #666;">
              <p>※ PDFはGitHub Actionsにより自動生成されています。最新の情報が反映されていない場合は、しばらく待ってからページを更新してください。</p>
          </div>
      </div>
      <?php
  } else {
      ?>
      <div class="pdf-not-found" style="margin: 20px 0; padding: 20px; background: #f7f7e7; border: 1px solid #dda; border-radius: 5px;">
          <h3 style="margin-top: 0;">📄 PDFファイル未生成</h3>
          <p><?php echo esc_html($pdf_info['message'] ?? 'PDFはまだ生成されていないか、アップロードディレクトリに存在しません。'); ?></p>
          
          <div style="background: #f5f5f5; padding: 10px; border-left: 3px solid #ccc; margin: 10px 0;">
              <p style="margin: 0 0 5px 0; font-weight: bold;">探索したパス:</p>
              <ul style="margin: 0; padding-left: 20px;">
              <?php foreach ($pdf_info['expected_paths'] as $path) : ?>
                  <li><code><?php echo esc_html($path); ?></code></li>
              <?php endforeach; ?>
              </ul>
          </div>
          
          <p style="margin-top: 15px;">
              PDFは GitHub Actions によって自動生成され、サーバーにアップロードされます。
              このプロセスには数分かかることがあります。
          </p>
          
          <a href="<?php echo esc_url(admin_url('admin.php?page=pdf-booklet-build')); ?>" 
             style="display: inline-block; background: #f0ad4e; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; margin-top: 10px; font-weight: bold;">
              <span style="margin-right: 5px;">🔄</span> PDFビルドを手動で実行
          </a>
      </div>
      <?php
  }
  ?>
</main>

<?php get_footer(); ?> 