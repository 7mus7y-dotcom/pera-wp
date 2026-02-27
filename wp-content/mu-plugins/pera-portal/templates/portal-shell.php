<?php

if (!defined('ABSPATH')) {
    exit;
}

?>
<section class="hero hero--left hero--fit" id="pera-portal-hero">
    <div class="hero-content container">
        <h1><?php echo esc_html__('Pera Portal', 'pera-portal'); ?></h1>
        <p class="lead"><?php echo esc_html__('Interactive floor plan viewer for units and availability.', 'pera-portal'); ?></p>

        <?php if (function_exists('pera_portal_current_user_can_access') && pera_portal_current_user_can_access()) : ?>
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
                <div class="pera-portal-floorbar">
                    <label for="pera-portal-floor-select"><?php echo esc_html__('Floor', 'pera-portal'); ?></label>
                    <select id="pera-portal-floor-select" class="pera-portal-floor-select"><option value=""><?php echo esc_html__('Select a building in shortcode.', 'pera-portal'); ?></option></select>
                </div>
                <div class="pera-portal-svg-placeholder"><?php echo esc_html__('SVG plan placeholder', 'pera-portal'); ?></div>
            </section>
            <aside class="pera-portal-panel pera-portal-panel--details" aria-label="Unit details">
                <div class="pera-portal-panel__head">
                    <h3><?php echo esc_html__('Unit Details', 'pera-portal'); ?></h3>
                </div>
                <div class="pera-portal-legend" aria-label="Unit status legend">
                    <span class="pera-portal-legend__item"><span class="pera-portal-legend__swatch pera-portal-legend__swatch--available"></span><?php echo esc_html__('Available', 'pera-portal'); ?></span>
                    <span class="pera-portal-legend__item"><span class="pera-portal-legend__swatch pera-portal-legend__swatch--reserved"></span><?php echo esc_html__('Reserved', 'pera-portal'); ?></span>
                    <span class="pera-portal-legend__item"><span class="pera-portal-legend__swatch pera-portal-legend__swatch--sold"></span><?php echo esc_html__('Sold', 'pera-portal'); ?></span>
                </div>
                <div class="pera-portal-filters" aria-label="Filter units by status">
                    <label class="pera-portal-filter-pill"><input type="checkbox" data-status-filter="available" checked> <?php echo esc_html__('Available', 'pera-portal'); ?></label>
                    <label class="pera-portal-filter-pill"><input type="checkbox" data-status-filter="reserved" checked> <?php echo esc_html__('Reserved', 'pera-portal'); ?></label>
                    <label class="pera-portal-filter-pill"><input type="checkbox" data-status-filter="sold" checked> <?php echo esc_html__('Sold', 'pera-portal'); ?></label>
                </div>
                <div class="pera-portal-counts" aria-live="polite"></div>
                <div class="pera-portal-details-placeholder"><?php echo esc_html__('Unit details placeholder', 'pera-portal'); ?></div>
            </aside>
        </div>
    </div>
</section>
