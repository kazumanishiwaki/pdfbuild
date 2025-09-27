<?php
/**
 * Template Name: PDF Booklet: ③ 大見出し＋（見出し＋本文）×３
 * Description: 大見出しと3つのセクションを含むPDFブックレットテンプレート
 */

get_header(); ?>

<div class="pdf-booklet-template template-main-heading-3">
    <div class="container">
        <?php while (have_posts()) : the_post(); ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                <header class="entry-header">
                    <h1 class="entry-title"><?php the_title(); ?></h1>
                </header>

                <div class="entry-content">
                    <?php if (function_exists('get_field')): ?>
                        <?php 
                        $main_title = get_field('main_title');
                        $section_1_title = get_field('section_1_title');
                        $section_1_content = get_field('section_1_content');
                        $section_2_title = get_field('section_2_title');
                        $section_2_content = get_field('section_2_content');
                        $section_3_title = get_field('section_3_title');
                        $section_3_content = get_field('section_3_content');
                        ?>
                        
                        <?php if ($main_title): ?>
                            <h1 class="pdf-main-heading"><?php echo esc_html($main_title); ?></h1>
                        <?php endif; ?>
                        
                        <?php 
                        $sections = [
                            ['title' => $section_1_title, 'content' => $section_1_content],
                            ['title' => $section_2_title, 'content' => $section_2_content],
                            ['title' => $section_3_title, 'content' => $section_3_content]
                        ];
                        foreach ($sections as $section): 
                            if ($section['title'] || $section['content']): ?>
                                <div class="pdf-section">
                                    <?php if ($section['title']): ?>
                                        <h2 class="pdf-section-heading"><?php echo esc_html($section['title']); ?></h2>
                                    <?php endif; ?>
                                    <?php if ($section['content']): ?>
                                        <div class="pdf-section-content">
                                            <?php echo wp_kses_post(nl2br($section['content'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif;
                        endforeach; ?>
                        
                    <?php else: ?>
                        <p>ACFプラグインが必要です。</p>
                    <?php endif; ?>
                </div>
            </article>
        <?php endwhile; ?>
    </div>
</div>

<?php get_footer(); ?>
