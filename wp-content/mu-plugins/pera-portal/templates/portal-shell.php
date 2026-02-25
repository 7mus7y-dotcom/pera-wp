<?php

if (!defined('ABSPATH')) {
    exit;
}
?>
<div id="pera-portal-root" class="pera-portal-shell">
    <section class="pera-portal-panel pera-portal-panel--svg" aria-label="Floor plan viewer">
        <h3><?php echo esc_html__('Floor Plan', 'pera-portal'); ?></h3>
        <div class="pera-portal-svg-placeholder"><?php echo esc_html__('SVG plan placeholder', 'pera-portal'); ?></div>
    </section>
    <aside class="pera-portal-panel pera-portal-panel--details" aria-label="Unit details">
        <h3><?php echo esc_html__('Unit Details', 'pera-portal'); ?></h3>
        <div class="pera-portal-details-placeholder"><?php echo esc_html__('Unit details placeholder', 'pera-portal'); ?></div>
    </aside>
</div>
