# PeraCRM authorization hardening deployment

PeraCRM now uses one server-side client policy. `manage_options` and
`peracrm_manage_all_clients` produce full scope; other CRM users receive only
clients whose current and legacy assignment fields resolve consistently to
them. Conflicting or missing assignment data fails closed. Generic `wp/v2`
exposure is disabled because no repository consumer requires it and its generic
controller is not an assignment-aware PeraCRM boundary.

## Legacy role migration

The plugin does not silently delete `advisor` while an account still has that
role. It records the affected IDs and displays an administrator warning. Before
deployment, inventory every affected account:

```sh
wp user list --role=advisor --fields=ID,user_login,display_name,roles
```

Review the result with the production owner. Migrate each confirmed ordinary
adviser explicitly (repeat for every approved ID), then allow the plugin's next
`init` request to remove the now-empty role:

```sh
wp user set-role <USER_ID> employee
wp user list --role=advisor --fields=ID,user_login,display_name,roles
```

For the Meta review fixture, the approved role operation is:

```sh
wp user get 97 --fields=ID,user_login,roles
wp user set-role 97 employee
```

This repository change does **not** assert that the production command ran.

## Meta reviewer verification

Run these commands on the target production blog after deployment:

```sh
wp user get 97 --fields=ID,user_login,roles
wp user has-cap 97 manage_options; test $? -ne 0
wp user has-cap 97 peracrm_manage_all_clients; test $? -ne 0
wp post get 59939 --field=post_type
wp post meta get 59939 assigned_advisor_user_id
wp post meta get 59939 crm_assigned_advisor
wp post list --post_type=crm_client --post_status=any --meta_key=assigned_advisor_user_id --meta_value=97 --fields=ID,post_title
wp post list --post_type=crm_client --post_status=any --meta_key=crm_assigned_advisor --meta_value=97 --fields=ID,post_title
```

Both metadata reads must print `97`; both lists must contain only deliberately
assigned clients (expected fixture: `59939`). Verify in an authenticated browser
as `metaappreviewer` that client 59939 and its WhatsApp conversation can be read
and messaged, then substitute a known unrelated client ID in both GET and POST
conversation routes and confirm HTTP 403. Also confirm client lists, header
search, dashboard/pipeline counts, direct client pages, and custom REST
collections do not reveal that unrelated client. Do not change Meta or WhatsApp
configuration during this verification.
