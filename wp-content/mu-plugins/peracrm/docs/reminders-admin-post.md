# Reminder status updates via `admin-post.php`

Use the existing admin-post handler instead of adding new front-end endpoints.

## Action and nonce

- Action: `peracrm_update_reminder_status`
- Entry point: `admin-post.php`
- Nonce field name: `peracrm_update_reminder_status_nonce`
- Nonce action: `peracrm_update_reminder_status`

## Required POST fields

- `action` = `peracrm_update_reminder_status`
- `peracrm_reminder_id` (integer reminder id)
- `peracrm_status` (`done`, `dismissed`, or another allowed status)
- `peracrm_redirect` (absolute URL)
- `peracrm_context` (`frontend` to enforce client scope; anything else keeps admin behavior)
- `peracrm_update_reminder_status_nonce`

## Front-end example form

```php
<form method="post" action="<?php echo esc_url( home_url( '/wp-admin/admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="peracrm_update_reminder_status">
	<input type="hidden" name="peracrm_reminder_id" value="<?php echo esc_attr( (string) absint( $reminder_id ) ); ?>">
	<input type="hidden" name="peracrm_status" value="done">
	<input type="hidden" name="peracrm_redirect" value="<?php echo esc_url( $current_url ); ?>">
	<input type="hidden" name="peracrm_context" value="frontend">
	<?php wp_nonce_field( 'peracrm_update_reminder_status', 'peracrm_update_reminder_status_nonce' ); ?>
	<button type="submit">Mark done</button>
</form>
```

For front-end CRM forms, prefer `home_url('/wp-admin/admin-post.php')` to keep cookies on the `WP_HOME` host; `admin_url()` can point at `WP_SITEURL`.

## `peracrm_allowed_client_ids_for_user` filter

When `peracrm_context=frontend`, reminder updates call
`peracrm_reminders_update_status_authorized()` with `enforce_client_scope=true`.
The wrapper uses:

```php
apply_filters( 'peracrm_allowed_client_ids_for_user', null, $actor_user_id )
```

Theme code should provide the allowed client IDs for the current CRM user via this filter.

MU plugin now registers a default fallback for this filter that resolves assigned `crm_client` IDs from `assigned_advisor_user_id` and `crm_assigned_advisor`, so front-end scope enforcement works during `admin-post.php` even if theme files are not loaded.

## Manual test checklist

1. **wp-admin flow**
   - Update reminder to `done` and `dismissed` from admin UI.
   - Confirm notices still show and redirects stay valid.
2. **Front-end `/crm/` flow**
   - Submit "Mark done" from `/crm/` list.
   - Confirm redirect returns to `/crm/` and success notice is applied.
   - Confirm users cannot update reminders outside their allowed client IDs when context is `frontend`.
