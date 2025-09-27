<?php
/**
 * Template Name: PDF Booklet: ⑨ 画像キャプション×2
 * Description: 画像キャプション2つを含むPDFブックレットテンプレート
 */

// テンプレートファイルとして機能するための基本構造
get_header(); ?>

<div class="pdf-booklet-template template-image-caption-2">
    <div class="container">
        <?php while (have_posts()) : the_post(); ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                <header class="entry-header">
                    <h1 class="entry-title"><?php the_title(); ?></h1>
                </header>

                <div class="entry-content">
                    <?php if (function_exists('get_field')): ?>
                        <?php 
                        $image_1 = get_field('image_1');
                        $caption_1 = get_field('caption_1');
                        $image_2 = get_field('image_2');
                        $caption_2 = get_field('caption_2');
                        ?>
                        
                        <div class="pdf-image-grid pdf-image-grid-2-only">
                            <?php if ($image_1): ?>
                                <div class="pdf-image-block">
                                    <img src="<?php echo esc_url($image_1['url']); ?>" 
                                         alt="<?php echo esc_attr($image_1['alt']); ?>" 
                                         class="pdf-image" />
                                    <?php if ($caption_1): ?>
                                        <div class="pdf-caption">
                                            <?php echo wp_kses_post(nl2br($caption_1)); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($image_2): ?>
                                <div class="pdf-image-block">
                                    <img src="<?php echo esc_url($image_2['url']); ?>" 
                                         alt="<?php echo esc_attr($image_2['alt']); ?>" 
                                         class="pdf-image" />
                                    <?php if ($caption_2): ?>
                                        <div class="pdf-caption">
                                            <?php echo wp_kses_post(nl2br($caption_2)); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
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