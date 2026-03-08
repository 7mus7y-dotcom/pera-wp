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
            <div id="portal-print-scope" class="portal-print-scope">
            <section class="pera-portal-panel pera-portal-panel--svg" aria-label="Floor plan viewer">
                <div class="pera-portal-panel__head">
                    <h3><?php echo esc_html__('Floor Plan', 'pera-portal'); ?></h3>
                </div>
                <div class="pera-portal-floorbar portal-print-section portal-print-section--selector">
                    <label for="pera-portal-floor-select"><?php echo esc_html__('Floor', 'pera-portal'); ?></label>
                    <select id="pera-portal-floor-select" class="pera-portal-floor-select"><option value=""><?php echo esc_html__('Select a building in shortcode.', 'pera-portal'); ?></option></select>
                </div>
                <div class="pera-portal-topbar">
                    <div class="pera-portal-colormode" role="group" aria-label="Color mode">
                        <button type="button" class="pera-portal-colormode-btn is-active" data-color-mode="availability">Availability</button>
                        <button type="button" class="pera-portal-colormode-btn" data-color-mode="price">Price</button>
                    </div>

                    <div class="pera-portal-shortlistbar" aria-label="Shortlist controls">
                        <span class="pera-portal-shortlistcount">
                            <?php echo esc_html__('Shortlist:', 'pera-portal'); ?>
                            <strong data-shortlist-count>0</strong>
                        </span>

                        <button type="button"
                                class="pera-portal-shortlistclear"
                                data-shortlist-clear
                                disabled>
                            <?php echo esc_html__('Clear', 'pera-portal'); ?>
                        </button>
                    </div>

                    <div class="pera-portal-share" aria-label="<?php echo esc_attr__('Share controls', 'pera-portal'); ?>">
                        <button type="button" class="pera-portal-iconbtn" data-share="whatsapp" aria-label="<?php echo esc_attr__('Share on WhatsApp', 'pera-portal'); ?>" title="<?php echo esc_attr__('Share on WhatsApp', 'pera-portal'); ?>">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path fill="currentColor" d="M12.04 2a9.93 9.93 0 0 0-8.5 15.08L2 22l5.08-1.5A9.95 9.95 0 1 0 12.04 2Zm0 18.1a8.1 8.1 0 0 1-4.13-1.12l-.3-.18-3 .89.9-2.93-.2-.3a8.11 8.11 0 1 1 6.73 3.64Zm4.44-6.1c-.24-.13-1.4-.69-1.63-.76-.22-.08-.38-.12-.54.12-.16.24-.61.76-.75.91-.14.16-.28.18-.52.06-.24-.12-1-.38-1.9-1.2-.7-.62-1.17-1.4-1.31-1.63-.14-.24-.01-.36.1-.49.11-.12.24-.3.36-.45.12-.16.16-.27.24-.45.08-.19.04-.35-.02-.49-.06-.13-.54-1.3-.74-1.78-.2-.48-.41-.4-.54-.4h-.46a.89.89 0 0 0-.64.3c-.22.24-.84.82-.84 2 0 1.18.86 2.33.98 2.49.12.16 1.67 2.54 4.04 3.56 2.37 1.02 2.37.68 2.8.64.42-.04 1.4-.57 1.6-1.12.2-.55.2-1.02.14-1.12-.06-.1-.22-.16-.46-.28Z"/>
                            </svg>
                        </button>
                        <button type="button" class="pera-portal-iconbtn" data-share="copy" aria-label="<?php echo esc_attr__('Copy link', 'pera-portal'); ?>" title="<?php echo esc_attr__('Copy link', 'pera-portal'); ?>">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path fill="currentColor" d="M3.9 12a5 5 0 0 1 5-5h3v2h-3a3 3 0 0 0 0 6h3v2h-3a5 5 0 0 1-5-5Zm6-1h4v2h-4v-2Zm5-4h3a5 5 0 1 1 0 10h-3v-2h3a3 3 0 1 0 0-6h-3V7Z"/>
                            </svg>
                        </button>
                        <a class="pera-portal-iconbtn" data-share="newwin" href="#" target="_blank" rel="noopener" aria-label="<?php echo esc_attr__('Open in new window', 'pera-portal'); ?>" title="<?php echo esc_attr__('Open in new window', 'pera-portal'); ?>">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path fill="currentColor" d="M14 3h7v7h-2V6.41l-9.29 9.3-1.42-1.42 9.3-9.29H14V3ZM5 5h6v2H7v10h10v-4h2v6H5V5Z"/>
                            </svg>
                        </a>
                        <button type="button" class="pera-portal-iconbtn" data-share="print" aria-label="<?php echo esc_attr__('Print page', 'pera-portal'); ?>" title="<?php echo esc_attr__('Print page', 'pera-portal'); ?>">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path fill="currentColor" d="M6 8V3h12v5H6Zm10-2V5H8v1h8Zm2 4h1a3 3 0 0 1 3 3v4h-4v4H6v-4H2v-4a3 3 0 0 1 3-3h1v2H5a1 1 0 0 0-1 1v2h2v-2h12v2h2v-2a1 1 0 0 0-1-1h-1v-2Zm-2 9v-5H8v5h8Z"/>
                            </svg>
                        </button>
                        <span class="pera-portal-share-toast" data-share-toast hidden><?php echo esc_html__('Copied', 'pera-portal'); ?></span>
                    </div>
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
                <div class="pera-portal-summary" hidden>
                    <div class="pera-portal-summary__grid">
                        <div><strong><?php echo esc_html__('Total Value', 'pera-portal'); ?></strong><br><span data-summary-total>—</span></div>
                        <div><strong><?php echo esc_html__('Average PPSQM', 'pera-portal'); ?></strong><br><span data-summary-pps>—</span></div>
                        <div><strong><?php echo esc_html__('Total Gross m²', 'pera-portal'); ?></strong><br><span data-summary-size>—</span></div>
                        <div><strong><?php echo esc_html__('Units Selected', 'pera-portal'); ?></strong><br><span data-summary-count>0</span></div>
                    </div>
                </div>
                <div class="pera-portal-compare" hidden>
                    <div class="pera-portal-compare__head">
                        <h4><?php echo esc_html__('Shortlist comparison', 'pera-portal'); ?></h4>
                        <p class="pera-portal-compare__hint"><?php echo esc_html__('Tip: Shift+Click units to shortlist them.', 'pera-portal'); ?></p>
                    </div>
                    <div class="pera-portal-compare__wrap">
                        <table class="pera-portal-compare__table">
                            <thead>
                                <tr>
                                    <th><?php echo esc_html__('Code', 'pera-portal'); ?></th>
                                    <th><?php echo esc_html__('Type', 'pera-portal'); ?></th>
                                    <th><?php echo esc_html__('Net', 'pera-portal'); ?></th>
                                    <th><?php echo esc_html__('Gross', 'pera-portal'); ?></th>
                                    <th><?php echo esc_html__('Price', 'pera-portal'); ?></th>
                                    <th><?php echo esc_html__('Status', 'pera-portal'); ?></th>
                                    <th><?php echo esc_html__('View plan', 'pera-portal'); ?></th>
                                    <th aria-label="Remove"></th>
                                </tr>
                            </thead>
                            <tbody data-compare-body></tbody>
                        </table>
                    </div>
                </div>
                <div class="portal-print-section portal-print-section--details">
                    <div class="pera-portal-details-placeholder"><?php echo esc_html__('Unit details placeholder', 'pera-portal'); ?></div>
                    <div class="pera-portal-quote-tools" data-quote-tools hidden></div>
                </div>
                <div class="portal-print-section portal-print-section--plan">
                    <div class="pera-portal-plan-placeholder"></div>
                </div>
            </aside>
            </div>
        </div>
    </div>
</section>
