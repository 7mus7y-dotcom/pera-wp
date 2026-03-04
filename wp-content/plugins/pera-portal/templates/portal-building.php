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

$building_id = absint(get_query_var('pera_building_id'));
$building = $building_id > 0 ? get_post($building_id) : null;

if (!($building instanceof WP_Post) || $building->post_type !== 'pera_building') {
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

$floor_id = 0;

$query = new WP_Query([
    'post_type' => 'pera_floor',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'orderby' => 'title',
    'order' => 'ASC',
    'meta_query' => [
        [
            'key' => 'building',
            'value' => (string) $building_id,
            'compare' => '=',
        ],
    ],
]);

$floors = [];
foreach ($query->posts as $floor_post) {
    $candidate_id = (int) $floor_post->ID;
    $floor_number = function_exists('get_field') ? get_field('floor_number', $candidate_id) : get_post_meta($candidate_id, 'floor_number', true);
    $floors[] = [
        'id' => $candidate_id,
        'title' => (string) get_the_title($candidate_id),
        'floor_number' => $floor_number === null ? '' : (string) $floor_number,
    ];
}

usort($floors, static function ($a, $b) {
    $a_number = trim((string) ($a['floor_number'] ?? ''));
    $b_number = trim((string) ($b['floor_number'] ?? ''));
    $a_numeric = $a_number !== '' && is_numeric($a_number);
    $b_numeric = $b_number !== '' && is_numeric($b_number);

    if ($a_numeric && $b_numeric) {
        return (float) $a_number <=> (float) $b_number;
    }

    if ($a_numeric) {
        return -1;
    }

    if ($b_numeric) {
        return 1;
    }

    return strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
});

if (!empty($floors[0]['id'])) {
    $floor_id = (int) $floors[0]['id'];
}

if (function_exists('pera_portal_mark_assets_needed')) {
    pera_portal_mark_assets_needed();
}

$GLOBALS['pera_portal_enqueue_assets'] = true;

if (function_exists('pera_portal_set_script_config')) {
    pera_portal_set_script_config([
        'rest_url' => esc_url_raw(rest_url(PERA_PORTAL_REST_NAMESPACE . '/')),
        'nonce' => wp_create_nonce('wp_rest'),
        'building_id' => $building_id,
        'floor_id' => $floor_id,
        'mode' => 'external',
    ]);
}

get_header();
?>
<main id="content" class="site-main pera-portal-building">
    <?php
    $template_path = PERA_PORTAL_PATH . '/templates/portal-shell.php';
    if (file_exists($template_path)) {
        require $template_path;
    }
    ?>
</main>
<?php
get_footer();
