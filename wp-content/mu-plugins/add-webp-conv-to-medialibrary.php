<?php
/**
 * MU Plugin: WebP swap + Media Library Convert actions (no external plugins)
 */
if ( ! defined('ABSPATH') ) exit;

/* ============================================================
   1) Front-end: serve .webp sibling if it exists
   ============================================================ */
add_filter('wp_get_attachment_image_src', function($image, $attachment_id, $size){

  if ( empty($image[0]) ) return $image;

  $url = $image[0];

  // Only attempt swap for jpg/jpeg/png
  if ( ! preg_match('/\.(jpe?g|png)$/i', $url) ) return $image;

  $uploads = wp_upload_dir();
  if ( empty($uploads['baseurl']) || empty($uploads['basedir']) ) return $image;

  $webp_url  = preg_replace('/\.(jpe?g|png)$/i', '.webp', $url);
  $webp_path = str_replace($uploads['baseurl'], $uploads['basedir'], $webp_url);

  if ( $webp_path && file_exists($webp_path) ) {
    $image[0] = $webp_url;
  }

  return $image;
}, 10, 3);


/* ============================================================
   Helpers: conversion
   ============================================================ */
function pera_webp_can_encode() : bool {
  // We can’t reliably pre-detect all server builds; we’ll “test” by attempting a save when asked.
  // This function is here if you later want to add stricter checks.
  return function_exists('wp_get_image_editor');
}

function pera_webp_convert_file($filepath, $quality = 82) : bool {
  $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
  if ( ! in_array($ext, ['jpg','jpeg','png'], true) ) return false;

  $webp_path = preg_replace('/\.(jpe?g|png)$/i', '.webp', $filepath);
  if ( ! $webp_path ) return false;

  // Already exists
  if ( file_exists($webp_path) ) return true;

  $editor = wp_get_image_editor($filepath);
  if ( is_wp_error($editor) ) return false;

  if ( method_exists($editor, 'set_quality') ) {
    $editor->set_quality($quality);
  }

  // Save as WebP
  $saved = $editor->save($webp_path, 'image/webp');
  if ( is_wp_error($saved) || empty($saved['path']) ) return false;

  return file_exists($webp_path);
}

function pera_webp_convert_attachment($attachment_id, $include_sizes = true, $quality = 82) : array {
  $log = [
    'ok'      => false,
    'message' => '',
    'details' => [],
  ];

  $file = get_attached_file($attachment_id);
  if ( ! $file || ! file_exists($file) ) {
    $log['message'] = 'Attachment file missing on disk.';
    return $log;
  }

  $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
  if ( ! in_array($ext, ['jpg','jpeg','png'], true) ) {
    $log['message'] = 'Only JPG/PNG can be converted.';
    return $log;
  }

  $ok_main = pera_webp_convert_file($file, $quality);
  $log['details'][] = $ok_main ? 'Converted original.' : 'Could not convert original (no WebP encoder or error).';

  if ( $include_sizes ) {
    $meta = wp_get_attachment_metadata($attachment_id);
    if ( is_array($meta) && ! empty($meta['sizes']) ) {
      $base_dir = trailingslashit(pathinfo($file, PATHINFO_DIRNAME));
      foreach ( $meta['sizes'] as $size_key => $info ) {
        if ( empty($info['file']) ) continue;
        $size_file = $base_dir . $info['file'];
        if ( file_exists($size_file) ) {
          $ok = pera_webp_convert_file($size_file, $quality);
          $log['details'][] = ($ok ? "OK size: {$size_key}" : "Skip size: {$size_key}");
        }
      }
    }
  }

  $log['ok'] = (bool) $ok_main; // success means at least original converted
  $log['message'] = implode(' ', $log['details']);
  return $log;
}


/* ============================================================
   2) Media Library row actions: "Convert to WebP"
   (This is what you circled in red.)
   ============================================================ */
