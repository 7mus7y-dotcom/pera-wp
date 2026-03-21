# PeraCRM impersonation target discovery fix

## Root cause found

The canonical selector helper, `peracrm_get_impersonation_targets()`, only queried users with:

```php
get_users([
    'role__in' => ['employee'],
    ...
]);
```

On multisite, that discovery step is too narrow because role membership is stored per site/blog. If the lookup is not explicitly scoped to the current CRM subsite, eligible users attached to the current subsite can be skipped before the final validation helper ever runs.

That meant a valid employee like Dave could be excluded from the candidate list even though he belongs to the active CRM subsite and still passes the existing impersonation target rule.

## What changed

`peracrm_get_impersonation_targets()` now:

1. resolves the current blog/site ID
2. loads a broader user list for that current blog using `get_users()` with `blog_id`
3. explicitly verifies multisite membership on the current blog
4. verifies CRM access relevance
5. preserves the existing final gate via `peracrm_user_is_impersonatable_target()`

This keeps target discovery broad enough for multisite while leaving the final impersonation eligibility policy unchanged.

## Multisite/subsite behavior now

The helper is now subsite-safe because it discovers candidates from the **current CRM blog context** instead of relying on a role-only query that may omit valid subsite users.

As a result:

- users do **not** need network-admin status to appear
- only users relevant to the current site/blog are considered
- users from unrelated subsites are not returned just because they exist somewhere on the network
- the current real admin user is still excluded by the final target validation helper

## Why Dave should now appear

Dave should now appear in the impersonation dropdown when all of the following are true:

- he is attached to the current CRM subsite
- he has CRM-relevant access on that subsite
- he satisfies the existing employee/advisor target rule enforced by `peracrm_user_is_impersonatable_target()`
- he is not the current real logged-in admin user

In that state, Dave will survive both the broader subsite candidate discovery step and the unchanged final impersonation target validation step.

## Manual QA checklist

### Admin on current CRM subsite

- [ ] Impersonation dropdown shows `My view`
- [ ] Dave appears in the dropdown
- [ ] Selecting Dave switches the CRM view successfully
- [ ] Resetting back to `My view` works

### Negative checks

- [ ] Users not eligible for impersonation do not appear
- [ ] Current real admin user does not appear as a target
- [ ] Users from irrelevant contexts do not appear if they are not valid for this subsite/CRM context
