<?php
/**
 * Regression checks for the shared CRM panel surface primitive.
 */

$root = dirname(__DIR__);
$css = file_get_contents($root . '/assets/frontend/crm.css');
$views = [
    $root . '/inc/views/pages/crm-new.php',
    $root . '/inc/views/pages/crm-performance.php',
    $root . '/inc/views/pages/crm-logs.php',
    $root . '/inc/views/pages/crm-overview.php',
    $root . '/inc/views/pages/crm-client.php',
    $root . '/inc/views/partials/manage-panels.php',
    $root . '/inc/views/partials/property-instructions.php',
    $root . '/inc/whatsapp.php',
];

$expect = static function ($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL {$message}\n");
        exit(1);
    }
    fwrite(STDOUT, "PASS {$message}\n");
};

$expect(false !== strpos($css, '.crm-page .crm-panel-surface {'), 'shared panel surface is defined');
$expect(false !== strpos($css, '.crm-page .crm-panel-surface--gradient {'), 'variable-driven gradient modifier is defined');
$expect(false !== strpos($css, '--crm-panel-gradient-start:'), 'custom start colour variable is available');
$expect(false !== strpos($css, '--crm-panel-gradient-end:'), 'custom end colour variable is available');

foreach (['cool', 'warm', 'brand'] as $preset) {
    $expect(
        false !== strpos($css, '.crm-page .crm-panel-surface--gradient-' . $preset),
        $preset . ' gradient preset is defined'
    );
}

$expect(false !== strpos($css, '@media (prefers-color-scheme: dark)'), 'OS dark mode is covered');
$expect(false !== strpos($css, '.crm-page[data-theme="dark"] .crm-panel-surface'), 'explicit dark mode is covered');

foreach ($views as $view) {
    $source = file_get_contents($view);
    $has_sections = false !== strpos($source, 'crm-section');
    if ($has_sections) {
        $expect(
            false !== strpos($source, 'crm-panel-surface crm-section')
                || false !== strpos($source, 'crm-panel-surface--gradient'),
            basename($view) . ' adopts the shared panel surface'
        );
    }
    $expect(
        false === strpos($source, 'crm-panel-surface crm-section__'),
        basename($view) . ' does not apply the panel surface to section child elements'
    );
}

$overview = file_get_contents($root . '/inc/views/pages/crm-overview.php');
$expect(
    false !== strpos($overview, 'crm-panel-surface--gradient-warm crm-section crm-overview-priority'),
    'priority work uses the warm overview gradient'
);
$expect(
    false !== strpos($overview, 'crm-panel-surface--gradient-cool crm-section crm-overview-queue'),
    'lead queues use the cool overview gradient'
);
$expect(
    false !== strpos($overview, 'crm-panel-surface--gradient-brand crm-section crm-overview-metrics'),
    'pipeline health uses the brand overview gradient'
);
$expect(
    false !== strpos($overview, 'crm-list-workspace--rows crm-list-workspace--new-leads'),
    'New leads has a dedicated workspace modifier'
);
$expect(
    false !== strpos($css, '.crm-page .crm-list-workspace--new-leads > .crm-section__header')
        && false !== strpos($css, 'padding-inline: var(--crm-section-padding);'),
    'New leads header and contents receive shared inline padding'
);

fwrite(STDOUT, "CRM panel surface regression checks passed.\n");
