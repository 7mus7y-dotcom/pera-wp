# PeraCRM Linked Properties: `viewed` relation removal (CRM client view)

## What was removed

- Removed `viewed` from the relation types loaded for the front-end CRM client page (`/crm/client/{id}`), so the Linked Properties panel no longer renders a Viewed section.
- Removed the `Viewed` option from the Linked Properties relation type dropdown on the CRM client page.

## What was intentionally left unchanged

- No database schema changes were made.
- Existing `crm_client_property` rows with `relation_type='viewed'` remain in the database.
- Link/unlink security checks (access gate + nonce verification) were not changed.
- Portfolio token dialog and token generation flow were not changed.

## Re-enable in future

To re-enable `viewed`, restore it in both places via git history:

1. `wp-content/themes/hello-elementor-child/inc/crm-client-view.php`
   - add `'viewed'` back to the `$relation_types` array used by `pera_crm_client_view_load_data()`.
2. `wp-content/themes/hello-elementor-child/page-crm-client.php`
   - add the `<option value="viewed">Viewed</option>` entry back in the relation type `<select>`.

Tip: use `git log --follow -- docs/peracrm-linked-properties-viewed-removal.md` and `git blame` on the files above to find the exact removal commit.
