<?php

if (!defined('ABSPATH')) {
    exit;
}

$wrap_main = !isset($GLOBALS['pera_portal_wrap_main']) || (bool) $GLOBALS['pera_portal_wrap_main'];
$post_classes = implode(' ', get_post_class('', get_queried_object_id()));
?>
<?php if ($wrap_main) : ?>
<main id="content" class="site-main <?php echo esc_attr($post_classes); ?>">
<?php endif; ?>
    <section class="hero hero--left hero--fit" id="pera-portal-hero">
        <div class="hero-content container">
            <h1><?php echo esc_html__('Pera Portal', 'pera-portal'); ?></h1>
            <p class="lead"><?php echo esc_html__('Interactive floor plan viewer for units and availability.', 'pera-portal'); ?></p>

            <?php if (current_user_can('manage_options')) : ?>
                <div class="hero-actions">
                    <a class="btn btn--ghost btn--green" href="<?php echo esc_url(admin_url('admin.php?page=pera-portal')); ?>">
                        <?php echo esc_html__('Manage Portal Data', 'pera-portal'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="section section-soft pera-portal-viewer-wrap">
        <div class="container">
            <div id="pera-portal-root" class="pera-portal-shell">
                <section class="pera-portal-panel pera-portal-panel--svg" aria-label="Floor plan viewer">
                    <div class="pera-portal-panel__head">
                        <h3><?php echo esc_html__('Floor Plan', 'pera-portal'); ?></h3>
                    </div>
                    <div class="pera-portal-svg-placeholder"><?php echo esc_html__('SVG plan placeholder', 'pera-portal'); ?></div>
                </section>
                <aside class="pera-portal-panel pera-portal-panel--details" aria-label="Unit details">
                    <div class="pera-portal-panel__head">
                        <h3><?php echo esc_html__('Unit Details', 'pera-portal'); ?></h3>
                    </div>
                    <div class="pera-portal-details-placeholder"><?php echo esc_html__('Unit details placeholder', 'pera-portal'); ?></div>
                </aside>
            </div>
        </div>
    </section>
<?php if ($wrap_main) : ?>
</main>
<?php endif; ?>
