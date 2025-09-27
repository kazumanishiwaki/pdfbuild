<?php
/**
 * Template Name: PDF Booklet: ⑤ 見出し＋左本文＋右画像キャプション×4（大1小3）
 * Description: 見出し、左カラム本文、右カラム画像キャプション4つ（大1小3）を含むPDFブックレットテンプレート
 */

// テンプレートファイルとして機能するための基本構造
get_header(); ?>

<div class="pdf-booklet-template template-heading-text-image-4-large-small">
    <div class="container">
        <?php while (have_posts()) : the_post(); ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                <header class="entry-header">
                    <h1 class="entry-title"><?php the_title(); ?></h1>
                </header>

                <div class="entry-content">
                    <?php if (function_exists('get_field')): ?>
                        <?php 
                        $heading = get_field('heading');
                        $left_content = get_field('left_content');
                        $image_large = get_field('image_large');
                        $caption_large = get_field('caption_large');
                        $image_small_1 = get_field('image_small_1');
                        $caption_small_1 = get_field('caption_small_1');
                        $image_small_2 = get_field('image_small_2');
                        $caption_small_2 = get_field('caption_small_2');
                        $image_small_3 = get_field('image_small_3');
                        $caption_small_3 = get_field('caption_small_3');
                        ?>
                        
                        <?php if ($heading): ?>
                            <h2 class="pdf-heading"><?php echo esc_html($heading); ?></h2>
                        <?php endif; ?>
                        
                        <div class="pdf-two-columns">
                            <?php if ($left_content): ?>
                                <div class="pdf-left-column">
                                    <div class="pdf-content">
                                        <?php echo wp_kses_post(nl2br($left_content)); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="pdf-right-column">
                                <div class="pdf-image-grid pdf-image-grid-4-large-small">
                                    <?php if ($image_large): ?>
                                        <div class="pdf-image-block pdf-image-large">
                                            <img src="<?php echo esc_url($image_large['url']); ?>" 
                                                 alt="<?php echo esc_attr($image_large['alt']); ?>" 
                                                 class="pdf-image" />
                                            <?php if ($caption_large): ?>
                                                <div class="pdf-caption">
                                                    <?php echo wp_kses_post(nl2br($caption_large)); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="pdf-small-images">
                                        <?php if ($image_small_1): ?>
                                            <div class="pdf-image-block pdf-image-small">
                                                <img src="<?php echo esc_url($image_small_1['url']); ?>" 
                                                     alt="<?php echo esc_attr($image_small_1['alt']); ?>" 
                                                     class="pdf-image" />
                                                <?php if ($caption_small_1): ?>
                                                    <div class="pdf-caption">
                                                        <?php echo wp_kses_post(nl2br($caption_small_1)); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($image_small_2): ?>
                                            <div class="pdf-image-block pdf-image-small">
                                                <img src="<?php echo esc_url($image_small_2['url']); ?>" 
                                                     alt="<?php echo esc_attr($image_small_2['alt']); ?>" 
                                                     class="pdf-image" />
                                                <?php if ($caption_small_2): ?>
                                                    <div class="pdf-caption">
                                                        <?php echo wp_kses_post(nl2br($caption_small_2)); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($image_small_3): ?>
                                            <div class="pdf-image-block pdf-image-small">
                                                <img src="<?php echo esc_url($image_small_3['url']); ?>" 
                                                     alt="<?php echo esc_attr($image_small_3['alt']); ?>" 
                                                     class="pdf-image" />
                                                <?php if ($caption_small_3): ?>
                                                    <div class="pdf-caption">
                                                        <?php echo wp_kses_post(nl2br($caption_small_3)); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    <?php else: ?>
                        <p>ACFプラグインが必要です。</p>
                    <?php endif; ?>
                </div>
            </article>
        <?php endwhile; ?>
    </div>
</div>

<?php get_footer(); ?>
