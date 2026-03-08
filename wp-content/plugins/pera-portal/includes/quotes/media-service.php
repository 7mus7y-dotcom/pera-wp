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
    $svg_markup = preg_replace('/\s(?:href|xlink:href)\s*=\s*("\s*javascript:[^"]*"|\'\s*javascript:[^\']*\'|\s*javascript:[^\s>]+)/i', '', (string) $svg_markup);

    $allowed_tags = [
        'svg' => ['xmlns' => true, 'xmlns:xlink' => true, 'viewbox' => true, 'width' => true, 'height' => true, 'class' => true, 'id' => true, 'style' => true, 'preserveaspectratio' => true, 'x' => true, 'y' => true],
        'g' => ['id' => true, 'class' => true, 'transform' => true, 'style' => true, 'clip-path' => true, 'mask' => true, 'opacity' => true, 'fill-opacity' => true, 'stroke-opacity' => true],
        'path' => ['id' => true, 'class' => true, 'd' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'style' => true, 'transform' => true, 'clip-path' => true, 'mask' => true, 'fill-rule' => true, 'clip-rule' => true, 'opacity' => true, 'fill-opacity' => true, 'stroke-opacity' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true, 'stroke-miterlimit' => true],
        'rect' => ['id' => true, 'class' => true, 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'style' => true, 'transform' => true, 'clip-path' => true, 'mask' => true, 'fill-rule' => true, 'clip-rule' => true, 'opacity' => true, 'fill-opacity' => true, 'stroke-opacity' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true, 'stroke-miterlimit' => true],
        'circle' => ['id' => true, 'class' => true, 'cx' => true, 'cy' => true, 'r' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'style' => true, 'transform' => true, 'clip-path' => true, 'mask' => true, 'fill-rule' => true, 'clip-rule' => true, 'opacity' => true, 'fill-opacity' => true, 'stroke-opacity' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true, 'stroke-miterlimit' => true],
        'ellipse' => ['id' => true, 'class' => true, 'cx' => true, 'cy' => true, 'rx' => true, 'ry' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'style' => true, 'transform' => true, 'clip-path' => true, 'mask' => true, 'fill-rule' => true, 'clip-rule' => true, 'opacity' => true, 'fill-opacity' => true, 'stroke-opacity' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true, 'stroke-miterlimit' => true],
        'polygon' => ['id' => true, 'class' => true, 'points' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'style' => true, 'transform' => true, 'clip-path' => true, 'mask' => true, 'fill-rule' => true, 'clip-rule' => true, 'opacity' => true, 'fill-opacity' => true, 'stroke-opacity' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true, 'stroke-miterlimit' => true],
        'polyline' => ['id' => true, 'class' => true, 'points' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'style' => true, 'transform' => true, 'clip-path' => true, 'mask' => true, 'fill-rule' => true, 'clip-rule' => true, 'opacity' => true, 'fill-opacity' => true, 'stroke-opacity' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true, 'stroke-miterlimit' => true],
        'line' => ['id' => true, 'class' => true, 'x1' => true, 'x2' => true, 'y1' => true, 'y2' => true, 'stroke' => true, 'stroke-width' => true, 'style' => true, 'transform' => true, 'clip-path' => true, 'mask' => true, 'opacity' => true, 'stroke-opacity' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true, 'stroke-miterlimit' => true],
        'text' => ['id' => true, 'class' => true, 'x' => true, 'y' => true, 'dx' => true, 'dy' => true, 'font-size' => true, 'fill' => true, 'transform' => true, 'clip-path' => true, 'mask' => true, 'fill-rule' => true, 'clip-rule' => true, 'opacity' => true, 'fill-opacity' => true, 'stroke-opacity' => true],
        'tspan' => ['id' => true, 'class' => true, 'x' => true, 'y' => true, 'dx' => true, 'dy' => true, 'style' => true, 'fill' => true, 'font-size' => true],
        'defs' => [],
        'clippath' => ['id' => true, 'class' => true, 'clippathunits' => true, 'transform' => true],
        'mask' => ['id' => true, 'class' => true, 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'maskunits' => true, 'maskcontentunits' => true],
        'lineargradient' => ['id' => true, 'class' => true, 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true, 'gradientunits' => true, 'gradienttransform' => true, 'spreadmethod' => true, 'href' => true, 'xlink:href' => true],
        'radialgradient' => ['id' => true, 'class' => true, 'cx' => true, 'cy' => true, 'r' => true, 'fx' => true, 'fy' => true, 'gradientunits' => true, 'gradienttransform' => true, 'spreadmethod' => true, 'href' => true, 'xlink:href' => true],
        'stop' => ['id' => true, 'class' => true, 'offset' => true, 'stop-color' => true, 'stop-opacity' => true, 'style' => true],
        'symbol' => ['id' => true, 'class' => true, 'viewbox' => true, 'preserveaspectratio' => true, 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'style' => true],
        'use' => ['id' => true, 'class' => true, 'href' => true, 'xlink:href' => true, 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'transform' => true, 'style' => true],
        'pattern' => ['id' => true, 'class' => true, 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'patternunits' => true, 'patterncontentunits' => true, 'patterntransform' => true, 'viewbox' => true, 'preserveaspectratio' => true],
        'image' => ['id' => true, 'class' => true, 'href' => true, 'xlink:href' => true, 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'preserveaspectratio' => true, 'transform' => true, 'style' => true],
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
