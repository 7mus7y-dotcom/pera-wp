# Repository guide

Start here, then read the one guide that owns your task. Code is the source of truth; re-check it before changing behaviour.

## Architecture

- `wp-content/themes/hello-elementor-child/`: Hello Elementor child theme and the public site frontend.
- `wp-content/plugins/peracrm/`: CRM data, staff routes, workflows, and CRM UI.
- `wp-content/plugins/pera-portal/`: authenticated building/unit portal and tokenised quotes.
- `wp-content/plugins/pera-currency/`: canonical-USD conversion and browser price hydration.
- `wp-content/plugins/pera-multilingual/`: language routing and stored translations.
- Property CPT: listing data consumed by the theme; cards, singles, archives, taxonomies, and V2 units cross the theme/plugin boundaries above.

## Read this first when…

| Task | Canonical guide |
|---|---|
| Changing the public theme, templates, CSS, JavaScript, header, or off-canvas navigation | [`docs/theme.md`](docs/theme.md) |
| Creating/editing properties, units, cards, filters, archives, or listing prices | [`docs/property-cpt.md`](docs/property-cpt.md) |
| Changing CRM behaviour, data, access, routes, or notifications | [`docs/crm.md`](docs/crm.md) |
| Restyling CRM screens or fixing responsive CRM presentation | [`docs/crm-ui.md`](docs/crm-ui.md) |
| Changing the authenticated portal, public quote, or portfolio-token surfaces | [`docs/portal.md`](docs/portal.md) |
| Changing languages, translated fields, routing, or localized links | [`docs/multilingual.md`](docs/multilingual.md) |
| Changing conversion, formatting, currency selectors, or monetary markup | [`docs/currency.md`](docs/currency.md) |
| Editing SEO metadata, social images, schema, canonicals, robots, or archive SEO | [`docs/content-seo.md`](docs/content-seo.md) |

## Safety and verification

- Do not edit vendor or dependency files.
- Do not make broad/global CSS changes before identifying the owning component and route.
- Reuse existing design tokens, utilities, components, and class conventions; do not create competing styles or duplicate classes.
- Validate current code and its tests before changing feature behaviour.
- Never document secrets, production credentials, private URLs, or private operational values.
- Run the narrowest relevant tests, lint, or static checks. State which browser/manual checks were not performed and why.
