<?php

if (!defined('ABSPATH')) {
    exit;
}

function pera_portal_quote_sanitize_svg_markup($svg_markup)
{
    $svg_markup = (string) $svg_markup;

    if ($svg_markup === '' || stripos($svg_markup, '<svg') === false) {
        return '';
    }

    $svg_markup = preg_replace('#<script[^>]*>.*?</script>#is', '', $svg_markup);
    $svg_markup = preg_replace('#<foreignObject[^>]*>.*?</foreignObject>#is', '', $svg_markup);
    $svg_markup = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', (string) $svg_markup);

    $allowed_tags = [
        'svg' => ['xmlns' => true, 'viewBox' => true, 'width' => true, 'height' => true, 'class' => true, 'id' => true, 'style' => true, 'preserveAspectRatio' => true],
        'g' => ['id' => true, 'class' => true, 'transform' => true, 'style' => true],
        'path' => ['id' => true, 'class' => true, 'd' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'style' => true, 'transform' => true],
        'rect' => ['id' => true, 'class' => true, 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'style' => true, 'transform' => true],
        'circle' => ['id' => true, 'class' => true, 'cx' => true, 'cy' => true, 'r' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'style' => true, 'transform' => true],
        'ellipse' => ['id' => true, 'class' => true, 'cx' => true, 'cy' => true, 'rx' => true, 'ry' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'style' => true, 'transform' => true],
        'polygon' => ['id' => true, 'class' => true, 'points' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'style' => true, 'transform' => true],
        'polyline' => ['id' => true, 'class' => true, 'points' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'style' => true, 'transform' => true],
        'line' => ['id' => true, 'class' => true, 'x1' => true, 'x2' => true, 'y1' => true, 'y2' => true, 'stroke' => true, 'stroke-width' => true, 'style' => true, 'transform' => true],
        'text' => ['id' => true, 'class' => true, 'x' => true, 'y' => true, 'font-size' => true, 'fill' => true, 'transform' => true],
        'defs' => [],
        'style' => [],
        'title' => [],
        'desc' => [],
    ];

    return wp_kses($svg_markup, $allowed_tags);
}

function pera_portal_quote_copy_attachment($attachment_id, $prefix = 'quote-plan')
{
    $attachment_id = absint($attachment_id);
    if ($attachment_id <= 0) {
        return 0;
    }

    $source_file = get_attached_file($attachment_id);
    if (!is_string($source_file) || $source_file === '' || !file_exists($source_file)) {
        return 0;
    }

    $bits = wp_upload_bits($prefix . '-' . wp_generate_uuid4() . '-' . basename($source_file), null, file_get_contents($source_file));

    if (!empty($bits['error']) || empty($bits['file'])) {
        return 0;
    }

    $mime = wp_check_filetype($bits['file']);
    $new_attachment_id = wp_insert_attachment([
        'post_title' => sanitize_file_name(pathinfo($bits['file'], PATHINFO_FILENAME)),
        'post_status' => 'inherit',
        'post_mime_type' => $mime['type'] ?? 'application/octet-stream',
    ], $bits['file']);

    if (is_wp_error($new_attachment_id) || !$new_attachment_id) {
        return 0;
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    $metadata = wp_generate_attachment_metadata($new_attachment_id, $bits['file']);
    if (!is_wp_error($metadata) && is_array($metadata)) {
        wp_update_attachment_metadata($new_attachment_id, $metadata);
    }

    return (int) $new_attachment_id;
}
