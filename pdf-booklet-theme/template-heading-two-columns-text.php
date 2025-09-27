<?php
/**
 * Template Name: ① 見出し＋左右カラム本文
 * Description: 見出しと左右2カラムの本文を含むPDFブックレットテンプレート
 */

// テンプレートファイルとして機能するための基本構造
get_header(); ?>

<div class="pdf-booklet-template template-heading-two-columns-text">
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
                        $right_content = get_field('right_content');
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
                            
                            <?php if ($right_content): ?>
                                <div class="pdf-right-column">
                                    <div class="pdf-content">
                                        <?php echo wp_kses_post(nl2br($right_content)); ?>
                                    </div>
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
