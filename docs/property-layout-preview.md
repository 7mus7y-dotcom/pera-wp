# Property layout preview infrastructure

No experimental layout currently exists. PR #1437's production layout renders
identically for ordinary URLs and administrator, non-admin and anonymous preview
URLs. The MU plugin only supplies a gate and cache isolation.

## Future experiments

After the main WordPress query (for example, in a template), call
`pera_property_layout_preview_enabled()`. It returns true only for a logged-in
user with `current_user_can( 'manage_options' )`, on `is_singular( 'property' )`,
with the exact string query value `?layout_preview=1`. Arrays, whitespace and
other malformed values are rejected rather than normalized into an opt-in.
Never use the query parameter alone as authorization.

A future PR may use this helper to select experimental rendering while retaining
the production fallback. Scope experimental CSS/JS to that rendering and test
missing content, mobile/desktop and RTL. This infrastructure PR introduces no
template branches, markup, styles or scripts.

Compare a property URL with and without `?layout_preview=1` as an administrator,
as a logged-in non-admin, and logged out. Also check a non-property URL and
malformed values such as `layout_preview[]=1`, `0`, and `%201`. Today all property
cases must render the same production layout. Run:

```sh
php wp-content/themes/hello-elementor-child/tests/property-layout-preview-gate-test.php
php wp-content/themes/hello-elementor-child/tests/property-layout-test.php
php wp-content/themes/hello-elementor-child/tests/property-editorial-test.php
php wp-content/themes/hello-elementor-child/tests/property-sticky-summary-test.php
```

## Cache requirements

For every request containing the parsed `layout_preview` query key, including
anonymous, non-property and malformed requests, the plugin sets `DONOTCACHEPAGE`
at MU-plugin load if not already defined. An existing constant is not overwritten;
ensure cache configuration does not define it as false for these requests.
At `init` priority 0 it calls WordPress `nocache_headers()` and sends
`Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0`,
`CDN-Cache-Control: no-store`, and `Surrogate-Control: no-store` before output.
Requests without that key receive no cache changes from this plugin.

CDN, reverse-proxy, server caches and `advanced-cache.php` must independently
bypass both lookup and storage for preview query keys (including array forms)
and authenticated WordPress cookies. PHP headers cannot prevent an upstream
cache hit before WordPress runs. Verify bypass and response headers on the actual
infrastructure, alternating authenticated and anonymous sessions; stubbed local
tests cannot certify upstream isolation. Other plugins must not override these
headers or cache authenticated experimental output.

## Promotion

Promote a successful experiment by making its approved rendering the production
default, removing its branches and obsolete experimental assets/tests. Keep this
MU plugin, its focused gate tests and this document for future experiments.
Update the production layout documentation and restore the statement that no
experiment is active.
