<?php
/**
 * Template Name: PDF Booklet: ⑤ （画像＋キャプション）×２
 * Description: 2つの画像とキャプションを含むPDFブックレットテンプレート
 */

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
                        $images = [
                            ['image' => get_field('image_1'), 'caption' => get_field('caption_1')],
                            ['image' => get_field('image_2'), 'caption' => get_field('caption_2')]
                        ];
                        ?>
                        
                        <?php foreach ($images as $index => $item): ?>
                            <?php if ($item['image']): ?>
                                <div class="pdf-image-block image-<?php echo $index + 1; ?>">
                                    <img src="<?php echo esc_url($item['image']['url']); ?>" 
                                         alt="<?php echo esc_attr($item['image']['alt']); ?>" 
                                         class="pdf-image" />
                                    <?php if ($item['caption']): ?>
                                        <div class="pdf-caption">
                                            <?php echo wp_kses_post(nl2br($item['caption'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        
                    <?php else: ?>
                        <p>ACFプラグインが必要です。</p>
                    <?php endif; ?>
                </div>
            </article>
        <?php endwhile; ?>
    </div>
</div>

<?php get_footer(); ?>
