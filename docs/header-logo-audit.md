# Header / Off-canvas Logo Source Audit

## Scope checked
- `wp-content/themes/hello-elementor-child`
- `wp-content/mu-plugins`
- `wp-content/plugins`
- Repository files for Elementor Theme Builder / popup exports (none present)

## Findings

### 1) Main header logo source
- Source file: `wp-content/themes/hello-elementor-child/header.php`
- The header sets `$logo_path` to a static theme file: `get_stylesheet_directory() . '/logos-icons/pera-logo.svg'`.
- It renders that file directly with `file_get_contents( $logo_path )` if it exists.
- Fallback is another static theme asset: `get_stylesheet_directory_uri() . '/logos-icons/logo-white.svg'`.

Classification: **(4) hardcoded image/asset path in theme**, with inline SVG include from the filesystem.

### 2) Off-canvas/mobile menu logo source
- Source file: `wp-content/themes/hello-elementor-child/header.php`
- Off-canvas top area reuses the same `$logo_path` + `file_get_contents()` logic.
- Fallback is the same hardcoded `logo-white.svg` URI.

Classification: **(4) hardcoded image/asset path in theme**, with inline SVG include from the filesystem.

### 3) Elementor Site Identity global logo integration
- No references found to Elementor Site Logo widget class (`elementor-widget-theme-site-logo`) or Elementor image widget class (`elementor-widget-image`) in theme/plugin/mu-plugin code.
- No Elementor template export files were found in the repository to inspect Theme Builder header/popup content.

Status in this repo: **No evidence that current header/off-canvas logos are powered by Elementor global Site Identity**.

### 4) WordPress custom logo integration
- No calls found to:
  - `add_theme_support('custom-logo')`
  - `the_custom_logo()`
  - `get_custom_logo()`
  - `get_theme_mod('custom_logo')`
- Theme setup currently registers image size and menus but not `custom-logo` support.

Status: **Not using WordPress custom logo APIs in this codebase**.

### 5) Auto-update behavior from global logo
- Main header logo: **Does not auto-update** from Elementor Site Identity or WP custom logo.
- Off-canvas/mobile logo: **Does not auto-update** from Elementor Site Identity or WP custom logo.
- Both will only change if theme assets at `logos-icons/pera-logo.svg` / `logos-icons/logo-white.svg` are changed or header logic is edited.

## Evidence pointers
- Hardcoded header + off-canvas logo logic: `wp-content/themes/hello-elementor-child/header.php`
- Theme setup without custom-logo support: `wp-content/themes/hello-elementor-child/inc/modules/theme-setup.php`
- No relevant logo integration in local plugin/mu-plugin code:
  - `wp-content/plugins/pera-portal/*`
  - `wp-content/mu-plugins/*`

## Recommended way to unify (without implementing changes yet)
1. Pick one source of truth for all logos (recommended: **Elementor Site Identity logo** if Elementor Theme Builder controls header UX).
2. Standardize both header and off-canvas to one rendering path:
   - Either Elementor Theme Builder widgets (Site Logo widget in header + popup/off-canvas template), or
   - WordPress custom-logo APIs in theme (`add_theme_support('custom-logo')` + `get_custom_logo()`), then consume same markup in both places.
3. If staying theme-based, avoid duplicate hardcoded paths by creating one helper (e.g., `pera_render_site_logo()`) and reuse it in both header and off-canvas sections.
4. If Elementor templates exist in DB (not versioned here), audit those templates directly in wp-admin to ensure no separate manually-selected image widget is overriding one location.
