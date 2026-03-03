<?php

if (!defined('ABSPATH')) {
    exit;
}

$can_access = function_exists('pera_portal_current_user_can_access')
    ? (bool) pera_portal_current_user_can_access()
    : current_user_can('manage_options');

if (!$can_access) {
    wp_die(esc_html__('Access denied.', 'pera-portal'), esc_html__('Portal', 'pera-portal'), ['response' => 403]);
}

$building_query = new WP_Query([
    'post_type' => 'pera_building',
    'post_status' => ['publish'],
    'posts_per_page' => -1,
    'orderby' => 'title',
    'order' => 'ASC',
]);

get_header();
?>
<main id="content" class="site-main pera-portal-landing">
    <div class="container">
        <h1><?php echo esc_html__('Portal', 'pera-portal'); ?></h1>

        <?php if ($building_query->have_posts()) : ?>
            <ul class="pera-portal-building-list">
                <?php while ($building_query->have_posts()) : $building_query->the_post(); ?>
                    <li class="pera-portal-building-list__item">
                        <span class="pera-portal-building-list__title"><?php the_title(); ?></span>
                        <a class="button button-primary" href="<?php echo esc_url(home_url('/portal/building/' . get_the_ID() . '/')); ?>">
                            <?php echo esc_html__('Open', 'pera-portal'); ?>
                        </a>
                    </li>
                <?php endwhile; ?>
            </ul>
        <?php else : ?>
            <p><?php echo esc_html__('No buildings found.', 'pera-portal'); ?></p>
        <?php endif; ?>
    </div>
</main>
<?php
wp_reset_postdata();
get_footer();
