<?php
/**
 * Template Name: PDF Booklet: ① 見出し＋本文
 * Description: 見出しと本文を含むシンプルなPDFブックレットテンプレート
 */

// テンプレートファイルとして機能するための基本構造
get_header(); ?>

<div class="pdf-booklet-template template-heading-text">
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
                        $content = get_field('content');
                        ?>
                        
                        <?php if ($heading): ?>
                            <h2 class="pdf-heading"><?php echo esc_html($heading); ?></h2>
                        <?php endif; ?>
                        
                        <?php if ($content): ?>
                            <div class="pdf-content">
                                <?php echo wp_kses_post(nl2br($content)); ?>
                            </div>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <p>ACFプラグインが必要です。</p>
                    <?php endif; ?>
                </div>
            </article>
        <?php endwhile; ?>
    </div>
</div>

<?php get_footer(); ?>
