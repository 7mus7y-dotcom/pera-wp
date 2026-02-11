# Roles and Capabilities Audit (CRM follow-up)

Date: automated in Codex follow-up branch.

## Scope
- Searched MU plugin for `advisor` references and specifically for role-slug usage assumptions.

## Checks run
- `rg -n "advisor" wp-content/mu-plugins/peracrm`
- `rg -n "get_role\('advisor'\)|add_role\('advisor'\)|set_role\('advisor'\)|role__in.*advisor|role'\s*=>\s*'advisor'|in_array\('advisor'" wp-content/mu-plugins/peracrm`

## Findings
- **Advisor was never a WP role slug in current runtime paths**, and no `get_role('advisor')`/`add_role('advisor')`/role filters for `advisor` exist.
- Remaining `advisor` references are domain labels for assigned staff ownership fields/events (for example `assigned_advisor_user_id`, `crm_assigned_advisor`, `advisor_user_id`), not WP roles.

## Applied decision
- Owner/staff selection now uses `peracrm_get_staff_users()` (`employee` + `manager` + `administrator`) instead of the old employee-only helper that was named around advisor users.
