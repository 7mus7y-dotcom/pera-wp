<?php

if (!defined('ABSPATH')) {
    exit;
}

function pera_portal_quote_generate_token()
{
    for ($i = 0; $i < 5; $i++) {
        $raw = random_bytes(32);
        $token = rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');

        if (!pera_portal_quote_find_by_token($token)) {
            return $token;
        }
    }

    return new WP_Error('pera_portal_quote_token_generation_failed', __('Could not generate unique quote token.', 'pera-portal'));
}

function pera_portal_quote_generate_reference()
{
    $prefix = 'PQ-' . gmdate('Ymd') . '-';
    $query = new WP_Query([
        'post_type' => 'pera_quote',
        'post_status' => 'any',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_key' => '_pera_quote_reference',
        'meta_value' => $prefix,
        'meta_compare' => 'LIKE',
    ]);

    $sequence = count($query->posts) + 1;

    return $prefix . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
}
