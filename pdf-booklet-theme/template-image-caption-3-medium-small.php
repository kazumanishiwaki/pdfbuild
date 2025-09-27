<?php
/**
 * Template Name: PDF Booklet: ⑩ 画像キャプション×3（中1小2）
 * Description: 画像キャプション3つ（中1小2）を含むPDFブックレットテンプレート
 */

// テンプレートファイルとして機能するための基本構造
get_header(); ?>

<div class="pdf-booklet-template template-image-caption-3-medium-small">
    <div class="container">
        <?php while (have_posts()) : the_post(); ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                <header class="entry-header">
                    <h1 class="entry-title"><?php the_title(); ?></h1>
                </header>

                <div class="entry-content">
                    <?php if (function_exists('get_field')): ?>
                        <?php 
                        $image_medium = get_field('image_medium');
                        $caption_medium = get_field('caption_medium');
                        $image_small_1 = get_field('image_small_1');
                        $caption_small_1 = get_field('caption_small_1');
                        $image_small_2 = get_field('image_small_2');
                        $caption_small_2 = get_field('caption_small_2');
                        ?>
                        
                        <div class="pdf-image-grid pdf-image-grid-3-medium-small-only">
                            <?php if ($image_medium): ?>
                                <div class="pdf-image-block pdf-image-medium">
                                    <img src="<?php echo esc_url($image_medium['url']); ?>" 
                                         alt="<?php echo esc_attr($image_medium['alt']); ?>" 
                                         class="pdf-image" />
                                    <?php if ($caption_medium): ?>
                                        <div class="pdf-caption">
                                            <?php echo wp_kses_post(nl2br($caption_medium)); ?>
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
