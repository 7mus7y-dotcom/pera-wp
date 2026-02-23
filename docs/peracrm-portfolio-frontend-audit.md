# PeraCRM Portfolio Public Route Audit (`/portfolio/{token}`)

## Route and request resolution

- Route registration is in `wp-content/themes/hello-elementor-child/inc/portfolio-token.php` via:
  - `add_rewrite_rule('^portfolio/([^/]+)/?$', 'index.php?portfolio_token=$matches[1]', 'top')`
  - `query_vars` filter adding `portfolio_token`
- Token sanitization/validation is handled by:
  - `pera_portfolio_token_get_request_token()` (alphanumeric-only token)
  - `pera_portfolio_token_get_request_context()` (finds portfolio by `_portfolio_token`, checks revoked/expired, returns ordered property IDs + client/advisor metadata)
- Template routing is handled by:
  - `template_include` filter in `pera_portfolio_token_template_include()` returning `page-portfolio-token.php`

## Actual template file path

- `wp-content/themes/hello-elementor-child/page-portfolio-token.php`

## Current page wrappers/hooks in template

- `<main id="primary" class="site-main content-rail portfolio-token-page">`
- Hero section: `<section class="hero hero--left hero--fit" id="crm-hero">`
- Content section: `<section class="section section-soft">`
- Property list wrapper: `<div id="property-grid" class="cards-grid">`

## How properties are rendered now

- Template creates `WP_Query` for `post_type=property`, constrained by `_portfolio_property_ids` order.
- In the loop, cards are rendered by `pera_render_property_card( array( 'variant' => 'archive' ) )` from the theme helper.
- No separate table/list rendering exists currently; only card grid output.

## Data source for portfolio properties

- Public route reads property IDs from portfolio post meta `_portfolio_property_ids` (stored during token creation).
- CRM relation records are in `peracrm` repository function `peracrm_client_property_list($client_id, 'portfolio', ...)`, but current public template does not consume row-level portfolio fields.

## CSS and JS used on this route

- CSS:
  - Global `css/main.css` (always enqueued)
  - `css/property.css` + `css/property-card.css` are loaded for token route via `is_portfolio_token` -> `pera_enqueue_property_archive_assets(...)`
- JS:
  - Global `js/main.js` (always enqueued)
  - No dedicated portfolio-token front-end script currently.
