# Amalgamated Diff — Phase 1 MVP: Tokenized Client Quote Links

This file contains the complete unified diff from commit `a16c7bc`.

```diff
diff --git a/wp-content/plugins/pera-portal/assets/dist/portal-viewer.css b/wp-content/plugins/pera-portal/assets/dist/portal-viewer.css
index 3011de6..78a7784 100644
--- a/wp-content/plugins/pera-portal/assets/dist/portal-viewer.css
+++ b/wp-content/plugins/pera-portal/assets/dist/portal-viewer.css
@@ -697,3 +697,35 @@ body.is-dark.pera-portal-page #pera-portal-root .pera-portal-tooltip {
         background: #fff !important;
     }
 }
+
+.pera-portal-quote-tools {
+    margin-top: 16px;
+    border-top: 1px solid var(--portal-border);
+    padding-top: 12px;
+}
+
+.pera-portal-quote-form {
+    display: grid;
+    gap: 8px;
+}
+
+.pera-portal-quote-form label {
+    display: grid;
+    gap: 4px;
+    font-size: 13px;
+    color: var(--portal-text-muted);
+}
+
+.pera-portal-quote-form input,
+.pera-portal-quote-form textarea {
+    border: 1px solid var(--portal-border);
+    border-radius: 4px;
+    padding: 6px 8px;
+    background: var(--portal-surface-2);
+    color: var(--portal-text);
+}
+
+.pera-portal-quote-result {
+    margin-top: 10px;
+    font-size: 13px;
+}
diff --git a/wp-content/plugins/pera-portal/assets/dist/portal-viewer.js b/wp-content/plugins/pera-portal/assets/dist/portal-viewer.js
index b8d6d56..c513e4f 100644
--- a/wp-content/plugins/pera-portal/assets/dist/portal-viewer.js
+++ b/wp-content/plugins/pera-portal/assets/dist/portal-viewer.js
@@ -31,6 +31,7 @@
     const summaryPpsEl = root.querySelector('[data-summary-pps]');
     const summarySizeEl = root.querySelector('[data-summary-size]');
     const summaryCountEl = root.querySelector('[data-summary-count]');
+    const quoteToolsContainer = root.querySelector('[data-quote-tools]');
     const colorModeButtons = root.querySelectorAll('[data-color-mode]');
     const restBase = typeof config.rest_url === 'string' ? config.rest_url : '';
     const headers = {
@@ -536,6 +537,114 @@
         countsContainer.textContent = 'Total: ' + unitsData.length + ' (Visible: ' + visibleTotal + ') | Available: ' + totals.available + ' | Reserved: ' + totals.reserved + ' | Sold: ' + totals.sold;
     }
 
+    async function createQuote(payload) {
+        const response = await fetch(restBase + 'quotes', {
+            method: 'POST',
+            headers: Object.assign({'Content-Type': 'application/json'}, headers),
+            credentials: 'same-origin',
+            body: JSON.stringify(payload),
+        });
+
+        const data = await response.json();
+
+        if (!response.ok) {
+            throw new Error(data && data.message ? data.message : 'Unable to create quote.');
+        }
+
+        return data;
+    }
+
+    function renderQuoteTools(unit) {
+        if (!quoteToolsContainer) {
+            return;
+        }
+
+        if (!unit || !unit.id) {
+            quoteToolsContainer.hidden = true;
+            quoteToolsContainer.textContent = '';
+            return;
+        }
+
+        quoteToolsContainer.hidden = false;
+        quoteToolsContainer.innerHTML = '';
+
+        const wrap = document.createElement('div');
+        wrap.className = 'pera-portal-quote-box';
+
+        const heading = document.createElement('h4');
+        heading.textContent = 'Create Client Quote';
+        wrap.appendChild(heading);
+
+        const form = document.createElement('form');
+        form.className = 'pera-portal-quote-form';
+        form.innerHTML = ''
+            + '<label>Quoted Price <input name="quoted_price" type="number" step="0.01" required></label>'
+            + '<label>Currency <input name="currency" type="text" required></label>'
+            + '<label>Expiry <input name="expires_at" type="datetime-local" required></label>'
+            + '<label>Consultant Note <textarea name="consultant_note" rows="2"></textarea></label>'
+            + '<label>Client Name <input name="client_name" type="text"></label>'
+            + '<label>Client Email <input name="client_email" type="email"></label>'
+            + '<label>Client Phone <input name="client_phone" type="text"></label>'
+            + '<button type="submit" class="button-like">Create Client Quote</button>';
+
+        const priceInput = form.querySelector('input[name="quoted_price"]');
+        const currencyInput = form.querySelector('input[name="currency"]');
+        if (priceInput && unit.price != null) {
+            priceInput.value = String(unit.price);
+        }
+        if (currencyInput && unit.currency) {
+            currencyInput.value = String(unit.currency);
+        }
+
+        const result = document.createElement('div');
+        result.className = 'pera-portal-quote-result';
+
+        form.addEventListener('submit', async function (event) {
+            event.preventDefault();
+            result.textContent = 'Creating quote…';
+
+            const fd = new FormData(form);
+            const payload = {
+                unit_id: unit.id,
+                quoted_price: Number(fd.get('quoted_price') || 0),
+                currency: String(fd.get('currency') || ''),
+                expires_at: String(fd.get('expires_at') || ''),
+                consultant_note: String(fd.get('consultant_note') || ''),
+                client_name: String(fd.get('client_name') || ''),
+                client_email: String(fd.get('client_email') || ''),
+                client_phone: String(fd.get('client_phone') || ''),
+                source_context: 'portal',
+            };
+
+            try {
+                const created = await createQuote(payload);
+                result.innerHTML = ''
+                    + '<p><strong>Reference:</strong> ' + safeText(created.quote_reference || '') + '</p>'
+                    + '<p><a href="' + safeText(created.public_url || '#') + '" target="_blank" rel="noopener noreferrer">Open Quote</a></p>'
+                    + '<p><button type="button" data-copy-quote="1">Copy Link</button></p>'
+                    + (created.warning ? '<p>' + safeText(created.warning) + '</p>' : '');
+
+                const copyBtn = result.querySelector('[data-copy-quote="1"]');
+                if (copyBtn) {
+                    copyBtn.addEventListener('click', async function () {
+                        try {
+                            await navigator.clipboard.writeText(String(created.public_url || ''));
+                            copyBtn.textContent = 'Copied';
+                        } catch (error) {
+                            copyBtn.textContent = 'Copy failed';
+                        }
+                    });
+                }
+            } catch (error) {
+                result.textContent = String(error && error.message ? error.message : 'Unable to create quote.');
+            }
+        });
+
+        wrap.appendChild(form);
+        wrap.appendChild(result);
+        quoteToolsContainer.appendChild(wrap);
+    }
+
     function renderDetails(unit) {
         if (!detailsContainer) {
             return;
@@ -607,6 +716,8 @@
         } else {
             detailsContainer.appendChild(planWrap);
         }
+
+        renderQuoteTools(unit);
     }
 
     function setMessage(target, message) {
@@ -650,6 +761,8 @@
         if (message) {
             setMessage(detailsContainer, message);
         }
+
+        renderQuoteTools(null);
     }
 
     function applyFilters() {
diff --git a/wp-content/plugins/pera-portal/includes/bootstrap.php b/wp-content/plugins/pera-portal/includes/bootstrap.php
index 0b989c3..6ace2d3 100644
--- a/wp-content/plugins/pera-portal/includes/bootstrap.php
+++ b/wp-content/plugins/pera-portal/includes/bootstrap.php
@@ -19,6 +19,12 @@ $pera_portal_bootstrap_files = [
     PERA_PORTAL_PATH . '/includes/cpt/building.php',
     PERA_PORTAL_PATH . '/includes/cpt/floor.php',
     PERA_PORTAL_PATH . '/includes/cpt/unit.php',
+    PERA_PORTAL_PATH . '/includes/cpt/quote.php',
+    PERA_PORTAL_PATH . '/includes/quotes/repository.php',
+    PERA_PORTAL_PATH . '/includes/quotes/token-service.php',
+    PERA_PORTAL_PATH . '/includes/quotes/media-service.php',
+    PERA_PORTAL_PATH . '/includes/quotes/snapshot-service.php',
+    PERA_PORTAL_PATH . '/includes/rest/quote-routes.php',
     PERA_PORTAL_PATH . '/includes/rest/routes.php',
     PERA_PORTAL_PATH . '/includes/assets/enqueue.php',
     PERA_PORTAL_PATH . '/includes/shortcodes/portal-shortcode.php',
diff --git a/wp-content/plugins/pera-portal/includes/capabilities.php b/wp-content/plugins/pera-portal/includes/capabilities.php
index 044caa7..07b1c3b 100644
--- a/wp-content/plugins/pera-portal/includes/capabilities.php
+++ b/wp-content/plugins/pera-portal/includes/capabilities.php
@@ -87,3 +87,36 @@ if (!function_exists('pera_portal_current_user_can_access')) {
             : current_user_can('manage_options');
     }
 }
+
+if (!function_exists('pera_portal_current_user_can_create_quotes')) {
+    function pera_portal_current_user_can_create_quotes()
+    {
+        if (!pera_portal_current_user_can_access()) {
+            return false;
+        }
+
+        return current_user_can('manage_options') || current_user_can('pera_portal_create_quotes') || pera_portal_current_user_can_access();
+    }
+}
+
+if (!function_exists('pera_portal_current_user_can_manage_quotes')) {
+    function pera_portal_current_user_can_manage_quotes()
+    {
+        if (!pera_portal_current_user_can_access()) {
+            return false;
+        }
+
+        return current_user_can('manage_options') || current_user_can('pera_portal_manage_quotes') || pera_portal_current_user_can_access();
+    }
+}
+
+if (!function_exists('pera_portal_current_user_can_revoke_quotes')) {
+    function pera_portal_current_user_can_revoke_quotes()
+    {
+        if (!pera_portal_current_user_can_access()) {
+            return false;
+        }
+
+        return current_user_can('manage_options') || current_user_can('pera_portal_revoke_quotes') || pera_portal_current_user_can_access();
+    }
+}
diff --git a/wp-content/plugins/pera-portal/includes/cpt/quote.php b/wp-content/plugins/pera-portal/includes/cpt/quote.php
new file mode 100644
index 0000000..5cd3622
--- /dev/null
+++ b/wp-content/plugins/pera-portal/includes/cpt/quote.php
@@ -0,0 +1,32 @@
+<?php
+
+if (!defined('ABSPATH')) {
+    exit;
+}
+
+function pera_portal_register_cpt_quote()
+{
+    $labels = [
+        'name' => __('Client Quotes', 'pera-portal'),
+        'singular_name' => __('Client Quote', 'pera-portal'),
+        'add_new_item' => __('Add New Client Quote', 'pera-portal'),
+        'edit_item' => __('Edit Client Quote', 'pera-portal'),
+        'new_item' => __('New Client Quote', 'pera-portal'),
+        'view_item' => __('View Client Quote', 'pera-portal'),
+        'search_items' => __('Search Client Quotes', 'pera-portal'),
+        'not_found' => __('No client quotes found', 'pera-portal'),
+        'menu_name' => __('Client Quotes', 'pera-portal'),
+    ];
+
+    register_post_type('pera_quote', [
+        'labels' => $labels,
+        'public' => false,
+        'show_ui' => true,
+        'show_in_rest' => false,
+        'supports' => ['title'],
+        'menu_icon' => 'dashicons-media-spreadsheet',
+        'capability_type' => 'post',
+    ]);
+}
+
+add_action('init', 'pera_portal_register_cpt_quote', 16);
diff --git a/wp-content/plugins/pera-portal/includes/quotes/media-service.php b/wp-content/plugins/pera-portal/includes/quotes/media-service.php
new file mode 100644
index 0000000..762f4e3
--- /dev/null
+++ b/wp-content/plugins/pera-portal/includes/quotes/media-service.php
@@ -0,0 +1,75 @@
+<?php
+
+if (!defined('ABSPATH')) {
+    exit;
+}
+
+function pera_portal_quote_sanitize_svg_markup($svg_markup)
+{
+    $svg_markup = (string) $svg_markup;
+
+    if ($svg_markup === '' || stripos($svg_markup, '<svg') === false) {
+        return '';
+    }
+
+    $svg_markup = preg_replace('#<script[^>]*>.*?</script>#is', '', $svg_markup);
+    $svg_markup = preg_replace('#<foreignObject[^>]*>.*?</foreignObject>#is', '', $svg_markup);
+    $svg_markup = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', (string) $svg_markup);
+
+    $allowed_tags = [
+        'svg' => ['xmlns' => true, 'viewBox' => true, 'width' => true, 'height' => true, 'class' => true, 'id' => true, 'style' => true, 'preserveAspectRatio' => true],
+        'g' => ['id' => true, 'class' => true, 'transform' => true, 'style' => true],
+        'path' => ['id' => true, 'class' => true, 'd' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'style' => true, 'transform' => true],
+        'rect' => ['id' => true, 'class' => true, 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'style' => true, 'transform' => true],
+        'circle' => ['id' => true, 'class' => true, 'cx' => true, 'cy' => true, 'r' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'style' => true, 'transform' => true],
+        'ellipse' => ['id' => true, 'class' => true, 'cx' => true, 'cy' => true, 'rx' => true, 'ry' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'style' => true, 'transform' => true],
+        'polygon' => ['id' => true, 'class' => true, 'points' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'style' => true, 'transform' => true],
+        'polyline' => ['id' => true, 'class' => true, 'points' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'style' => true, 'transform' => true],
+        'line' => ['id' => true, 'class' => true, 'x1' => true, 'x2' => true, 'y1' => true, 'y2' => true, 'stroke' => true, 'stroke-width' => true, 'style' => true, 'transform' => true],
+        'text' => ['id' => true, 'class' => true, 'x' => true, 'y' => true, 'font-size' => true, 'fill' => true, 'transform' => true],
+        'defs' => [],
+        'style' => [],
+        'title' => [],
+        'desc' => [],
+    ];
+
+    return wp_kses($svg_markup, $allowed_tags);
+}
+
+function pera_portal_quote_copy_attachment($attachment_id, $prefix = 'quote-plan')
+{
+    $attachment_id = absint($attachment_id);
+    if ($attachment_id <= 0) {
+        return 0;
+    }
+
+    $source_file = get_attached_file($attachment_id);
+    if (!is_string($source_file) || $source_file === '' || !file_exists($source_file)) {
+        return 0;
+    }
+
+    $bits = wp_upload_bits($prefix . '-' . wp_generate_uuid4() . '-' . basename($source_file), null, file_get_contents($source_file));
+
+    if (!empty($bits['error']) || empty($bits['file'])) {
+        return 0;
+    }
+
+    $mime = wp_check_filetype($bits['file']);
+    $new_attachment_id = wp_insert_attachment([
+        'post_title' => sanitize_file_name(pathinfo($bits['file'], PATHINFO_FILENAME)),
+        'post_status' => 'inherit',
+        'post_mime_type' => $mime['type'] ?? 'application/octet-stream',
+    ], $bits['file']);
+
+    if (is_wp_error($new_attachment_id) || !$new_attachment_id) {
+        return 0;
+    }
+
+    require_once ABSPATH . 'wp-admin/includes/image.php';
+    $metadata = wp_generate_attachment_metadata($new_attachment_id, $bits['file']);
+    if (!is_wp_error($metadata) && is_array($metadata)) {
+        wp_update_attachment_metadata($new_attachment_id, $metadata);
+    }
+
+    return (int) $new_attachment_id;
+}
diff --git a/wp-content/plugins/pera-portal/includes/quotes/repository.php b/wp-content/plugins/pera-portal/includes/quotes/repository.php
new file mode 100644
index 0000000..04bd70a
--- /dev/null
+++ b/wp-content/plugins/pera-portal/includes/quotes/repository.php
@@ -0,0 +1,107 @@
+<?php
+
+if (!defined('ABSPATH')) {
+    exit;
+}
+
+function pera_portal_quote_find_by_token($token)
+{
+    $token = sanitize_text_field((string) $token);
+    if ($token === '') {
+        return null;
+    }
+
+    $query = new WP_Query([
+        'post_type' => 'pera_quote',
+        'post_status' => ['publish', 'private'],
+        'posts_per_page' => 1,
+        'fields' => 'ids',
+        'meta_query' => [
+            [
+                'key' => '_pera_quote_token',
+                'value' => $token,
+                'compare' => '=',
+            ],
+        ],
+    ]);
+
+    if (empty($query->posts[0])) {
+        return null;
+    }
+
+    return get_post((int) $query->posts[0]);
+}
+
+function pera_portal_quote_get_business_status($quote_id)
+{
+    $status = sanitize_key((string) get_post_meta($quote_id, '_pera_quote_status', true));
+
+    if ($status === 'revoked') {
+        return 'revoked';
+    }
+
+    $expires_gmt = (string) get_post_meta($quote_id, '_pera_quote_expires_gmt', true);
+    if ($expires_gmt !== '') {
+        $expires_ts = strtotime($expires_gmt . ' GMT');
+        if ($expires_ts !== false && $expires_ts < time()) {
+            return 'expired';
+        }
+    }
+
+    return $status === 'active' ? 'active' : 'active';
+}
+
+function pera_portal_quote_get_public_url($token)
+{
+    return home_url('/portal/quote/' . rawurlencode((string) $token) . '/');
+}
+
+function pera_portal_quote_save(array $record)
+{
+    $reference = isset($record['reference']) ? sanitize_text_field((string) $record['reference']) : pera_portal_quote_generate_reference();
+
+    $post_id = wp_insert_post([
+        'post_type' => 'pera_quote',
+        'post_status' => 'publish',
+        'post_title' => $reference,
+    ], true);
+
+    if (is_wp_error($post_id)) {
+        return $post_id;
+    }
+
+    $meta = [
+        '_pera_quote_token' => sanitize_text_field((string) ($record['token'] ?? '')),
+        '_pera_quote_reference' => $reference,
+        '_pera_quote_status' => sanitize_key((string) ($record['status'] ?? 'active')),
+        '_pera_quote_created_gmt' => sanitize_text_field((string) ($record['created_gmt'] ?? gmdate('Y-m-d H:i:s'))),
+        '_pera_quote_issued_gmt' => sanitize_text_field((string) ($record['issued_gmt'] ?? gmdate('Y-m-d H:i:s'))),
+        '_pera_quote_expires_gmt' => sanitize_text_field((string) ($record['expires_gmt'] ?? '')),
+        '_pera_quote_revoked_gmt' => sanitize_text_field((string) ($record['revoked_gmt'] ?? '')),
+        '_pera_quote_issued_by_user_id' => absint($record['issued_by_user_id'] ?? 0),
+        '_pera_quote_issued_by_name' => sanitize_text_field((string) ($record['issued_by_name'] ?? '')),
+        '_pera_quote_source_building_id' => absint($record['source_building_id'] ?? 0),
+        '_pera_quote_source_floor_id' => absint($record['source_floor_id'] ?? 0),
+        '_pera_quote_source_unit_id' => absint($record['source_unit_id'] ?? 0),
+        '_pera_quote_crm_client_id' => sanitize_text_field((string) ($record['crm_client_id'] ?? '')),
+        '_pera_quote_crm_deal_id' => sanitize_text_field((string) ($record['crm_deal_id'] ?? '')),
+        '_pera_quote_client_name' => sanitize_text_field((string) ($record['client_name'] ?? '')),
+        '_pera_quote_client_email' => sanitize_email((string) ($record['client_email'] ?? '')),
+        '_pera_quote_client_phone' => sanitize_text_field((string) ($record['client_phone'] ?? '')),
+        '_pera_quote_crm_note' => sanitize_textarea_field((string) ($record['crm_note'] ?? '')),
+        '_pera_quote_source_context' => sanitize_text_field((string) ($record['source_context'] ?? 'portal')),
+        '_pera_quote_source_channel' => sanitize_text_field((string) ($record['source_channel'] ?? '')),
+        '_pera_quote_payload_version' => 1,
+        '_pera_quote_payload_v1' => wp_json_encode($record['payload'] ?? []),
+        '_pera_quote_floor_plan_mode' => sanitize_key((string) ($record['floor_plan_mode'] ?? 'svg_markup')),
+        '_pera_quote_floor_plan_svg' => (string) ($record['floor_plan_svg'] ?? ''),
+        '_pera_quote_floor_plan_attachment_id' => absint($record['floor_plan_attachment_id'] ?? 0),
+        '_pera_quote_apartment_plan_attachment_id' => absint($record['apartment_plan_attachment_id'] ?? 0),
+    ];
+
+    foreach ($meta as $key => $value) {
+        update_post_meta($post_id, $key, $value);
+    }
+
+    return get_post($post_id);
+}
diff --git a/wp-content/plugins/pera-portal/includes/quotes/snapshot-service.php b/wp-content/plugins/pera-portal/includes/quotes/snapshot-service.php
new file mode 100644
index 0000000..e9d440d
--- /dev/null
+++ b/wp-content/plugins/pera-portal/includes/quotes/snapshot-service.php
@@ -0,0 +1,121 @@
+<?php
+
+if (!defined('ABSPATH')) {
+    exit;
+}
+
+function pera_portal_quote_get_field($field, $post_id)
+{
+    return function_exists('get_field') ? get_field($field, $post_id) : get_post_meta($post_id, $field, true);
+}
+
+function pera_portal_quote_resolve_floor_svg_markup($floor_id)
+{
+    $file = pera_portal_quote_get_field('floor_svg', $floor_id);
+    $svg_path = '';
+
+    if (is_array($file) && !empty($file['ID'])) {
+        $svg_path = (string) get_attached_file((int) $file['ID']);
+    } elseif (is_numeric($file)) {
+        $svg_path = (string) get_attached_file((int) $file);
+    }
+
+    if ($svg_path === '' || !file_exists($svg_path)) {
+        return new WP_Error('pera_portal_quote_floor_svg_missing', __('Floor SVG is required to create a quote.', 'pera-portal'));
+    }
+
+    $svg_markup = (string) file_get_contents($svg_path);
+    $sanitized = pera_portal_quote_sanitize_svg_markup($svg_markup);
+
+    if ($sanitized === '') {
+        return new WP_Error('pera_portal_quote_floor_svg_invalid', __('Floor SVG could not be sanitized for quote snapshot.', 'pera-portal'));
+    }
+
+    return $sanitized;
+}
+
+function pera_portal_quote_build_snapshot($unit_id, array $request)
+{
+    $unit = get_post($unit_id);
+    if (!($unit instanceof WP_Post) || $unit->post_type !== 'pera_unit') {
+        return new WP_Error('pera_portal_quote_invalid_unit', __('Invalid unit selected.', 'pera-portal'), ['status' => 400]);
+    }
+
+    $floor_id = absint(pera_portal_quote_get_field('floor', $unit_id));
+    $floor = $floor_id > 0 ? get_post($floor_id) : null;
+
+    if (!($floor instanceof WP_Post) || $floor->post_type !== 'pera_floor') {
+        return new WP_Error('pera_portal_quote_invalid_floor', __('Selected unit is missing a floor relation.', 'pera-portal'), ['status' => 400]);
+    }
+
+    $building_id = absint(pera_portal_quote_get_field('building', $floor_id));
+    $building = $building_id > 0 ? get_post($building_id) : null;
+
+    if (!($building instanceof WP_Post) || $building->post_type !== 'pera_building') {
+        return new WP_Error('pera_portal_quote_invalid_building', __('Selected unit floor is missing building relation.', 'pera-portal'), ['status' => 400]);
+    }
+
+    $now_gmt = gmdate('Y-m-d H:i:s');
+    $expires_gmt = get_gmt_from_date((string) ($request['expires_at'] ?? ''));
+
+    if ($expires_gmt === '' || strtotime($expires_gmt . ' GMT') <= time()) {
+        return new WP_Error('pera_portal_quote_invalid_expiry', __('Expiry date/time must be in the future.', 'pera-portal'), ['status' => 400]);
+    }
+
+    $price = is_numeric($request['quoted_price'] ?? null) ? (float) $request['quoted_price'] : null;
+    if ($price === null || $price <= 0) {
+        return new WP_Error('pera_portal_quote_invalid_price', __('Quoted price must be greater than zero.', 'pera-portal'), ['status' => 400]);
+    }
+
+    $currency = sanitize_text_field((string) ($request['currency'] ?? 'GBP'));
+    if ($currency === '') {
+        $currency = 'GBP';
+    }
+
+    $floor_svg = pera_portal_quote_resolve_floor_svg_markup($floor_id);
+    if (is_wp_error($floor_svg)) {
+        return $floor_svg;
+    }
+
+    $plan = pera_portal_quote_get_field('unit_detail_plan', $unit_id);
+    $source_attachment_id = is_array($plan) && !empty($plan['ID']) ? (int) $plan['ID'] : (is_numeric($plan) ? (int) $plan : 0);
+    $copied_attachment_id = pera_portal_quote_copy_attachment($source_attachment_id, 'quote-apartment-plan');
+
+    $snapshot = [
+        'payload_version' => 1,
+        'reference' => '',
+        'building_title' => wp_strip_all_tags((string) get_the_title($building_id), true),
+        'floor_label' => sanitize_text_field((string) pera_portal_quote_get_field('floor_number', $floor_id)),
+        'unit_code' => sanitize_text_field((string) pera_portal_quote_get_field('unit_code', $unit_id)),
+        'unit_type' => sanitize_text_field((string) pera_portal_quote_get_field('unit_type', $unit_id)),
+        'net_size' => pera_portal_quote_get_field('net_size', $unit_id),
+        'gross_size' => pera_portal_quote_get_field('gross_size', $unit_id),
+        'price' => $price,
+        'currency' => $currency,
+        'unit_status' => sanitize_key((string) pera_portal_quote_get_field('status', $unit_id)),
+        'consultant_note' => sanitize_textarea_field((string) ($request['consultant_note'] ?? '')),
+        'issued_gmt' => $now_gmt,
+        'expires_gmt' => $expires_gmt,
+        'issued_by' => sanitize_text_field(wp_get_current_user()->display_name),
+        'client_name' => sanitize_text_field((string) ($request['client_name'] ?? '')),
+        'client_email' => sanitize_email((string) ($request['client_email'] ?? '')),
+        'client_phone' => sanitize_text_field((string) ($request['client_phone'] ?? '')),
+        'crm_client_id' => sanitize_text_field((string) ($request['crm_client_id'] ?? '')),
+        'crm_deal_id' => sanitize_text_field((string) ($request['crm_deal_id'] ?? '')),
+        'crm_note' => sanitize_textarea_field((string) ($request['crm_note'] ?? '')),
+        'source_context' => sanitize_text_field((string) ($request['source_context'] ?? 'portal')),
+        'source_channel' => sanitize_text_field((string) ($request['source_channel'] ?? '')),
+        'disclaimer' => __('This quote is a frozen snapshot and does not guarantee ongoing availability.', 'pera-portal'),
+    ];
+
+    return [
+        'snapshot' => $snapshot,
+        'source' => [
+            'building_id' => $building_id,
+            'floor_id' => $floor_id,
+            'unit_id' => $unit_id,
+        ],
+        'floor_svg' => $floor_svg,
+        'apartment_plan_attachment_id' => $copied_attachment_id,
+    ];
+}
diff --git a/wp-content/plugins/pera-portal/includes/quotes/token-service.php b/wp-content/plugins/pera-portal/includes/quotes/token-service.php
new file mode 100644
index 0000000..094d86b
--- /dev/null
+++ b/wp-content/plugins/pera-portal/includes/quotes/token-service.php
@@ -0,0 +1,37 @@
+<?php
+
+if (!defined('ABSPATH')) {
+    exit;
+}
+
+function pera_portal_quote_generate_token()
+{
+    for ($i = 0; $i < 5; $i++) {
+        $raw = random_bytes(32);
+        $token = rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
+
+        if (!pera_portal_quote_find_by_token($token)) {
+            return $token;
+        }
+    }
+
+    return new WP_Error('pera_portal_quote_token_generation_failed', __('Could not generate unique quote token.', 'pera-portal'));
+}
+
+function pera_portal_quote_generate_reference()
+{
+    $prefix = 'PQ-' . gmdate('Ymd') . '-';
+    $query = new WP_Query([
+        'post_type' => 'pera_quote',
+        'post_status' => 'any',
+        'posts_per_page' => -1,
+        'fields' => 'ids',
+        'meta_key' => '_pera_quote_reference',
+        'meta_value' => $prefix,
+        'meta_compare' => 'LIKE',
+    ]);
+
+    $sequence = count($query->posts) + 1;
+
+    return $prefix . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
+}
diff --git a/wp-content/plugins/pera-portal/includes/rest/quote-routes.php b/wp-content/plugins/pera-portal/includes/rest/quote-routes.php
new file mode 100644
index 0000000..d91ac56
--- /dev/null
+++ b/wp-content/plugins/pera-portal/includes/rest/quote-routes.php
@@ -0,0 +1,125 @@
+<?php
+
+if (!defined('ABSPATH')) {
+    exit;
+}
+
+function pera_portal_quote_user_can_create()
+{
+    return function_exists('pera_portal_current_user_can_create_quotes') ? pera_portal_current_user_can_create_quotes() : (function_exists('pera_portal_current_user_can_access') && pera_portal_current_user_can_access());
+}
+
+function pera_portal_rest_create_quote(WP_REST_Request $request)
+{
+    if (!pera_portal_quote_user_can_create()) {
+        return new WP_Error('pera_portal_quote_forbidden', __('Portal access required.', 'pera-portal'), ['status' => is_user_logged_in() ? 403 : 401]);
+    }
+
+    $unit_id = absint($request->get_param('unit_id'));
+    if ($unit_id <= 0) {
+        return new WP_Error('pera_portal_quote_unit_required', __('A unit is required.', 'pera-portal'), ['status' => 400]);
+    }
+
+    $snapshot_result = pera_portal_quote_build_snapshot($unit_id, $request->get_params());
+    if (is_wp_error($snapshot_result)) {
+        return $snapshot_result;
+    }
+
+    $token = pera_portal_quote_generate_token();
+    if (is_wp_error($token)) {
+        return $token;
+    }
+
+    $reference = pera_portal_quote_generate_reference();
+    $snapshot = $snapshot_result['snapshot'];
+    $snapshot['reference'] = $reference;
+
+    $quote_post = pera_portal_quote_save([
+        'token' => $token,
+        'reference' => $reference,
+        'status' => 'active',
+        'issued_by_user_id' => get_current_user_id(),
+        'issued_by_name' => $snapshot['issued_by'],
+        'created_gmt' => gmdate('Y-m-d H:i:s'),
+        'issued_gmt' => $snapshot['issued_gmt'],
+        'expires_gmt' => $snapshot['expires_gmt'],
+        'source_building_id' => $snapshot_result['source']['building_id'],
+        'source_floor_id' => $snapshot_result['source']['floor_id'],
+        'source_unit_id' => $snapshot_result['source']['unit_id'],
+        'crm_client_id' => $snapshot['crm_client_id'],
+        'crm_deal_id' => $snapshot['crm_deal_id'],
+        'client_name' => $snapshot['client_name'],
+        'client_email' => $snapshot['client_email'],
+        'client_phone' => $snapshot['client_phone'],
+        'crm_note' => $snapshot['crm_note'],
+        'source_context' => $snapshot['source_context'],
+        'source_channel' => $snapshot['source_channel'],
+        'payload' => $snapshot,
+        'floor_plan_mode' => 'svg_markup',
+        'floor_plan_svg' => $snapshot_result['floor_svg'],
+        'apartment_plan_attachment_id' => $snapshot_result['apartment_plan_attachment_id'],
+    ]);
+
+    if (is_wp_error($quote_post)) {
+        return $quote_post;
+    }
+
+    return rest_ensure_response([
+        'quote_id' => (int) $quote_post->ID,
+        'quote_reference' => $reference,
+        'status' => 'active',
+        'token' => $token,
+        'public_url' => pera_portal_quote_get_public_url($token),
+        'warning' => $snapshot_result['apartment_plan_attachment_id'] > 0 ? '' : __('Apartment plan was missing and was not included in this quote.', 'pera-portal'),
+    ]);
+}
+
+function pera_portal_rest_revoke_quote(WP_REST_Request $request)
+{
+    if (!pera_portal_quote_user_can_create()) {
+        return new WP_Error('pera_portal_quote_forbidden', __('Portal access required.', 'pera-portal'), ['status' => is_user_logged_in() ? 403 : 401]);
+    }
+
+    $quote_id = absint($request->get_param('quote_id'));
+    $quote = $quote_id > 0 ? get_post($quote_id) : null;
+    if (!($quote instanceof WP_Post) || $quote->post_type !== 'pera_quote') {
+        return new WP_Error('pera_portal_quote_not_found', __('Quote not found.', 'pera-portal'), ['status' => 404]);
+    }
+
+    update_post_meta($quote_id, '_pera_quote_status', 'revoked');
+    update_post_meta($quote_id, '_pera_quote_revoked_gmt', gmdate('Y-m-d H:i:s'));
+
+    return rest_ensure_response(['quote_id' => $quote_id, 'status' => 'revoked']);
+}
+
+function pera_portal_register_quote_rest_routes()
+{
+    register_rest_route(PERA_PORTAL_REST_NAMESPACE, '/quotes', [
+        'methods' => WP_REST_Server::CREATABLE,
+        'callback' => 'pera_portal_rest_create_quote',
+        'permission_callback' => 'pera_portal_quote_user_can_create',
+        'args' => [
+            'unit_id' => ['required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint'],
+            'quoted_price' => ['required' => true, 'type' => 'number'],
+            'currency' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
+            'expires_at' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
+            'consultant_note' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'],
+            'client_name' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
+            'client_email' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_email'],
+            'client_phone' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
+            'crm_client_id' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
+            'crm_deal_id' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
+            'crm_note' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'],
+            'source_context' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
+            'source_channel' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
+        ],
+    ]);
+
+    register_rest_route(PERA_PORTAL_REST_NAMESPACE, '/quotes/(?P<quote_id>[0-9]+)/revoke', [
+        'methods' => WP_REST_Server::EDITABLE,
+        'callback' => 'pera_portal_rest_revoke_quote',
+        'permission_callback' => 'pera_portal_quote_user_can_create',
+    ]);
+}
+
+add_action('rest_api_init', 'pera_portal_register_quote_rest_routes');
diff --git a/wp-content/plugins/pera-portal/includes/routing/portal-pages.php b/wp-content/plugins/pera-portal/includes/routing/portal-pages.php
index e845911..2515ab3 100644
--- a/wp-content/plugins/pera-portal/includes/routing/portal-pages.php
+++ b/wp-content/plugins/pera-portal/includes/routing/portal-pages.php
@@ -8,6 +8,7 @@ function pera_portal_register_page_rewrites()
 {
     add_rewrite_rule('^portal/?$', 'index.php?pera_portal_page=landing', 'top');
     add_rewrite_rule('^portal/building/([0-9]+)/?$', 'index.php?pera_portal_page=building&pera_building_id=$matches[1]', 'top');
+    add_rewrite_rule('^portal/quote/([^/]+)/?$', 'index.php?pera_portal_page=quote&pera_quote_token=$matches[1]', 'top');
 }
 
 add_action('init', 'pera_portal_register_page_rewrites');
@@ -16,6 +17,7 @@ function pera_portal_register_page_query_vars($vars)
 {
     $vars[] = 'pera_portal_page';
     $vars[] = 'pera_building_id';
+    $vars[] = 'pera_quote_token';
 
     return $vars;
 }
@@ -34,6 +36,8 @@ function pera_portal_maybe_override_page_template($template)
         $portal_template = PERA_PORTAL_PATH . '/templates/portal-landing.php';
     } elseif ($portal_page === 'building') {
         $portal_template = PERA_PORTAL_PATH . '/templates/portal-building.php';
+    } elseif ($portal_page === 'quote') {
+        $portal_template = PERA_PORTAL_PATH . '/templates/portal-quote.php';
     } else {
         return $template;
     }
@@ -76,3 +80,14 @@ function pera_portal_render_rewrite_notice()
 }
 
 add_action('admin_notices', 'pera_portal_render_rewrite_notice');
+
+function pera_portal_output_quote_noindex_meta()
+{
+    if (sanitize_key((string) get_query_var('pera_portal_page')) !== 'quote') {
+        return;
+    }
+
+    echo "\n" . '<meta name="robots" content="noindex, nofollow" />' . "\n";
+}
+
+add_action('wp_head', 'pera_portal_output_quote_noindex_meta', 1);
diff --git a/wp-content/plugins/pera-portal/templates/portal-quote.php b/wp-content/plugins/pera-portal/templates/portal-quote.php
new file mode 100644
index 0000000..61bdf61
--- /dev/null
+++ b/wp-content/plugins/pera-portal/templates/portal-quote.php
@@ -0,0 +1,109 @@
+<?php
+
+if (!defined('ABSPATH')) {
+    exit;
+}
+
+$token = sanitize_text_field((string) get_query_var('pera_quote_token'));
+$quote = $token !== '' ? pera_portal_quote_find_by_token($token) : null;
+
+if (!($quote instanceof WP_Post) || $quote->post_type !== 'pera_quote') {
+    global $wp_query;
+    if (isset($wp_query) && $wp_query instanceof WP_Query) {
+        $wp_query->set_404();
+    }
+    status_header(404);
+    nocache_headers();
+    $not_found_template = get_404_template();
+    if ($not_found_template) {
+        include $not_found_template;
+    }
+    exit;
+}
+
+$status = pera_portal_quote_get_business_status($quote->ID);
+$payload = json_decode((string) get_post_meta($quote->ID, '_pera_quote_payload_v1', true), true);
+$payload = is_array($payload) ? $payload : [];
+$floor_svg = (string) get_post_meta($quote->ID, '_pera_quote_floor_plan_svg', true);
+$apartment_plan_id = absint(get_post_meta($quote->ID, '_pera_quote_apartment_plan_attachment_id', true));
+$apartment_plan_url = $apartment_plan_id > 0 ? wp_get_attachment_url($apartment_plan_id) : '';
+
+$banner = $status === 'revoked'
+    ? __('This quote has been revoked. Please contact your consultant for an updated offer.', 'pera-portal')
+    : ($status === 'expired'
+        ? __('This quote has expired. Please contact your consultant for a refreshed quote.', 'pera-portal')
+        : __('Quote is active and valid until the expiry date below.', 'pera-portal'));
+
+status_header(200);
+nocache_headers();
+?>
+<!doctype html>
+<html <?php language_attributes(); ?>>
+<head>
+    <meta charset="<?php bloginfo('charset'); ?>" />
+    <meta name="viewport" content="width=device-width, initial-scale=1" />
+    <?php wp_head(); ?>
+    <style>
+        .pera-quote-page{font-family:Arial,sans-serif;max-width:1000px;margin:0 auto;padding:24px;color:#111}
+        .pera-quote-header,.pera-quote-section{border:1px solid #ddd;border-radius:8px;padding:16px;margin-bottom:16px}
+        .pera-quote-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
+        .pera-quote-status{padding:12px;border-radius:8px;font-weight:600}.status-active{background:#ecfdf5;color:#166534}.status-expired{background:#fff7ed;color:#9a3412}.status-revoked{background:#fef2f2;color:#991b1b}
+        .pera-quote-price{font-size:30px;font-weight:700}
+        .pera-quote-plan img,.pera-quote-plan svg{max-width:100%;height:auto;border:1px solid #ddd;border-radius:4px}
+        @media print {.pera-quote-page{padding:0}.pera-quote-header,.pera-quote-section{page-break-inside:avoid;box-shadow:none}}
+    </style>
+</head>
+<body <?php body_class('pera-quote-public-page'); ?>>
+    <main class="pera-quote-page">
+        <section class="pera-quote-header">
+            <h1><?php echo esc_html($payload['building_title'] ?? get_bloginfo('name')); ?></h1>
+            <p><strong><?php esc_html_e('Quote Reference:', 'pera-portal'); ?></strong> <?php echo esc_html((string) ($payload['reference'] ?? get_post_meta($quote->ID, '_pera_quote_reference', true))); ?></p>
+            <div class="pera-quote-status status-<?php echo esc_attr($status); ?>"><?php echo esc_html($banner); ?></div>
+        </section>
+
+        <section class="pera-quote-section">
+            <div class="pera-quote-price"><?php echo esc_html(number_format((float) ($payload['price'] ?? 0), 2) . ' ' . ($payload['currency'] ?? '')); ?></div>
+            <p>
+                <strong><?php esc_html_e('Issued:', 'pera-portal'); ?></strong> <?php echo esc_html((string) ($payload['issued_gmt'] ?? '')); ?> GMT<br>
+                <strong><?php esc_html_e('Valid Until:', 'pera-portal'); ?></strong> <?php echo esc_html((string) ($payload['expires_gmt'] ?? '')); ?> GMT
+            </p>
+        </section>
+
+        <section class="pera-quote-section pera-quote-grid">
+            <p><strong><?php esc_html_e('Floor', 'pera-portal'); ?>:</strong> <?php echo esc_html((string) ($payload['floor_label'] ?? '-')); ?></p>
+            <p><strong><?php esc_html_e('Unit Code', 'pera-portal'); ?>:</strong> <?php echo esc_html((string) ($payload['unit_code'] ?? '-')); ?></p>
+            <p><strong><?php esc_html_e('Unit Type', 'pera-portal'); ?>:</strong> <?php echo esc_html((string) ($payload['unit_type'] ?? '-')); ?></p>
+            <p><strong><?php esc_html_e('Net Size', 'pera-portal'); ?>:</strong> <?php echo esc_html((string) ($payload['net_size'] ?? '-')); ?></p>
+            <p><strong><?php esc_html_e('Gross Size', 'pera-portal'); ?>:</strong> <?php echo esc_html((string) ($payload['gross_size'] ?? '-')); ?></p>
+            <p><strong><?php esc_html_e('Issued By', 'pera-portal'); ?>:</strong> <?php echo esc_html((string) ($payload['issued_by'] ?? '-')); ?></p>
+        </section>
+
+        <section class="pera-quote-section pera-quote-plan">
+            <h3><?php esc_html_e('Frozen Floor Plan', 'pera-portal'); ?></h3>
+            <?php echo $floor_svg !== '' ? wp_kses_post($floor_svg) : '<p>' . esc_html__('No floor plan available.', 'pera-portal') . '</p>'; ?>
+        </section>
+
+        <section class="pera-quote-section pera-quote-plan">
+            <h3><?php esc_html_e('Frozen Apartment Plan', 'pera-portal'); ?></h3>
+            <?php if ($apartment_plan_url) : ?>
+                <img src="<?php echo esc_url($apartment_plan_url); ?>" alt="<?php echo esc_attr__('Apartment plan snapshot', 'pera-portal'); ?>" />
+            <?php else : ?>
+                <p><?php esc_html_e('Apartment plan was unavailable at issue time.', 'pera-portal'); ?></p>
+            <?php endif; ?>
+        </section>
+
+        <?php if (!empty($payload['consultant_note'])) : ?>
+            <section class="pera-quote-section">
+                <h3><?php esc_html_e('Consultant Note', 'pera-portal'); ?></h3>
+                <p><?php echo nl2br(esc_html((string) $payload['consultant_note'])); ?></p>
+            </section>
+        <?php endif; ?>
+
+        <section class="pera-quote-section">
+            <p><strong><?php esc_html_e('Client', 'pera-portal'); ?>:</strong> <?php echo esc_html((string) ($payload['client_name'] ?? '-')); ?> <?php echo esc_html((string) ($payload['client_email'] ?? '')); ?> <?php echo esc_html((string) ($payload['client_phone'] ?? '')); ?></p>
+            <p><?php echo esc_html((string) ($payload['disclaimer'] ?? '')); ?></p>
+        </section>
+    </main>
+    <?php wp_footer(); ?>
+</body>
+</html>
diff --git a/wp-content/plugins/pera-portal/templates/portal-shell.php b/wp-content/plugins/pera-portal/templates/portal-shell.php
index deb3568..1b59f0d 100644
--- a/wp-content/plugins/pera-portal/templates/portal-shell.php
+++ b/wp-content/plugins/pera-portal/templates/portal-shell.php
@@ -126,6 +126,7 @@ if (!defined('ABSPATH')) {
                 </div>
                 <div class="portal-print-section portal-print-section--details">
                     <div class="pera-portal-details-placeholder"><?php echo esc_html__('Unit details placeholder', 'pera-portal'); ?></div>
+                    <div class="pera-portal-quote-tools" data-quote-tools hidden></div>
                 </div>
                 <div class="portal-print-section portal-print-section--plan">
                     <div class="pera-portal-plan-placeholder"></div>
```