add_filter('media_row_actions', function($actions, $post){

  if ( ! current_user_can('upload_files') ) return $actions;
  if ( empty($post->ID) ) return $actions;

  $mime = get_post_mime_type($post->ID);
  if ( ! in_array($mime, ['image/jpeg','image/png'], true) ) return $actions;

  $base_url = admin_url('upload.php');
  $nonce    = wp_create_nonce('pera_webp_convert_' . $post->ID);

  $url1 = add_query_arg([
    'pera_webp_convert' => 1,
    'id'                => $post->ID,
    'sizes'             => 0,
    '_wpnonce'          => $nonce,
  ], $base_url);

  $url2 = add_query_arg([
    'pera_webp_convert' => 1,
    'id'                => $post->ID,
    'sizes'             => 1,
    '_wpnonce'          => $nonce,
  ], $base_url);

  // Insert near "Copy URL" / "Download file" region
  $actions['pera_webp_convert'] = '<a href="' . esc_url($url1) . '">Convert to WebP</a>';
  $actions['pera_webp_convert_sizes'] = '<a href="' . esc_url($url2) . '">Convert to WebP (incl. sizes)</a>';

  return $actions;

}, 10, 2);


/* ============================================================
   3) Handle conversion request (admin redirect back with notice)
   ============================================================ */
add_action('admin_init', function(){

  if ( ! is_admin() ) return;
  if ( ! isset($_GET['pera_webp_convert'], $_GET['id']) ) return;

  if ( ! current_user_can('upload_files') ) {
    wp_die('You do not have permission to convert images.');
  }

  $attachment_id = (int) $_GET['id'];
  if ( $attachment_id <= 0 ) {
    wp_die('Invalid attachment ID.');
  }

  $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field($_GET['_wpnonce']) : '';
  if ( ! wp_verify_nonce($nonce, 'pera_webp_convert_' . $attachment_id) ) {
    wp_die('Nonce check failed.');
  }

  $sizes = ! empty($_GET['sizes']);
  $result = pera_webp_convert_attachment($attachment_id, $sizes, 82);

  // Store a transient to show notice on next page load
  set_transient('pera_webp_notice_' . get_current_user_id(), [
    'ok' => $result['ok'],
    'msg' => $result['message'],
  ], 60);

  // Redirect back to Media Library without action params
  $redirect = remove_query_arg(['pera_webp_convert','id','sizes','_wpnonce']);
  wp_safe_redirect($redirect);
  exit;
});


/* ============================================================
   4) Show admin notice after conversion
   ============================================================ */
add_action('admin_notices', function(){

  if ( ! is_admin() ) return;

  $data = get_transient('pera_webp_notice_' . get_current_user_id());
  if ( ! $data ) return;

  delete_transient('pera_webp_notice_' . get_current_user_id());

  $class = ! empty($data['ok']) ? 'notice notice-success' : 'notice notice-error';
  echo '<div class="' . esc_attr($class) . '"><p><strong>WebP:</strong> ' . esc_html($data['msg']) . '</p></div>';
});


/* ============================================================
   5) Optional: Bulk Action in Media Library
   ============================================================ */
add_filter('bulk_actions-upload', function($bulk_actions){
  if ( current_user_can('upload_files') ) {
    $bulk_actions['pera_webp_bulk_sizes'] = 'Convert to WebP (incl. sizes)';
  }
  return $bulk_actions;
});

add_filter('handle_bulk_actions-upload', function($redirect_url, $action, $post_ids){

  if ( $action !== 'pera_webp_bulk_sizes' ) return $redirect_url;
  if ( ! current_user_can('upload_files') ) return $redirect_url;

  $ok = 0; $fail = 0;

  foreach ($post_ids as $id) {
    $mime = get_post_mime_type($id);
    if ( ! in_array($mime, ['image/jpeg','image/png'], true) ) continue;

    $r = pera_webp_convert_attachment((int)$id, true, 82);
    if ( ! empty($r['ok']) ) $ok++; else $fail++;
  }

  return add_query_arg([
    'pera_webp_bulk_done' => $ok,
    'pera_webp_bulk_fail' => $fail,
  ], $redirect_url);

}, 10, 3);

add_action('admin_notices', function(){
  if ( ! isset($_GET['pera_webp_bulk_done'], $_GET['pera_webp_bulk_fail']) ) return;

  $ok = (int) $_GET['pera_webp_bulk_done'];
  $fail = (int) $_GET['pera_webp_bulk_fail'];

  echo '<div class="notice notice-info"><p><strong>WebP bulk conversion:</strong> ' .
       esc_html("Converted: {$ok}. Failed/Skipped: {$fail}.") .
       '</p></div>';
});
