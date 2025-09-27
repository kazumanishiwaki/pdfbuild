<?php
/**
 * Template Name: ② 見出し＋左本文＋右画像キャプション
 * Description: 見出し、左カラム本文、右カラム画像キャプション1つを含むPDFブックレットテンプレート
 */

// テンプレートファイルとして機能するための基本構造
get_header(); ?>

<div class="pdf-booklet-template template-heading-text-image-1">
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
                        $image = get_field('image');
                        $caption = get_field('caption');
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
                                <?php if ($image): ?>
                                    <div class="pdf-image-block">
                                        <img src="<?php echo esc_url($image['url']); ?>" 
                                             alt="<?php echo esc_attr($image['alt']); ?>" 
                                             class="pdf-image" />
                                        <?php if ($caption): ?>
                                            <div class="pdf-caption">
                                                <?php echo wp_kses_post(nl2br($caption)); ?>
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
