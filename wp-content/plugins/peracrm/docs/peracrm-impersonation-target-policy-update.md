# PeraCRM impersonation target policy update

## Previous target behavior

The canonical impersonation target helper, `peracrm_user_is_impersonatable_target()`, previously treated CRM impersonation targets as employee-only.

That meant an admin could switch into eligible employee CRM views, but managers were filtered out before they could appear in the impersonation dropdown or be persisted as the active target.

## New target behavior

Valid CRM impersonation targets now include both of these CRM-facing roles:

- `employee`
- `manager`

The policy remains intentionally narrow. This update does **not** broaden impersonation targets to administrators.

## Explicit rule now used

A valid impersonation target must now:

1. exist as a real `WP_User`
2. not be the current real logged-in user
3. belong to the current CRM site/subsite discovery context
4. have CRM access
5. have an explicit CRM-facing role of either `employee` or `manager`
6. not be an `administrator` target

`peracrm_get_impersonation_targets()` continues to scope discovery to the current CRM blog/subsite, verify CRM access, and then applies the canonical `peracrm_user_is_impersonatable_target()` gate before returning selector options.

## Confirmation

Managers now appear as valid targets for admin impersonation when they belong to the active CRM subsite context and have CRM access.

## Manual QA checklist

### Admin

- [ ] admin sees employees in the dropdown
- [ ] admin now also sees managers in the dropdown
- [ ] switching to a manager view works
- [ ] switching back to My view works

### Negative checks

- [ ] current real admin user does not appear as a target
- [ ] users without CRM access do not appear
- [ ] unrelated users from other subsites do not appear
