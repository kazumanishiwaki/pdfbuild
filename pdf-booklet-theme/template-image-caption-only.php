<?php
/**
 * Template Name: ⑯ 画像キャプションのみ
 * Description: 画像キャプション1つのみを含むPDFブックレットテンプレート
 */

// テンプレートファイルとして機能するための基本構造
get_header(); ?>

<div class="pdf-booklet-template template-image-caption-only">
    <div class="container">
        <?php while (have_posts()) : the_post(); ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                <header class="entry-header">
                    <h1 class="entry-title"><?php the_title(); ?></h1>
                </header>

                <div class="entry-content">
                    <?php if (function_exists('get_field')): ?>
                        <?php 
                        $image = get_field('image');
                        $caption = get_field('caption');
                        ?>
                        
                        <div class="pdf-single-image">
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
                        
                    <?php else: ?>
                        <p>ACFプラグインが必要です。</p>
                    <?php endif; ?>
                </div>
            </article>
        <?php endwhile; ?>
    </div>
</div>

<?php get_footer(); ?>
