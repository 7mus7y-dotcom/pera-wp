<?php

if (!defined('ABSPATH')) {
    exit;
}

$token = sanitize_text_field((string) get_query_var('pera_quote_token'));
$quote = $token !== '' ? pera_portal_quote_find_by_token($token) : null;

if (!($quote instanceof WP_Post) || $quote->post_type !== 'pera_quote') {
    global $wp_query;
    if (isset($wp_query) && $wp_query instanceof WP_Query) {
        $wp_query->set_404();
    }
    status_header(404);
    nocache_headers();
    $not_found_template = get_404_template();
    if ($not_found_template) {
        include $not_found_template;
    }
    exit;
}

$status = pera_portal_quote_get_business_status($quote->ID);
$payload = json_decode((string) get_post_meta($quote->ID, '_pera_quote_payload_v1', true), true);
$payload = is_array($payload) ? $payload : [];
$floor_svg = (string) get_post_meta($quote->ID, '_pera_quote_floor_plan_svg', true);
$floor_svg_renderable = function_exists('pera_portal_quote_sanitize_svg_markup')
    ? pera_portal_quote_sanitize_svg_markup($floor_svg)
    : '';
$apartment_plan_id = absint(get_post_meta($quote->ID, '_pera_quote_apartment_plan_attachment_id', true));
$apartment_plan_url = $apartment_plan_id > 0 ? wp_get_attachment_url($apartment_plan_id) : '';

$banner = $status === 'revoked'
    ? __('This quote has been revoked. Please contact your consultant for an updated offer.', 'pera-portal')
    : ($status === 'expired'
        ? __('This quote has expired. Please contact your consultant for a refreshed quote.', 'pera-portal')
        : __('Quote is active and valid until the expiry date below.', 'pera-portal'));

status_header(200);
nocache_headers();
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <?php wp_head(); ?>
    <style>
        .pera-quote-page{font-family:Arial,sans-serif;max-width:1000px;margin:0 auto;padding:24px;color:#111}
        .pera-quote-header,.pera-quote-section{border:1px solid #ddd;border-radius:8px;padding:16px;margin-bottom:16px}
        .pera-quote-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
        .pera-quote-status{padding:12px;border-radius:8px;font-weight:600}.status-active{background:#ecfdf5;color:#166534}.status-expired{background:#fff7ed;color:#9a3412}.status-revoked{background:#fef2f2;color:#991b1b}
        .pera-quote-price{font-size:30px;font-weight:700}
        .pera-quote-plan img,.pera-quote-plan svg{max-width:100%;height:auto;border:1px solid #ddd;border-radius:4px}
        @media print {.pera-quote-page{padding:0}.pera-quote-header,.pera-quote-section{page-break-inside:avoid;box-shadow:none}}
    </style>
</head>
<body <?php body_class('pera-quote-public-page'); ?>>
    <main class="pera-quote-page">
        <section class="pera-quote-header">
            <h1><?php echo esc_html($payload['building_title'] ?? get_bloginfo('name')); ?></h1>
            <p><strong><?php esc_html_e('Quote Reference:', 'pera-portal'); ?></strong> <?php echo esc_html((string) ($payload['reference'] ?? get_post_meta($quote->ID, '_pera_quote_reference', true))); ?></p>
            <div class="pera-quote-status status-<?php echo esc_attr($status); ?>"><?php echo esc_html($banner); ?></div>
        </section>

        <section class="pera-quote-section">
            <div class="pera-quote-price"><?php echo esc_html(number_format((float) ($payload['price'] ?? 0), 2) . ' ' . ($payload['currency'] ?? '')); ?></div>
            <p>
                <strong><?php esc_html_e('Issued:', 'pera-portal'); ?></strong> <?php echo esc_html((string) ($payload['issued_gmt'] ?? '')); ?> GMT<br>
                <strong><?php esc_html_e('Valid Until:', 'pera-portal'); ?></strong> <?php echo esc_html((string) ($payload['expires_gmt'] ?? '')); ?> GMT
            </p>
        </section>

        <section class="pera-quote-section pera-quote-grid">
            <p><strong><?php esc_html_e('Floor', 'pera-portal'); ?>:</strong> <?php echo esc_html((string) ($payload['floor_label'] ?? '-')); ?></p>
            <p><strong><?php esc_html_e('Unit Code', 'pera-portal'); ?>:</strong> <?php echo esc_html((string) ($payload['unit_code'] ?? '-')); ?></p>
            <p><strong><?php esc_html_e('Unit Type', 'pera-portal'); ?>:</strong> <?php echo esc_html((string) ($payload['unit_type'] ?? '-')); ?></p>
            <p><strong><?php esc_html_e('Net Size', 'pera-portal'); ?>:</strong> <?php echo esc_html((string) ($payload['net_size'] ?? '-')); ?></p>
            <p><strong><?php esc_html_e('Gross Size', 'pera-portal'); ?>:</strong> <?php echo esc_html((string) ($payload['gross_size'] ?? '-')); ?></p>
            <p><strong><?php esc_html_e('Issued By', 'pera-portal'); ?>:</strong> <?php echo esc_html((string) ($payload['issued_by'] ?? '-')); ?></p>
        </section>

        <section class="pera-quote-section pera-quote-plan">
            <h3><?php esc_html_e('Frozen Floor Plan', 'pera-portal'); ?></h3>
            <?php echo $floor_svg_renderable !== '' ? $floor_svg_renderable : '<p>' . esc_html__('No floor plan available.', 'pera-portal') . '</p>'; ?>
        </section>

        <section class="pera-quote-section pera-quote-plan">
            <h3><?php esc_html_e('Frozen Apartment Plan', 'pera-portal'); ?></h3>
            <?php if ($apartment_plan_url) : ?>
                <img src="<?php echo esc_url($apartment_plan_url); ?>" alt="<?php echo esc_attr__('Apartment plan snapshot', 'pera-portal'); ?>" />
            <?php else : ?>
                <p><?php esc_html_e('Apartment plan was unavailable at issue time.', 'pera-portal'); ?></p>
            <?php endif; ?>
        </section>

        <?php if (!empty($payload['consultant_note'])) : ?>
            <section class="pera-quote-section">
                <h3><?php esc_html_e('Consultant Note', 'pera-portal'); ?></h3>
                <p><?php echo nl2br(esc_html((string) $payload['consultant_note'])); ?></p>
            </section>
        <?php endif; ?>

        <section class="pera-quote-section">
            <p><strong><?php esc_html_e('Client', 'pera-portal'); ?>:</strong> <?php echo esc_html((string) ($payload['client_name'] ?? '-')); ?> <?php echo esc_html((string) ($payload['client_email'] ?? '')); ?> <?php echo esc_html((string) ($payload['client_phone'] ?? '')); ?></p>
            <p><?php echo esc_html((string) ($payload['disclaimer'] ?? '')); ?></p>
        </section>
    </main>
    <?php wp_footer(); ?>
</body>
</html>
