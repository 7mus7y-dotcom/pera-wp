# Client workspace panels

`inc/client-panels.php` is the trusted panel registry. Templates, preference reads/writes, and Manage panels share it. This feature only changes the employee's workspace on `/crm/client/{id}/`; it does not change client, instruction, deal, reminder or activity data.

| Panel key | Visibility rule |
| --- | --- |
| `crm-client-summary` | Protected client summary |
| `profile` | Protected profile and key facts |
| `status` | Protected CRM status |
| `reminders` | Protected next actions and reminders |
| `notes` | Protected advisor notes |
| `timeline` | Protected activity and timeline |
| `deals` | Optional, only canonical lead stages `offer_made` or `deal_closed` |
| `seller_properties` | Optional, only client type `seller` |
| `landlord_properties` | Optional, only client type `landlord` |
| `enquiries` | Optional client enquiries |
| `theme_portfolio` | Optional live Theme portfolio |

The summary, status and timeline now have stable `data-crm-panel` identifiers; existing `profile`, `notes`, `reminders` and `deals` identifiers remain intact. Visibility uses `data-crm-optional-panel` on each entire eligible article. The separate attribute preserves existing AJAX extraction contracts, including the legacy inner `properties` workspace. The legacy Portfolio article is commented out upstream and is deliberately excluded from visibility controls. The `advisor` assignment block, Danger zone, notices, access errors and WhatsApp conversation remain outside the optional allowlist. Unknown keys cannot be hidden.

Property instructions continue to use PR #1499's single partial and persistence layer. The current client type chooses its visibility key; sale and rental preferences are independent. All historical instructions remain available inside the current owner panel, and switching away from seller/landlord exposes the existing **Property instruction history** recovery panel. That history panel has no optional key or dismiss control and ignores saved sale/rental preferences. Hiding the active owner panel includes its Add property, edit and property-reminder controls; restoring it recovers their unchanged DOM and values.

## Preferences and security

One WordPress user-meta record per customized employee/client pair is named `_peracrm_client_panels_v1_{CRM target blog ID}_{client ID}`. Its value is `['version' => 1, 'hidden' => ['deals', ...]]`. A record contains at most the five approved optional keys, even if obsolete or protected keys were injected into storage. There is no growing serialized map of all clients and no eviction that would reset an older client's preferences. Target-blog namespacing also prevents multisite client-ID collisions. There are no new tables or client post-meta preferences.

Reads create no records. The first actual visibility change creates a record; Restore all saves an empty list for this client only, including previously saved ineligible keys. Other clients and users are untouched. Ineligibility, pipeline changes and client-type changes do not mutate preferences. The current logged-in WordPress employee owns the preferences, including when using an advisor scope; the selected advisor is not used as the storage owner.

The authenticated `wp_ajax_peracrm_client_panels` POST endpoint verifies a client-bound `peracrm_client_panels_{id}` nonce and the existing `pera_crm_client_view_access_state()` policy before reading/writing metadata. The service validates exact registry keys and current canonical profile/party eligibility on the CRM target blog. Protected keys are rejected on mutations and filtered on reads. User IDs and eligibility are never accepted from the request. Existing metadata updates compare the previous value so conflicting updates can return a retry error instead of silently replacing another tab's value.

## Rendering and workflows

The server omits ineligible Deals entirely and renders saved hidden articles with `hidden`, so there is no initial flash. CSS preserves native hidden semantics over the existing grid styles. Without JavaScript, server preferences still apply; controls remain disabled and a concise message explains how to enable customization. With JavaScript, real accessible buttons and native disclosure/checkbox controls allow dismissal, individual restoration and Restore all. No confirmation dialog, deletion semantics or global button redesign is introduced. Styling uses existing section classes, buttons, spacing and color tokens, including dark-mode tokens.

Requests disable all visibility controls while pending. The client optimistically changes visibility, retains all form values, and rolls back on network, JSON, application or timeout failures. An always-available live region announces results; failures move focus to it. Dismissal moves focus out of the hidden panel to Manage panels. Anchor navigation into an eligible hidden panel restores it through the same authorized request before scrolling/focusing; explicit `deal_id` navigation restores eligible Deals. Unavailable panels are never created by JavaScript.

Successful existing status/profile AJAX saves return server-rendered conditional slots and the updated Manage panels disclosure. Only slots whose eligibility key changed are replaced; unchanged instruction/deal editors and unrelated forms retain unsaved values. The status panel also now has the identifier expected by the existing panel refresh code.

The lead pipeline and deal workflow have separate stages. Existing status writes can move a client out of the two approved stages even while a deal is `payment_in_progress`; this change honors the requested display gate without advancing stages, modifying deals or overriding transition rules. Existing deal access remains in the authorized WordPress client editor's Deals metabox (subject to existing staff capabilities). To resume the frontend deal workspace, select an approved lead stage through the existing CRM status form. No deal records or existing admin controls are removed.

## Validation

Standalone checks:

```
for test in wp-content/plugins/peracrm/tests/*-test.php; do php "$test"; done
node wp-content/plugins/peracrm/tests/client-panels-js-test.js
node --check wp-content/plugins/peracrm/assets/frontend/crm.js
find wp-content/plugins/peracrm -name '*.php' -not -path '*/vendor-webpush/*' -print0 | xargs -0 -n1 php -l
python3 wp-content/plugins/peracrm/vendor-ses/package.py --verify
```

The existing PeraCRM workflow runs the new PHP and JavaScript regression suites. Its disposable single-site/multisite WordPress/MySQL fixture runs `tests/integration/campaigns/client-panels.php`, checking real user metadata and preservation of exact instruction and in-progress deal records. The browser fixture runs `client-panels-browser.cjs`, covering real AJAX/nonce checks, keyboard dismissal/restoration, reload persistence, failure rollback, conditional status updates, employee/client isolation, and desktop/tablet/mobile/dark screenshots in CI artifacts.

Local validation: standalone PHP/JavaScript tests and syntax checks pass. Local Docker is unavailable (daemon socket denied); Chromium is installed but cannot start under the workspace sandbox (`setsockopt: Operation not permitted`). Live integration/browser results must be taken from CI, not inferred from the local unit checks.

Manual review on staging:

1. As an assigned employee, dismiss Deals, enquiries and the current seller/landlord panel. Reload and verify no flash. Open Manage panels with keyboard and restore individually, then Restore all. Repeat with a second client and employee/device.
2. Try every lead stage; only Offer made and Deal closed show Deals. Change seller → landlord → investor → seller; check hidden preferences return, historical instructions remain editable, and no deal/instruction records change.
3. Block the preference request in developer tools; verify the panel returns, controls unlock and error feedback receives focus. Exercise an anchor into a hidden panel and an existing Edit deal link.
4. Check the six protected panels and their forms, status changes, notes, timeline, reminders, navigation and property-specific reminders. At 1440px, 820px and 390px in light/dark mode, verify no heading/button overlap, horizontal overflow or lost focus indicator.
