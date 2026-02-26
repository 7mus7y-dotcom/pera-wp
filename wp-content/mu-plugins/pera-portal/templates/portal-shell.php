<?php

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="pera-portal-page">
    <header class="pera-portal-hero section section-soft">
        <div class="container">
            <p class="text-upper text-xs"><?php echo esc_html__('Pera Portal', 'pera-portal'); ?></p>
            <h1><?php echo esc_html__('Floor Plan Viewer', 'pera-portal'); ?></h1>
            <p class="muted"><?php echo esc_html__('Select a unit on the plan to view details.', 'pera-portal'); ?></p>
        </div>
    </header>

    <section class="pera-portal-section section">
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
</div>
