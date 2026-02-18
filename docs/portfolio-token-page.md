# Portfolio Token Page

This adds a public portfolio route that does not require login:

- URL format: `/portfolio/{token}/`
- Token portfolios are stored in a `portfolio` CPT
- Property cards reuse existing `parts/property-card-v2.php` rendering via `pera_render_property_card()`

## Data model

`portfolio` post meta keys:

- `_portfolio_token` (string, unique token)
- `_portfolio_property_ids` (array of property post IDs, order preserved)
- `_portfolio_client_id` (optional CRM client post ID)
- `_portfolio_expires_at` (optional Unix timestamp)
- `_portfolio_revoked` (`0` or `1`)

## WP-CLI: create a portfolio

```bash
wp pera portfolio create --client=123 --properties=564,777,888 --expires="+30 days"
```

Notes:

- `--properties` is required and preserves the given order.
- `--client` is optional.
- `--expires` is optional and accepts any `strtotime()` format (for example, `"+7 days"`, `"2026-12-01 12:00:00"`).

The command outputs:

- portfolio post ID
- generated token
- public URL

## Revoking or expiring a portfolio

- Revoke manually by setting `_portfolio_revoked` to `1`.
- Expiry is controlled by `_portfolio_expires_at` (Unix timestamp).
- Revoked/expired links return HTTP `410` with a generic unavailable message.

## SEO and indexing behavior

All `/portfolio/{token}/` responses include robots directives via `wp_robots`:

- `noindex`
- `nofollow`

Invalid tokens also remain `noindex,nofollow` and return HTTP `404`.

## Deploy note: rewrite flush

After deploy, refresh rewrites once:

```bash
wp rewrite flush --hard
```

Or in WP Admin: **Settings → Permalinks → Save Changes**.
