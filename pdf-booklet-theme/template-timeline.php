<?php
/**
 * Template Name: PDF Booklet: ⑧ 年表（年、月、出来事）×100
 * Description: 年表形式のPDFブックレットテンプレート（最大100項目）
 */

get_header(); ?>

<div class="pdf-booklet-template template-timeline">
    <div class="container">
        <?php while (have_posts()) : the_post(); ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                <header class="entry-header">
                    <h1 class="entry-title"><?php the_title(); ?></h1>
                </header>

                <div class="entry-content">
                    <?php if (function_exists('get_field')): ?>
                        <?php 
                        $timeline_title = get_field('timeline_title');
                        $timeline_items = get_field('timeline_items');
                        ?>
                        
                        <?php if ($timeline_title): ?>
                            <h2 class="pdf-timeline-title"><?php echo esc_html($timeline_title); ?></h2>
                        <?php endif; ?>
                        
                        <?php if ($timeline_items): ?>
                            <div class="pdf-timeline">
                                <table class="timeline-table">
                                    <thead>
                                        <tr>
                                            <th class="timeline-year">年</th>
                                            <th class="timeline-month">月</th>
                                            <th class="timeline-event">出来事</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($timeline_items as $item): ?>
                                            <tr>
                                                <td class="timeline-year">
                                                    <?php echo esc_html($item['year']); ?>
                                                </td>
                                                <td class="timeline-month">
                                                    <?php echo $item['month'] ? esc_html($item['month']) . '月' : ''; ?>
                                                </td>
                                                <td class="timeline-event">
                                                    <?php echo wp_kses_post(nl2br($item['event'])); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
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
