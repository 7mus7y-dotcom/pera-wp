# PeraCRM page-flow assessment and agent action plan

**Prepared:** 26 October 2026 (Europe/Istanbul).  
**Repository:** `7mus7y-dotcom/pera-wp`.  
**Location:** `wp-content/plugins/peracrm/docs/crm-page-flow-assessment-and-action-plan.md`.  
**Purpose:** Durable, self-contained handoff for a future agent to reassess the CRM page-flow findings against current code and carry out a bounded remediation plan.

## Read this before taking action

The assessment below is a historical source review of commit `e65b22e626081ebc47149cffb9ff50740cdd1f02`, completed 25 October 2026. It contains **46 distinct open findings: 6 P1, 31 P2 and 9 P3**. Its source links deliberately stay pinned to that commit. Shared symptoms are not additional findings.

At handoff preparation, `main` was `7c1d13c9fad2442126183bbbb2a5b7efa2db1a30` (merge of PR #1485). The six P1 defects have **not been revalidated against that newer code by this handoff**. Newer email transport work does not automatically close these findings. The future agent must establish what remains reproducible at its own starting commit rather than apply a stale patch or repeat historical test claims.

This change saves documentation only. It does not implement fixes, migrate roles/data, activate sending, merge or deploy anything. Source confirmation is not proof of production exploitation, affected record count or current deployed state.

## First remediation batch: six immediate findings

One focused implementation prompt and one PR are appropriate if reassessment confirms the findings still apply. Keep changes limited to the shared authorization/privacy boundaries and the tests/documentation needed to verify them. Do not bundle the other 40 issues, redesign pages or perform an unrelated cleanup. If a shared correction necessarily changes another finding, describe the dependency and verify it; do not silently expand scope.

| Order | ID | Assessment at audited baseline | Required correction | Minimum acceptance evidence |
|---|---|---|---|---|
| 1 | CD-01 | Deal updates authorize a submitted client but do not verify that the separately submitted deal belongs to it. Both AJAX and native update paths are implicated. | Load the deal, derive its actual client, check canonical edit authority and reject every route/body/record mismatch before any write or success audit. Use a common guarded mutation path. | Employee authorized for A submits B's deal ID through A via AJAX and native POST: denied, B unchanged, no success audit. Legitimate A update works. |
| 2 | WA-01 | WhatsApp click-log AJAX list/delete falls back to general CRM access after the strict operational-log policy returns false. | Honor explicit canonical denial; only use a narrowly equivalent fallback when the canonical helper is absent. Align page, AJAX read and delete policy. | Employee with general CRM access and a valid nonce receives 403 for list and delete; rows unchanged. Authorized log operator succeeds. |
| 3 | OV-01 | Reminder hydration can read out-of-scope client names and note previews after reassignment or assignment conflict. | Intersect reminders with canonical client-read scope before hydrating private fields; apply the same scope to counts and destinations while retaining legitimate full scope. | Former advisor retains a reminder but receives no client title, reminder text or private-note preview in visible/hidden HTML or task responses. Repeat for conflicting metadata and empty scope. |
| 4 | CL-01 | Normal lists apply assignment restriction only to the employee role, bypassing canonical assigned/empty scope for other CRM-enabled roles. | Use explicit canonical full/assigned/empty scope for every list query, response and row hydration; general CRM admission is insufficient. | Custom scoped and empty-scope CRM users cannot receive forbidden rows, hidden markup, counts or AJAX tab data; employee and full manager/admin access still work. |
| 5 | CD-02 | AJAX profile saves omit the dormant-state permission enforced by the native action. | Enforce restricted transitions in a shared mutation service and align UI options with actual permissions. Preserve the existing role policy. | Assigned employee cannot set dormant through either submission path; metadata unchanged. Authorized manager can through both. |
| 6 | NL-01 | Create-lead error redirects contain contact details and private notes in query strings. | Retain failed fields privately using same-request rendering or a short-lived actor-bound opaque recovery reference; do not put form contents in URLs. | Validation, duplicate and storage-error responses/redirects contain none of the synthetic personal fields. Correcting/retrying retains data for the same actor only. |

### Code entry points to reassess

Paths below are relative to `wp-content/plugins/peracrm/`; search by symbol on current main because line numbers can move. Follow every caller and fallback, not just the rendered form.

- **CD-01:** `inc/frontend-data/crm-client-view.php` — `pera_crm_client_action_ajax()` deal branch; `inc/admin/actions.php` — native deal update handler; `inc/repositories/deals.php` — `peracrm_deals_get()` / `peracrm_deals_update()`. Check create/delete siblings for reuse without assuming an unrelated defect.
- **WA-01:** `inc/whatsapp-logs.php` — `peracrm_whatsapp_logs_user_can_access()`, `peracrm_ajax_whatsapp_logs_table()`, `peracrm_ajax_whatsapp_delete_logs()`; `inc/helpers.php` — `peracrm_can_view_operational_logs()`; `inc/admin/actions.php` — `peracrm_admin_user_can_manage()`; `inc/views/pages/crm-logs.php` and nonce/asset enqueue paths.
- **OV-01:** `inc/frontend-data/crm-data.php` — `pera_crm_get_task_rows()` and reminder/count consumers; `inc/repositories/reminders.php`; `inc/repositories/notes.php`; `inc/services/client_scope_service.php`.
- **CL-01:** `inc/frontend-data/crm-data.php` — normal listing query/filter/row hydration; `inc/services/client_scope_service.php`; `inc/helpers.php`; normal GET, global-search and AJAX tab paths.
- **CD-02:** `inc/frontend-data/crm-client-view.php` — AJAX profile branch; `inc/admin/actions.php` — `peracrm_handle_save_client_profile()` and reassignment permission helper; `inc/helpers.php` — `peracrm_client_update_profile()`; `inc/views/pages/crm-client.php` profile and danger controls.
- **NL-01:** `inc/frontend/routing.php` — `pera_crm_build_create_lead_redirect_url()`, duplicate redirect builder and `pera_crm_handle_new_lead()`; `inc/views/pages/crm-new.php` and any debug/error logging of request URLs.

## Execution plan for the future agent

### 1. Establish a fresh baseline and preserve other work

1. Read applicable `AGENTS.md` and repository instructions. Inspect worktree/branch state before changing anything. Preserve unrelated or unfinished work; use an isolated branch/worktree when appropriate.
2. Fetch latest `main`, record the exact starting commit and inspect open/recent PRs touching these paths to avoid duplicate or conflicting work. Branch from a verified baseline, not the old audit commit.
3. Read this handoff and the current repository documents: `authorization-hardening-deployment.md`, `client-view-admin-audit.md`, relevant impersonation write-path/verification notes, and `email-campaigns-implementation-plan.md`. All are in this directory. Historical documentation can describe intent without proving all callers comply.
4. Reproduce each P1 with synthetic fixtures and identify the current endpoint/service/repository path. Classify each as confirmed, already corrected with evidence, superseded, or not reproduced with a stated limitation. Retain stable IDs and record current source references.
5. Confirm canonical full/assigned/empty scope, real actor versus effective identity, authoritative blog and existing state-transition/log permissions. Do not infer authority from a role label, nonce, ownership form field or visible navigation.

### 2. Implement a bounded correction

- Share authorization and transition checks across AJAX and native commands where practical, without broadly refactoring unrelated CRM behavior.
- Authorize the stored record being changed, and validate the relationship between all submitted identifiers before writing. Denials must not mutate the target or emit a success event.
- Filter authorized populations before private field hydration and before calculating exposed totals. Do not merely hide forbidden rows with CSS or remove detail links.
- Keep role/capability and impersonation policy intact. Do not fix denied actions by granting broader permissions, migrating real accounts or weakening canonical checks. Preserve the real audit actor separately from effective working scope.
- Resolve all reads and writes on the intended CRM/log blog. Test single-site and multisite, including conflicting assignment metadata and empty scopes.
- For failed lead forms, prefer an existing secure error-retention pattern if one is available. If using opaque references, bind them to the actor and purpose, bound their lifetime/storage, reject cross-user access and avoid shared caches. Retain no more data than the correction requires. Never serialize private fields back into a URL or diagnostic output.
- Do not inspect, export or erase production logs as a side effect. After stopping the URL exposure, document any owner-led historical logging/retention follow-up separately.

### 3. Verify behavior, not just matching source text

Use meaningful behavioral regressions with synthetic clients, deals, reminders and logs. A test that only asserts a helper name is present does not demonstrate the boundary.

| Boundary | Required cases |
|---|---|
| Client permissions | Ordinary employee, custom scoped CRM role, empty scope, full manager/admin, missing/conflicting assignment metadata, reassignment after page load. |
| Deal integrity | Authorized client A paired with unauthorized deal B; forged or mismatched identifiers; absent deal; valid A edit; both native and AJAX paths; unchanged fields and no success audit after denial. |
| Log operations | Page denial plus direct valid-nonce AJAX list/delete; authorized operation; missing/invalid nonce; unchanged rows after denied deletion. |
| Reminder privacy | Old advisor retains reminder after client transfer; names, reminder text and latest notes absent from visible and hidden responses; counts and linked queues use consistent authorized populations. |
| List privacy | Normal GET, tabs/AJAX, search, counts and hidden table/list variants; preserve authorized paging/filter behavior without exposing forbidden rows. |
| Dormant transition | Native and enhanced profile submissions return equivalent allowed/denied outcomes under the existing permission policy. |
| Lead error retention | Required-field, invalid contact/source/nonce, duplicate and storage-error cases; private data absent from URLs/Location headers; actor isolation, expiry and refresh/retry behavior. |
| Regression/deployment topology | Single-site and multisite, real/effective identity policy, authoritative table/blog, legitimate existing staff workflows. |

Run the relevant existing suites and any required CI, plus changed-file PHP syntax and diff checks. Inspect completed results for the exact final commit, including single-site/multisite jobs when applicable. If runtime/browser checks cannot run, report the limitation explicitly and retain an outstanding acceptance state; never substitute an old report's test claim.

### 4. Review and deliver one implementation PR

- Review the final diff against all six acceptance criteria. Fix newly introduced findings and repeat affected checks after the last code change.
- Open one non-draft PR against current `main` when ready, unless a repository instruction requires another status. A draft or explicit incomplete report is appropriate if a material verification prerequisite remains blocked.
- The PR description must map the six IDs to final behavior, tests, current commit/source evidence and any genuine residual risk. If an issue was already fixed, link proof rather than manufacturing another patch.
- Update this document's tracking table below with evidence. “Implemented,” “verified,” “merged” and “deployed” are separate states. Do not mark a finding closed solely because a patch exists.
- No merge, deployment, production role/data/configuration changes, real customer messages or provider traffic are authorized by this handoff. Keep all campaign production gates in their current intentional state.

## Subsequent remediation batches

Reassess all remaining issues before scheduling them. The full assessment below contains each ID's trigger, recommendation and acceptance criteria. Suggested dependency order:

| Batch | Focus | Findings to reassess |
|---|---|---|
| B | Scope/identity, accurate query/time/order/paging and complete task/history retrieval | OV-02–OV-08; CL-02–CL-07; CD-05; PL-01–PL-02 |
| C | Client commands, coherent state refresh, safe duplicate prevention and recoverable creation | CD-03–CD-04, CD-07; NL-02–NL-04 |
| D | Search/mobile parity, reporting correctness, logs and preview accuracy | CL-08–CL-09; PF-01–PF-04; EL-01–EL-02; CA-02 |
| E | Presentation, navigation, localized limits and unavailable-script resilience | OV-09–OV-10; CL-10; CD-06; WA-02; SU-01–SU-02; CA-01, CA-03 |

These later batches are planning guidance, not a requirement to combine them with the P1 implementation PR. Campaign delivery/readiness prerequisites R-01/R-02 in the snapshot remain separate from defect counts and must be reassessed against the current living implementation plan.

## Action tracking

Start state as of this documentation handoff: **not reassessed on newer main; no fix made by this change**. Future agents should update the table with exact commits, test commands/results, PR links and runtime limits rather than overwrite the historical evidence below.

| ID | Current reassessment commit/result | Implementation/PR | Verification | Merge/deployment |
|---|---|---|---|---|
| CD-01 | Pending | None in this handoff | Pending | Not performed |
| WA-01 | Pending | None in this handoff | Pending | Not performed |
| OV-01 | Pending | None in this handoff | Pending | Not performed |
| CL-01 | Pending | None in this handoff | Pending | Not performed |
| CD-02 | Pending | None in this handoff | Pending | Not performed |
| NL-01 | Pending | None in this handoff | Pending | Not performed |

## Full assessment snapshot

The following is the complete revision 3 assessment, preserved with its original IDs, baseline, page flows, urgency register, evidence, proposed acceptance checks and limitations. Statements such as “current,” “latest” or “open” inside the snapshot refer to its 25 October 2026 baseline, not automatically to the future agent's code or production deployment. For ongoing work, update the action tracking above and add dated reassessment evidence.


## PeraCRM — Living page-flow audit

**Last updated:** 25 October 2026 (Europe/Istanbul)  
**Revision:** 3 — All remaining frontend CRM views reviewed; every issue ranked by urgency.  
**Baseline for reviewed views:** `7mus7y-dotcom/pera-wp` main, `e65b22e626081ebc47149cffb9ff50740cdd1f02`.

This is the single report to update as each CRM view is audited. Keep stable finding IDs, append source-pinned evidence, and record fixes only after their implementation and verification are checked. An audit observation is not a claim of a live incident. No findings have been fixed or closed by this report.

### Scope and completion

All routed staff CRM page views and their subscriber/campaign subviews are now covered, plus linked public preference/withdrawal views. WP-admin screens and the separate portal plugin are outside this frontend page-flow review; native admin-post handlers were inspected wherever frontend forms use them. “Complete” means the source flow has been traced, not that production/browser acceptance has passed.

| View | Review status | Distinct finding IDs / shared coverage |
|---|---|---|
| Overview | Complete source review | OV-01–OV-10 |
| Clients/Leads, Inactive, Agent and search | Complete source review; mocked search-race reproduction | CL-01–CL-10 |
| Client detail, properties, notes, tasks, deals and conversation | Complete source review; mocked duplicate-submit reproduction | CD-01–CD-07 |
| Tasks | Complete source review | Shared OV-01/03/04/06/08 and CL-10 |
| Create lead | Complete source review | NL-01–NL-04 |
| Pipeline | Complete source review | PL-01–PL-02; shared CL-02 |
| Performance | Complete source review | PF-01–PF-04; shared OV-04/08 |
| WhatsApp click logs | Complete source review | WA-01–WA-02 |
| Enquiry email logs | Complete source review | EL-01–EL-02 |
| Subscribers: list, profile, import/preview/apply, suppression | Complete source review | SU-01–SU-02 |
| Campaigns: list, new/edit, configuration, history, content/audience/email preview, freeze | Complete source review | CA-01–CA-03 |
| Campaign queue: overview, revisions, preparation, job detail/lifecycle | Complete source review | Shared CA-03; readiness gate retained |
| Public preferences and visible/machine unsubscribe | Complete source review | Intentional readiness block R-01 |

### Urgency and issue register

Every finding remains **open**. Urgency is a recommended remediation order based on the source-confirmed trigger and consequence, not an assertion of an active incident. Shared manifestations are counted once.

| Urgency | Meaning | Count |
|---|---|---:|
| P1 — Immediate | Authorization/integrity or unnecessary private-data exposure; address in the first remediation batch | 6 |
| P2 — High | Material workflow correctness, misleading reporting, lost/hidden work or duplicate customer actions; next corrective release | 31 |
| P3 — Planned | Usability, resilience and clarity; schedule after the affected correctness boundaries | 9 |
| Total | Unique open findings | 46 |

No P0 outage or active exploitation was established. This review is not an exhaustive security certification.

| ID | Urgency | Finding |
|---|---|---|
| OV-01 | P1 | Reminder previews can disclose out-of-scope client details |
| CL-01 | P1 | Normal listing role check bypasses canonical client scope |
| CD-01 | P1 | Deal update does not bind the deal to the authorized client |
| CD-02 | P1 | AJAX profile saves bypass the dormant-state permission |
| NL-01 | P1 | Create-lead errors copy contact details and private notes into URLs |
| WA-01 | P1 | WhatsApp log AJAX permission falls back after explicit denial |
| OV-02 | P2 | Activity feed calls a per-client reader with an options array |
| OV-03 | P2 | Prefetch limit can hide today's tasks |
| OV-04 | P2 | Overdue definitions and timezone cutoffs disagree |
| OV-05 | P2 | Lead counts and linked lists use different populations |
| OV-06 | P2 | Completing a task leaves duplicate rows and counts stale |
| OV-07 | P2 | Manager view-as can collapse full scope into empty results |
| OV-08 | P2 | Excessive dashboard/query work for small previews |
| CL-02 | P2 | Normal listing ignores the effective view-as identity |
| CL-03 | P2 | AJAX tabs leave headers, filters and counts stale |
| CL-04 | P2 | Sorting is page-local; activity sort uses modification time |
| CL-05 | P2 | Pagination clamps only after slicing records |
| CL-06 | P2 | Global and local search match different fields/scopes |
| CL-07 | P2 | New leads strip ignores filters and lacks first-touch evidence |
| CL-08 | P2 | Search lacks mobile view switching; list omits task controls |
| CL-09 | P2 | Pending autocomplete responses restore stale/dismissed matches |
| CD-03 | P2 | AJAX deal saves skip native business validation |
| CD-04 | P2 | Successful panel saves leave related client information stale |
| CD-05 | P2 | Client history limits can hide pending work and older records |
| CD-07 | P2 | WhatsApp composer permits duplicate pending sends |
| NL-02 | P2 | Create lead silently assigns to the real user while viewing as someone else |
| NL-03 | P2 | Create-lead duplicate checks are vulnerable to concurrent submissions |
| NL-04 | P2 | Create lead reports success despite possible follow-on write failures |
| PL-01 | P2 | Pipeline silently truncates records before stage grouping |
| PL-02 | P2 | Pipeline accepts filter state that it does not apply or expose clearly |
| PF-01 | P2 | Performance reporting uses employee-role inference for team scope |
| PF-02 | P2 | Performance rates mix unrelated populations and current-stage snapshots |
| PF-03 | P2 | Response-time warnings classify new untouched leads as already overdue |
| PF-04 | P2 | Attention metrics treat completed reminders as current follow-up coverage |
| EL-01 | P2 | Email log failures are presented as an empty history |
| EL-02 | P2 | Email log history ends at the latest 100 rows |
| CA-02 | P2 | Email preview is not invalidated when the form changes |
| OV-09 | P3 | Repeated panels and implementation commentary |
| OV-10 | P3 | Failure notices and operational status need clearer presentation |
| CL-10 | P3 | Explicit view URLs and rendered presentation disagree |
| CD-06 | P3 | Return navigation loses the originating list and client tab |
| WA-02 | P3 | WhatsApp log pagination can paint an older request over a newer one |
| SU-01 | P3 | Subscriber pagination can land on empty out-of-range pages |
| SU-02 | P3 | CSV intake mapping relies on manual numeric column positions |
| CA-01 | P3 | Campaign form character limits do not match server byte limits |
| CA-03 | P3 | Freeze and queue actions lack a clear unavailable-script state |

#### Immediate remediation order

1. **CD-01:** bind deal IDs to their real clients and authorize that record in every update path.
2. **WA-01:** enforce the operational-log capability for AJAX reads and deletes, including explicit denials.
3. **OV-01 and CL-01:** intersect reminder/list populations with canonical client scope before displaying details.
4. **CD-02:** enforce restricted profile state transitions in a shared mutation service.
5. **NL-01:** remove lead contact/notes from redirect URLs and preserve failed form data privately.

For P2, correct shared identity/time/query semantics first, then command parity/idempotency and complete task/history retrieval, then reporting/preview accuracy. P3 polish follows. Each finding below includes its own acceptance criterion.

### Update log

| Revision | Date | Change |
|---|---|---|
| 1 | 25 October 2026 | Overview audit: ten findings with source evidence and acceptance criteria |
| 2 | 25 October 2026 | Converted into this living report; retained Overview and added Clients/Leads/search with ten findings and a controlled JavaScript reproduction |
| 3 | 25 October 2026 | Completed all remaining frontend page/subview flows; added 26 distinct findings, retained 20 prior IDs, and categorized all 46 by urgency |

### View 01 — Overview

**Page:** `/crm/`  
**Reviewed:** 25 October 2026, Europe/Istanbul  
**Repository baseline:** `7mus7y-dotcom/pera-wp`, main commit `e65b22e626081ebc47149cffb9ff50740cdd1f02`  
**Method:** Source review of the page template, data helpers, routing, navigation, permissions, reminder actions, activity and note repositories, impersonation, push controls and associated CSS/JavaScript. No production records were accessed and no code was changed.

#### Assessment

The overview has the right broad ordering: overdue follow-up and today's tasks come before leads, activity and administrative information. It already incorporates much of the earlier UI direction, so a wholesale redesign is unnecessary.

The main weakness is consistency between what the page displays, what its counts mean, and what the next click returns. There is also an important client-access gap in reminder rendering. Address those functional issues before polishing the layout.

This is a source-based report, not a live browser acceptance test. The code establishes the conditions described below; the presence of affected production records, actual load times, notification delivery, and mobile rendering remain unverified.

#### 1. Entry and loading flow

1. WordPress rewrites `/crm/` to `pera_crm=1`. The absence of a specific view selects the overview.
2. The template loader checks authentication and CRM access. Anonymous visitors are redirected to login; logged-in users without access receive a 403.
3. The page builds its dashboard data before rendering: pipeline counts, overdue count, activity, today's reminders, overdue reminders, recent leads and pipeline-health indicators.
4. It separately obtains the new-lead count and hydrates up to ten recently created leads assigned to the effective user.
5. The plugin renders its own shell, global search, page header, overview sections and navigation. Plugin CSS and JavaScript supply the interactions.
6. The push panel initializes in the browser, inspects service-worker/subscription state and requests authenticated diagnostics.

**Ownership:** The active implementation is plugin-owned. Older documents describing the CRM as theme-owned or located under `mu-plugins` are historical and should not determine a new fix.

#### 2. What the user encounters

| Order | Surface | Purpose and next action |
|---|---|---|
| 1 | Global header | Logo returns to the public website. Search offers CRM matches and submits to the Clients results page. The CRM menu exposes other workspaces. |
| 2 | Overview header | Title, context, Create lead, current date, and a separate New leads summary/action. |
| 3 | View-as controls, for eligible managers/admins | Apply an employee/manager view or return to the signed-in user's view. Selection is persisted in user metadata. |
| 4 | Priority work | Overdue and today's reminder queues. Open a client, inspect its latest-note preview, or mark a reminder done. |
| 5 | New leads | Up to ten leads created within 72 hours and assigned to the effective user; open an individual lead or the filtered list. |
| 6 | Recent leads | Up to twenty recently created records after excluding closed-won clients. This has a different definition from New leads. |
| 7 | Task focus | Repeats overdue/today counts and links to the Tasks workspace. |
| 8 | Latest Activity | Intended to show twenty recent events. Current helper contract is broken; see OV-02. |
| 9 | Pipeline Health | Six linked metrics: overdue, due today, new leads, unassigned, stale and open pipeline. |
| 10 | Workspace notices | Data-availability messages, when generated. These appear after the operational sections. |
| 11 | Reminder Push Notifications | Device enable/disable, diagnostic status, privileged digest trigger, and a configured-server test action. |

**Queue limits:** A reminder panel shows eight reminders if it has at most eight. If it has more, it shows seven plus a “More work waiting” row. Its source list is itself capped at 200. New leads and Recent leads can overlap.

#### 3. Permissions and identity

| Context | Current behavior |
|---|---|
| Employee | Lead panels generally use canonical assigned-client IDs. Reminder queues instead select by the reminder's advisor ID, creating the access gap in OV-01. |
| Manager/admin, normal view | Reminder and recent-lead panels are broadly scoped. The New leads count/panel still means leads assigned specifically to that user. The header's “My view” label does not explain this mixed scope. |
| View as employee | Read helpers generally use the effective employee. Writes remain attributed to the real signed-in actor. Push subscription/test actions also use the real account. |
| View as manager | Manager is an allowed selector target, but several readers interpret the target's full client scope as an empty ID list; see OV-07. |
| Mark done/Undo | Login, nonce and reminder authorization are checked server-side. Frontend mutations request client-scope enforcement; privileged reminder managers can operate across that scope. |

The real-actor audit model is useful and should be preserved. “View as” changes the data lens; it is not a new login session or a general transfer of write permissions.

#### 4. Findings

Priority: **P1** = access/security issue to address first; **P2** = functional correctness or material workflow issue; **P3** = usability/clarity improvement. These are audit priorities, not claims of a live incident.

##### OV-01 — P1: Reminder rows can reveal details of a client outside the user's current scope

**Trigger:** A reminder stays assigned to employee A after its client is reassigned to employee B, or conflicting assignment metadata makes the client inaccessible to A.

**Evidence:** `pera_crm_get_task_rows()` selects reminders by `advisor_user_id`. It then reads each client's title and latest internal-note preview without intersecting the results with the canonical client-access scope. The note repository filters by client ID and internal visibility, but does not perform a user-access check itself.

**Effect:** A's overview can render the client name, reminder text and latest-note preview even though opening the client or completing its reminder may be denied. A collapsed preview still puts the note text in the HTML.

**Recommendation:** Apply the same effective-user client policy before reading names or notes, and use it consistently in counts and destination lists. Decide explicitly how outstanding reminders should be handled when ownership changes. Preserve legitimate full management scope.

**Acceptance:** Reassign a client while retaining its old reminder. The former advisor must receive neither its title nor note text in dashboard HTML, counts, search or follow-on task responses. Repeat with conflicting assignment metadata and an empty assigned scope.

**Sources:** [task hydration](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend-data/crm-data.php#L743), [reminder query](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/repositories/reminders.php#L628), [note reader](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/repositories/notes.php#L147), [canonical client policy](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/services/client_scope_service.php#L17).

##### OV-02 — P2: Latest Activity calls a per-client reader as though it were a feed reader

**Trigger:** The overview builds its activity feed.

**Evidence:** `pera_crm_fetch_recent_activity()` passes an associative options array to `peracrm_activity_list()`. The repository function expects a scalar client ID and casts its first argument to an integer. A nonempty array becomes `1`, so the supposed feed query targets client ID 1 with the reader's default limit. The supplied `party_ids`, ordering and requested limit are not interpreted. The normalizer also expects `type`/`summary` alternatives rather than the repository's `event_type`/`event_payload` fields.

**Effect:** The panel can be empty despite real activity, or return timestamps from the wrong client with blank event details. An employee's intended allowed-client list is not applied by this call. If client 1 has activity, its timestamps may reach an unrelated user's overview. Do not fix this by merely adding another argument to the existing call.

**Recommendation:** Introduce or use an explicit scoped recent-activity feed API, map the actual event schema to readable summaries and link each event to its permitted frontend client page.

**Acceptance:** Interleave events from allowed and forbidden clients. Return the latest twenty permitted events in stable order, with meaningful type, summary and client links. An empty scope must return no events.

**Sources:** [caller and normalizer](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend-data/crm-data.php#L378), [actual reader signature](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/repositories/activity.php#L35).

##### OV-03 — P2: Today's tasks can disappear behind the 200-row prefetch limit

**Trigger:** An employee has 200 older pending reminders ahead of today's reminders.

**Evidence:** The employee today path requests the first 200 open reminders in ascending due order using range `all`, then filters those rows to today in PHP. The Tasks workspace also takes 200 open rows before applying its date filter.

**Effect:** Today's queue and its health metric can show zero while valid reminders exist. “Open task workspace” is not a reliable escape route because the destination has the same prefilter limit. Badges derived from fetched arrays are not reliable total counts above that limit.

**Recommendation:** Filter and count in storage before pagination. Return explicit total, displayed count and continuation metadata. Fetch only the small preview needed by the overview.

**Acceptance:** Seed 201 older reminders plus reminders today and tomorrow. The today panel and its destination must still show today's work, and totals must not silently stop at 200.

**Sources:** [overview query/filter](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend-data/crm-data.php#L759), [destination prefetch](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend-data/crm-data.php#L899).

##### OV-04 — P2: “Overdue” changes meaning across panels and links

**Trigger:** At 15:00, a pending reminder was due today at 10:00.

**Evidence:** The overview compares against the current instant, so the reminder appears in both Overdue and Today's tasks. The Tasks destination calls only reminders before today's midnight overdue, so the same reminder disappears when following the overview's overdue health link.

There is a second time issue: `peracrm_reminders_range_clause()` passes `current_time('timestamp')` through `wp_date()` with the site timezone. On a non-UTC WordPress site this can apply the offset twice. The employee overview later filters against the real current timestamp, but its count helper uses the repository cutoff directly; the overdue metric and preview can therefore disagree.

**Recommendation:** Define whether overdue means “past the due instant” or “before today.” Use one definition and one real timestamp across overview, counts and Tasks. Explicitly document whether today's queue includes already overdue reminders.

**Acceptance:** Compare a reminder just before now, just after now, yesterday and around midnight under both UTC and Europe/Istanbul. Every linked result must follow the same policy.

**Sources:** [overview cutoff](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend-data/crm-data.php#L750), [Tasks classification](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend-data/crm-data.php#L987), [repository time range](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/repositories/reminders.php#L728).

##### OV-05 — P2: Lead counts and their destination lists use different populations

**New leads:** The header/panel counts leads assigned to the effective user, created in the last 72 hours, excluding several closed states. Its link opens `type=leads&filter=new72`, whose destination filters recent leads across the viewer's list scope without applying that same assignment requirement. Managers can click a personal count and land on a wider team list. The destination also excludes dormant records through its base filter, while the overview's new-lead helper does not explicitly exclude dormant engagement. Its 72-hour cutoff uses an offset timestamp against GMT creation timestamps, introducing a further timezone boundary mismatch.

**Pipeline Health:** Health aggregation starts with all scoped `crm_client` posts and excludes only the `deal_closed`/`deal_lost` stages. It does not first apply the destination's closed-won-client and inactive-record exclusions. Unassigned/stale counts also use that broader population, while their destination filters do not consistently restrict to the same open stages.

**Effect:** A user cannot trust that clicking “N leads” reveals those N records.

**Recommendation:** Define shared filters for each metric and use them for its count, preview and full list. Specify personal versus team scope in the label. Use actual Unix timestamps for duration comparisons.

**Acceptance:** For manager, employee and view-as contexts, compare count IDs with destination IDs using active, dormant, closed-won, lost, unassigned and 72-hour-boundary records. Populations must match, apart from explicit preview pagination.

**Sources:** [new-lead definition](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend-data/crm-data.php#L499), [health population](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend-data/crm-data.php#L1131), [destination filters](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend-data/crm-data.php#L1511).

##### OV-06 — P2: Mark done leaves duplicate rows and summary counts stale

**Trigger:** Mark a reminder done, especially one shown in both overdue and today.

**Evidence:** On AJAX success, JavaScript removes only the submitted row. It does not update other occurrences with the same reminder ID, summary badges, health metrics, empty states or preview continuation. Undo restores only that stored DOM row.

**Effect:** A completed reminder can remain actionable elsewhere on the same page. Counts can still say work remains after the last visible reminder is completed. The user must reload to reconcile the page.

**Recommendation:** Refresh all affected queues and metrics from an authoritative response, or reconcile every instance by reminder ID and update counts/empty states consistently. Preserve Undo and the native POST fallback.

**Acceptance:** Complete and undo a reminder visible twice; complete the last item in a queue; complete an item while more work exists. Rows, totals and empty states must agree without reload.

**Sources:** [success and Undo handlers](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/assets/frontend/crm.js#L497), [authorized endpoint](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/services/reminder_service.php#L98).

##### OV-07 — P2: Viewing as a manager can produce an incoherent empty dashboard

**Evidence:** Managers are explicitly valid view-as targets and normally have full client scope. The allowed-ID helper returns IDs only for scope type `assigned`; it maps `full` to an empty array. Impersonating readers then use that array as a restrictive scope, while reminder readers can still return reminders owned by the manager.

**Effect:** The selected manager view can show reminders alongside empty lead/activity/health populations. Global search has the same full-to-empty behavior during impersonation.

**Recommendation:** Resolve whether manager view-as means the manager's real full view or only personally assigned records. Represent full, assigned and empty distinctly throughout; do not infer an empty scope from an empty ID array alone.

**Acceptance:** View as a manager with personal assignments and full access. All dashboard sections and search must implement the chosen policy consistently.

**Sources:** [allowed target policy](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/impersonation.php#L93), [scope collapse](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend-data/crm-data.php#L1253), [search scope](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/services/header_search_service.php#L37).

##### OV-08 — P2: The overview performs excessive work before rendering small previews

The template builds the full dashboard even when reused for Clients or Tasks. On Overview, the new-lead ID/count work is requested repeatedly. Each reminder collection hydrates up to 200 rows and queries latest notes per distinct client before the template displays at most eight. Pipeline Health loads all scoped records and calls health readers per record without first using the available batch health-cache priming helper. Stage/KPI data is also computed although its returned KPI/pipeline arrays are not rendered in this overview.

**Effect:** Work grows with database size rather than the small number of visible rows. The source establishes repeated reads and unbounded populations; no measured production latency is claimed.

**Recommendation:** Build view-specific data, share request-local results, fetch previews separately from totals, batch note/health reads and remove unused dashboard computation after verifying consumers.

**Acceptance:** Measure SQL count and duration with representative data. Increase record volume substantially and confirm visible preview queries remain bounded. Ensure Clients and Tasks do not build unrelated overview data.

**Sources:** [unconditional dashboard build](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/views/pages/crm-overview.php#L28), [dashboard orchestration](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend-data/crm-data.php#L1045), [note loop](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend-data/crm-data.php#L700), [batch health reader](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/health.php#L19).

##### OV-09 — P3: Repetition and implementation commentary lengthen the working page

New leads appears in the header and again in its own queue, followed by an overlapping twenty-row Recent leads queue. Task focus repeats counts and another Tasks link. Text such as “kept quieter than the live queues above” and “so the next click is obvious” describes design intent rather than helping staff decide what to do.

**Recommendation:** Keep a compact actionable summary, one primary lead queue with a clear scope, and a secondary Recent leads view or collapsed section. Remove the repeated Task focus panel. Replace commentary with concise operational labels. Move useful exception metrics above passive activity after their counts are fixed.

This is a UX recommendation, not a broken-code finding. Keep the overdue-first structure and avoid changing every card or control simply to implement it.

**Source:** [overview section markup](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/views/pages/crm-overview.php#L243).

##### OV-10 — P3: Failure and operational status need clearer placement

Data-unavailable notices are rendered after the main queues, so an empty queue can appear reassuring before the user reaches the warning. Reminder admin-post fallback redirects with `peracrm_notice`, but the overview does not render that notice; it only explicitly reads push-test notices. The push panel shows implementation details such as cron configuration and service-worker scope to ordinary staff, and device subscription presence alone is presented as “enabled,” which does not establish end-to-end delivery health.

**Recommendation:** Put a concise availability warning beside affected queues, render reminder fallback success/failure messages, keep a simple staff push status and place technical diagnostics behind an appropriate details/management surface. Distinguish device permission/subscription from verified delivery.

**Acceptance:** Disable JavaScript and complete a reminder; simulate a data-source failure; test unsupported/blocked push and a browser subscription absent from the server. Staff should get a clear result and next action.

**Sources:** [page notices and push panel](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/views/pages/crm-overview.php#L449), [native reminder response](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/admin/actions.php#L1891), [push state](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/assets/frontend/crm-push.js#L245).

#### 5. Recommended page flow after correction

1. Compact header: current data scope, date, search and Create lead. Keep the view-as controls clear for authorized users.
2. Summary actions: overdue, due today, new leads; management exceptions such as unassigned/stale when useful. Every number opens the exact population it counts.
3. Priority queues: overdue and today, with consistent time rules, accurate totals and small previews.
4. One main lead queue: clearly personal or team-scoped, with a full-list action. Make Recent leads secondary.
5. Readable, scoped activity with client links.
6. Simple notification status; technical diagnostics separated from daily work.

Preserve the existing plugin-owned shell, native fallback forms, real-actor audit, empty-scope denial and responsive stacking. CSS specifies that the priority columns stack at 1024px and below; actual mobile layout, focus order, contrast and overflow still require browser checks.

#### 6. Suggested correction order

| Batch | Scope | Why |
|---|---|---|
| 1 | OV-01 | Close client-detail disclosure through reminder previews. |
| 2 | OV-02 | Repair the activity feed contract and scoped rendering. |
| 3 | OV-03, OV-04, OV-05, OV-07 | Establish shared population, time and identity rules before altering presentation. |
| 4 | OV-06 and fallback notices | Keep successful actions and their visible results synchronized. |
| 5 | OV-08 | Reduce query work once shared queries have correct semantics. |
| 6 | OV-09, remaining OV-10 presentation | Simplify the dashboard without masking functional issues. |

Each batch should have focused behavior tests around the triggering conditions above. Existing email-campaign CI evidence does not establish overview-flow correctness.

#### 7. Review status

This installment reviews **Overview** and follows its outbound links far enough to check count/filter and permission consistency. It does not claim complete audits of destination workspaces.

The original review sequence is now complete in this revision: **Clients/Leads and search → Client detail → Tasks → Create lead → Pipeline → Performance → WhatsApp/Email logs → Subscribers → Campaigns**, including queue and linked public preference views. Role variants, empty/error states, mutations, return paths, pagination and responsive acceptance requirements are recorded for each; live verification remains outstanding.

##### Verification limits

- Current source was fetched at the pinned commit; findings are grounded in the caller and implementation together, not older scan conclusions.
- No authenticated production browser, live database or provider delivery test was used.
- PHP is unavailable in this execution environment; no PHP/WordPress runtime test pass is claimed.
- Source-level checks can establish the defects and their triggers, but not how many live users or records are affected.
- No fix, merge, deployment, notification or CRM data mutation was performed.

---

### View 02 — Clients, Leads and search

**Routes:** `/crm/clients/`, `/crm/clients/page/N/`, and legacy `/crm/leads/` equivalents. Global search submits to `/crm/clients/?crm_client_search=TERM`.  
**Reviewed:** 25 October 2026, Europe/Istanbul.  
**Baseline:** `e65b22e626081ebc47149cffb9ff50740cdd1f02`, unchanged from the overview review.  
**Scope:** Leads, Clients, Inactive and Agent tabs; list filters; global search preview/full results; table/list switching; sorting; pagination; view-as behavior; outgoing client, WhatsApp and task links.

#### Assessment

The workspace has useful building blocks: a compact header, a 20-record page size, employee assignment filtering, native GET forms and a shared matcher for global-search preview and full results. The primary problem is that different entry paths use different definitions of scope, ordering and page state. The normal list is less strictly authorized than the global-search path.

Ten findings are added below. Cross-page issues already recorded under OV-05, OV-07 and OV-08 remain open and are referenced rather than counted again.

#### Entry and data flow

1. Routing selects the same `crm-overview.php` template used by Overview and Tasks, with `pera_crm_view=leads`.
2. The route requires login and general CRM access. This admission check permits client, lead or deal editing capabilities; it is not an unrestricted-client-read grant.
3. The `type` parameter selects `leads` by default, or `clients`, `inactive`, or `agent`. The main navigation label “Clients” therefore opens Leads initially.
4. The template builds the entire dashboard model, then calls `pera_crm_get_leads_view_data(page, 20, derived_type, list_view)`.
5. Normal listing obtains all candidate IDs in modified-date order, optionally restricts employees, derives closed-won clients, and applies lifecycle, stage, advisor, quick, text and client-type filters in PHP.
6. Global-search mode instead calls the shared SQL matcher. It searches names and email/phone metadata across permitted record categories. It deliberately bypasses the normal tab, lifecycle and list filters.
7. The helper counts filtered IDs and slices 20 IDs for the requested page. It hydrates health, ownership, budget, dates, WhatsApp and next-task details. Normal-list items are then reordered by last activity within that page.
8. PHP renders both the table and a hidden structured list. JavaScript chooses the normal-list presentation using device width and saved local preference.
9. Changing a type tab on the first `/crm/clients/` route fetches a full HTML page but replaces only the workspace subtree. Filters and pagination normally use full navigation.

#### Record categories and controls

| Surface | Current semantics |
|---|---|
| Leads | Records not classified as closed-won clients, excluding engagement `closed` or `dormant`. This does not automatically mean every record has an open pipeline stage. |
| Clients | Closed-won classification, again excluding inactive engagement. This is based on deal history rather than simply a profile type named client. |
| Inactive | Closed or dormant engagement across derived lead/client categories. |
| Agent | Active records whose stored client type is `agent`; this can overlap the Leads population. |
| Global search | Permitted matching records across categories, including inactive records; minimum two characters. Dropdown shows up to eight. |
| Header filters | Local text query, stage, client type and advisor; quick filters can be carried from overview links. Advisor selection is hidden/locked during view-as. |
| New leads strip | Up to eight recently created, assigned records on Leads pages, computed separately from the main filters and pagination. |
| Table | Name/WhatsApp, status/stage, source, advisor, health, budget, next task, last activity and created date. Name and row open client detail; Add task links to its next-actions anchor. |
| Structured list | Name/WhatsApp, stage, source, advisor, last activity, engagement/disposition and open action. Budget, health and next-task controls are omitted. |

Tab overlap is documented here as current semantics, not automatically a bug. The product should make clear whether these are overlapping views or mutually exclusive categories.

#### CL-01 — P1: Normal listings use a role check instead of the canonical client-access policy

**Trigger:** A non-employee user has general CRM access but lacks `manage_options` and `peracrm_manage_all_clients`. Examples include a custom role with `edit_crm_clients`, a deal-only role accepted by the route gate, or a manager whose full-access capability has been removed.

**Evidence:** The normal list obtains allowed IDs, but applies `post__in` only if `pera_crm_user_is_employee(current_user_id)` is true. It does not inspect canonical scope type for other users. The canonical policy can return assigned or empty scope for these users; that decision is ignored by the list query. Subsequent row hydration does not reauthorize individual clients.

**Effect:** These accounts can receive names, ownership, budget, WhatsApp contact links and next-task note details from records outside their authorized client scope. A direct detail-page denial does not undo list disclosure. Default fully privileged managers/admins are not the affected case. Whether such restricted custom accounts exist in production is unverified.

**Correction:** Apply explicit full/assigned/empty canonical scope before querying or hydrating every list mode. General CRM admission and a role name must not substitute for client-read authorization.

**Acceptance:** Exercise custom assigned scope, empty scope, deal-only access, employee, and full manager/admin access. Check table, hidden list HTML, counts, AJAX tab responses and direct URLs. Forbidden records must never enter responses.

**Evidence:** [list scope branch][C1], [canonical policy][C2], [route capability admission][C3].

#### CL-02 — P2: Normal listing ignores the selected view-as user

**Trigger:** A manager/admin selects an employee view on Overview, then opens Clients normally.

**Evidence:** The normal-list helper uses `get_current_user_id()` for allowed IDs and its employee check. It does not use the effective CRM identity. Global search does use the effective identity. The shared header nevertheless hides the advisor selector and says the results already follow the selected view.

**Effect:** The ordinary list can show the real manager's broad population while search returns only the impersonated employee's matches. The view-as banner/reset is restricted to Overview, making the persistent context less visible here. The separate New leads strip can follow the effective employee while the main table follows the real user.

**Correction:** Use one explicit effective read scope for list, search, strips, counts and pagination. Preserve real-actor attribution for writes. Keep a visible scope indicator wherever persisted view-as is active.

**Acceptance:** View as employee A and switch tabs, filter, search, paginate and reload. Every path must use the same intended population. Also cover the manager-target empty-scope issue already tracked as OV-07.

**Evidence:** [normal-list identity][C1], [search identity][C4], [locked-filter claim][C5]. This extends OV-07; it is a distinct normal-list identity defect.

#### CL-03 — P2: AJAX type switching leaves the header and filters on the previous tab

**Trigger:** Open Leads, select Clients/Inactive/Agent, then use the header filter form.

**Evidence:** The fetched response replaces only `[data-crm-clients-workspace]`. The page title, count, description, filter controls, hidden filter-form `type`, and browser document title live outside it. Type links also start from the base Clients URL and clear current filters. Browser Back/Forward uses the same partial replacement.

**Effect:** The URL and records can say Clients while the header still says Leads and shows the old count. Applying the unchanged filter form can submit `type=leads` and switch back unexpectedly. This affects the enhanced first-page route; legacy `/crm/leads/` and numbered-page routes generally navigate normally.

**Correction:** Update the header/filter state with the workspace, or use full navigation. Establish an explicit policy for retaining filters on a tab change and reset the page deliberately. Update document title and announce the new scope.

**Acceptance:** Leads → Clients → apply stage filter → Back → Forward. At every step compare URL, hidden form values, selected controls, title, total and records.

**Evidence:** [workspace-only replacement][C6], [filter-form type][C5], [type links][C7].

#### CL-04 — P2: Sorting is page-local, and Last activity uses the wrong field

**Evidence:** Candidate IDs are sliced into pages before normal-list sorting by `last_activity_ts`. JavaScript sorts only rows already in the current table. The Last activity header uses `data-sort=updated`; rows populate that attribute from `updated_ts` (post modification), even when the displayed cell shows a different activity timestamp.

**Effect:** Page 2 can contain a more recently active record than page 1. Clicking Name, Budget or Next task does not order the complete result set, and clicking Last activity can contradict the visible dates. Sorting is lost on navigation and does not order the hidden structured list.

**Correction:** Define server-side ordering across the full filtered result set before slicing, with a stable ID tie-breaker. Store sort/direction in the URL, carry them through pagination and use the actual displayed activity field. If any control is intentionally page-local, label it explicitly.

**Acceptance:** Use at least 45 records with divergent modification and activity dates. Verify global order across three pages for activity/name/budget/task, including ties and missing values.

**Evidence:** [slice and hydration][C8], [post-slice sort][C9], [table sort attributes][C10], [DOM-only sort][C11].

#### CL-05 — P2: Out-of-range pagination slices first and clamps the reported page afterward

**Trigger:** Visit `/crm/clients/page/9/` when the result set has only two pages, or revisit a saved page after records disappear from its scope.

**Evidence:** Offset IDs are sliced using requested page 9. Only the returned `current_page` is clamped to the total. The template therefore renders the last page's range/current-page indicator using empty items from the invalid slice.

**Effect:** A valid population can display an empty-state message alongside a nonzero total and a valid-looking page number.

**Correction:** Clamp or reject the page before slicing. Redirect to the canonical valid page where appropriate, and make empty-state text distinguish no matches from an invalid page.

**Acceptance:** With 25 matching records, page 9 must redirect/render the actual five items on page 2, or give an explicit invalid-page response. Repeat for zero matches and changed permissions.

**Evidence:** [slice][C8], [late clamp][C9], [display range][C12].

#### CL-06 — P2: The two search boxes have different matching and scope semantics

**Trigger:** Search a phone number using the global header versus the page's “Search clients” filter.

**Evidence:** Global search matches title plus canonical/legacy email and phone fields. The local `q` filter checks only title and email. Global search spans permitted categories and ignores the list filters; local search stays inside the selected category/filter set. The visual labels do not explain these differences. Global AJAX search explicitly enters the target-blog context, while the full-page data helper does not wrap its whole read in that context; cross-subsite parity also needs testing on this multisite installation.

**Effect:** The same phone query can find a record above and show no records below. Global search can return an inactive record absent from the local list. The latter may be intentional; the phone-field mismatch is an implementation inconsistency.

**Correction:** Share the matching fields and scope rules where intended. Distinguish “Search all permitted records” from “Filter this list,” and make search context explicit. Preserve the shared global preview/full-results matcher.

**Acceptance:** Test name, email and phone using both inputs, including legacy contact fields, inactive records, current filters and origin/target blog variants.

**Evidence:** [global matcher][C13], [local query filter][C14], [target-blog AJAX wrapper][C15]. The multisite point is a conditional verification concern, not a confirmed production mismatch.

#### CL-07 — P2: The New leads strip ignores list filters and does not establish first-touch status

**Evidence:** The strip is populated separately whenever this is the Leads tab outside global search. Its helper uses assignment and creation within 72 hours, with some closed-state exclusions. It receives neither the current list text/stage/advisor/type/quick filters nor a first-contact/activity condition.

**Effect:** A filtered list can show zero results while an unrelated New leads strip still appears above it. The same strip repeats on numbered pages. Already contacted leads can still appear under copy claiming they need “first-touch review”; an old lead assigned today can be absent because the window uses creation time.

**Correction:** Either label this explicitly as an independent personal recent-leads queue, or apply the active list scope and filters. Use real first-touch evidence before claiming first-touch work. Avoid repeating a dashboard queue above every result page.

**Acceptance:** Apply filters that exclude every strip item; add activity to a new lead; assign an older lead today; visit page 2. Display and wording must match the chosen definition.

**Evidence:** [strip population][C16], [creation-based definition][C17], [strip wording][C18]. Related count/window mismatches remain tracked under OV-05.

#### CL-08 — P2: Global-search mode bypasses the responsive list switch, and list mode loses task controls

**Evidence:** The normal table/list toggle is inside `if (!is_header_search)`. Global-search results render the table plus a hidden list, but have no toggle for `initViewToggle()` to initialize, so its mobile/default preference logic does not run. Normal mobile list mode omits budget, health, next-task dates/details and Add task actions that exist in the table.

**Effect:** Global results stay table-based on narrow screens despite the normal page's mobile-list behavior. Staff using list mode must open records to check the next task or add one. The table remains an available scrollable presentation; this is not a claim that mobile search is entirely unusable.

**Correction:** Give search results a working responsive presentation choice. Decide which triage fields/actions must remain available in the mobile list; at minimum preserve next-task visibility and a clear task action.

**Acceptance:** At 360px and 767px, test normal and global-search pages with/without a saved preference, including zero results, long names, next tasks and keyboard navigation. Actual layout/overflow still needs browser verification.

**Evidence:** [toggle visibility][C7], [table and hidden list][C10], [view initializer][C19].

#### CL-09 — P2: Pending autocomplete responses can restore stale or dismissed results

**Evidence:** Request identity advances when a request begins, or when input becomes shorter than two characters. It does not advance immediately on every longer query edit. Escape/outside click clears the dropdown without invalidating pending responses.

**Effect:** A response for Ali can appear after the input has changed to Mona but before Mona's debounce fires. A pending response can also reopen the dropdown after Escape. A selection can therefore navigate to an outdated match.

**Local reproduction:** Executed the actual header-search JavaScript block in a Node VM with a minimal synthetic DOM and controlled fetch promises. Both the longer-query debounce race and post-Escape reopening reproduced. This proves the script behavior in the fixture, not real-browser rendering or provider/network behavior.

**Correction:** Invalidate request identity immediately on every query change and dismissal, cancel obsolete requests where possible, and verify that a response still matches the current query/open state before rendering. Preserve keyboard selection and native Enter submission.

**Acceptance:** Resolve responses in reverse order, edit during debounce, press Escape during a request, and click outside. Old or dismissed results must not render or become selectable.

**Evidence:** [request/input/dismissal handlers][C20].

#### CL-10 — P3: URL view preferences and JavaScript presentation do not agree

**Evidence:** PHP parses `clients_view=cards|table` and uses it to style the buttons, but always initially renders the table visible and list hidden. JavaScript instead chooses localStorage or viewport defaults, without reading the URL or server selection. View choice is not explicitly included in the pagination argument list. WordPress may also preserve query arguments from the current URL, so absence from that explicit list alone does not prove every link drops it.

**Effect:** A URL requesting cards can still show a table on desktop. Without JavaScript, the button state can advertise List view while the table remains visible. The same link can display differently across devices or saved browser preferences without a clear precedence rule.

**Correction:** Define URL → saved preference → device-default precedence, honor it in both PHP and JavaScript, and provide a coherent native fallback. Carry the effective choice through navigation where intended.

**Acceptance:** Load each explicit view URL with conflicting storage values and with JavaScript disabled. Verify rendered content, pressed state and subsequent pagination agree.

**Evidence:** [PHP view parsing][C21], [fixed initial visibility][C10], [JavaScript precedence][C19].

#### Shared findings, existing protections and correction order

- **OV-05 remains open:** overview metrics and filtered destination populations do not match. This chapter establishes the destination-side filtering behavior in more detail.
- **OV-07 remains open:** manager view-as collapses full scope to empty in global search. CL-02 covers the separate normal-list use of the real identity.
- **OV-08 remains open:** this shared template builds dashboard data on every list request; list filtering additionally materializes all candidate IDs, global full search obtains all matching IDs, and stale filtering hydrates health before pagination. No production query timings have been measured.
- **Keep:** native GET navigation, global search's shared preview/full matcher, SQL parameterization, strict empty search scope, employee conflict-aware assignment IDs, escaped output, real client links and WhatsApp `noopener noreferrer` behavior.

| Order | Work |
|---|---|
| 1 | CL-01: apply canonical client authorization to every normal-list response. |
| 2 | CL-02 plus OV-07: unify effective-user scope while preserving real-actor audit. |
| 3 | CL-03: synchronize the entire visible page state on tab/history navigation. |
| 4 | CL-04 and CL-05: sort globally, paginate consistently and handle invalid pages. |
| 5 | CL-06, CL-07, CL-09: align matching, queue wording and asynchronous search state. |
| 6 | CL-08 and CL-10: restore presentation parity and explicit preference rules. |
| 7 | OV-08: optimize queries after scope/filter/order semantics are correct. |

#### Verification and remaining scope

Reviewed the active implementations plus the existing client authorization, header-search authorization and client-search markup tests. Those tests cover useful canonical-policy and search behavior, but do not demonstrate correctness of the normal-list role branch, partial-page header state or browser request races described here. PHP is unavailable; these PHP suites were read, not executed.

The controlled Node reproduction for CL-09 passed. Source inspection established the other trigger paths. No authenticated browser, WordPress runtime, live records, actual AJAX/tab screenshots, visual QA or production query profiling was used. No CRM code/data, WhatsApp messages or notifications were changed.

**Revision 3 completion:** Client detail and all remaining frontend views are now covered in the following chapters. The earlier view-level acceptance limits remain in force.

[C1]: https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend-data/crm-data.php#L1460
[C2]: https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/services/client_scope_service.php#L17
[C3]: https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/helpers.php#L624
[C4]: https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/services/header_search_service.php#L37
[C5]: https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/views/partials/crm-header.php#L188
[C6]: https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/assets/frontend/crm.js#L1108
[C7]: https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/views/pages/crm-overview.php#L771
[C8]: https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend-data/crm-data.php#L1653
[C9]: https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend-data/crm-data.php#L1757
[C10]: https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/views/pages/crm-overview.php#L811
[C11]: https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/assets/frontend/crm.js#L1007
[C12]: https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/views/pages/crm-overview.php#L692
[C13]: https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/services/header_search_service.php#L99
[C14]: https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend-data/crm-data.php#L1621
[C15]: https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/services/header_search_service.php#L229
[C16]: https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/views/pages/crm-overview.php#L93
[C17]: https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend-data/crm-data.php#L501
[C18]: https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/views/pages/crm-overview.php#L736
[C19]: https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/assets/frontend/crm.js#L861
[C20]: https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/assets/frontend/crm.js#L192
[C21]: https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/views/pages/crm-overview.php#L22

### View 03 — Client detail

**Page(s):** `/crm/client/{id}/`  
**Status:** Source flow review complete at the report baseline; live acceptance pending.

#### Flow and assessment

Entry → canonical client access check → target-blog data load → identity/contact summary → profile and pipeline status → reminders/notes → linked properties and portfolio → timeline/deals → assignment and danger actions. Quick contact links and the REST WhatsApp composer are separate communication paths. Native forms and JavaScript-enhanced AJAX submissions must produce the same permission and validation decisions.

The page contains the necessary daily work in one place and uses record-level access on entry. The highest risks are mutation parity and record binding. Keep the existing guarded delete/assignment flows; consolidate commands rather than adding another endpoint. The WhatsApp conversation currently loads a bounded latest history; older-message retrieval should be a clearly labelled limitation until a paging flow exists.

**Shared findings:** OV-04 time semantics and CL-02 effective identity also need a deliberate policy here. Reads use canonical real-user authorization; that should not be silently relaxed to expand access while impersonating.

#### Findings

##### CD-01 — P1: Deal update does not bind the deal to the authorized client

**Trigger and evidence:** The AJAX handler authorizes the submitted client ID, then accepts a separate deal ID. The native update handler also checks the submitted client without comparing it to the stored deal’s party ID. The repository updates by deal ID alone; putting the authorized client in the payload does not constrain that UPDATE.

**Consequence:** A CRM editor with access to client A and a valid deal form nonce can submit a deal ID belonging to client B and change its fields. The source establishes the missing binding; this audit did not attempt it against production.

**Correction:** Load the deal first, derive its actual client, and apply the canonical edit policy to that client. Reject mismatched route/body/client/deal IDs in both handlers. Put common validation in a shared service; a nonce is not record authorization.

**Acceptance:** With A assigned to one employee and B to another, submit B’s deal through A’s form via both endpoints. Require 403, unchanged deal fields and no success audit. Confirm legitimate A edits still work.

**Source:** [inc/frontend-data/crm-client-view.php:1779](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend-data/crm-client-view.php#L1779); [inc/frontend-data/crm-client-view.php:1911](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend-data/crm-client-view.php#L1911); [inc/admin/actions.php:2950](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/admin/actions.php#L2950); [inc/repositories/deals.php:320](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/repositories/deals.php#L320).

##### CD-02 — P1: AJAX profile saves bypass the dormant-state permission

**Trigger and evidence:** The normal profile action explicitly rejects dormant status unless the user can reassign clients. The AJAX profile branch passes the status straight to peracrm_client_update_profile, which validates the status value but does not enforce that role restriction.

**Consequence:** An assigned employee can perform a restricted state change through the enhanced form even though the native submission denies it. The dedicated danger-action permission does not protect this second path.

**Correction:** Enforce the same state-transition permission inside a shared profile mutation service used by every endpoint. Also remove unavailable choices from the employee form.

**Acceptance:** Employee attempts to set dormant through AJAX and native POST both fail without changing metadata. Authorized managers succeed through either path.

**Source:** [inc/frontend-data/crm-client-view.php:1785](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend-data/crm-client-view.php#L1785); [inc/admin/actions.php:1645](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/admin/actions.php#L1645); [inc/helpers.php:196](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/helpers.php#L196); [inc/views/pages/crm-client.php:509](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/views/pages/crm-client.php#L509).

##### CD-03 — P2: AJAX deal saves skip native business validation

**Trigger and evidence:** The native create/update handlers run peracrm_validate_deal_submission; native creation also handles the junk-lead restriction. The AJAX deal branch calls the repository directly without that validation.

**Consequence:** An Offer made client can receive a deal without the title, property or positive value required by the normal form path. Which rules apply depends on whether JavaScript intercepts the submission.

**Correction:** Use one authorized deal command for AJAX and native actions, including offer-stage requirements, junk policy and owner validation. Return field-specific errors while preserving inputs.

**Acceptance:** Run identical missing-title/property/value and junk-lead cases through both paths. Require identical decisions and no partial writes on failure.

**Source:** [inc/frontend-data/crm-client-view.php:1911](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend-data/crm-client-view.php#L1911); [inc/admin/actions.php:2879](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/admin/actions.php#L2879); [inc/admin/actions.php:2967](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/admin/actions.php#L2967); [inc/admin/actions.php:3190](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/admin/actions.php#L3190).

##### CD-04 — P2: Successful panel saves leave related client information stale

**Trigger and evidence:** AJAX renders a fresh client page but returns one extracted panel. The browser replaces that panel only. Profile edits therefore leave summary contact links unchanged; note/task/status changes can leave health, counts or timeline elsewhere stale. A render_failed response flag is not handled as a refresh requirement.

**Consequence:** The page can say Saved while displaying the previous phone, status or task state elsewhere. A save followed by a quick action can use obsolete information.

**Correction:** Return and refresh all dependent fragments, or use a single client state response. If mutation succeeds but fragment rendering fails, clearly show that state and offer a full reload without repeating the mutation.

**Acceptance:** Change a phone, add a note and complete a task. Verify summary links, badges, counts, timeline and the edited panel agree before another action. Simulate render failure and confirm no duplicate submission.

**Source:** [inc/frontend-data/crm-client-view.php:1735](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend-data/crm-client-view.php#L1735); [assets/frontend/crm.js:2250](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/assets/frontend/crm.js#L2250).

##### CD-05 — P2: Client history limits can hide pending work and older records

**Trigger and evidence:** The detail loader fetches 20 notes, 50 reminders across all statuses, 20 activity entries, 20 properties per relation and a 50-item timeline. Reminders are ordered by due date ascending before the page separates pending work. Expanding the timeline reveals only the already fetched batch.

**Consequence:** Fifty old completed reminders can crowd out a newer pending reminder while the open count remains positive. Older notes, timeline entries and linked properties have no complete paging path in this view; the displayed property total is a sum of capped lists.

**Correction:** Query active reminders separately with status/date predicates before limits. Add cursor paging or explicit full-history links for histories and properties, and distinguish loaded counts from real totals.

**Acceptance:** Seed 50 completed old reminders plus a pending one, over 20 notes/properties and over 50 events. Pending work remains visible and every older authorized record is reachable with accurate totals.

**Source:** [inc/frontend-data/crm-client-view.php:400](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend-data/crm-client-view.php#L400); [inc/repositories/reminders.php:576](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/repositories/reminders.php#L576).

##### CD-06 — P3: Return navigation loses the originating list and client tab

**Trigger and evidence:** Detail links are rebuilt from fixed /crm/clients/ and /crm/leads/ paths. The clients path itself defaults to the leads view unless the type query state is supplied. Filter, sort and pagination context are not retained.

**Consequence:** Opening a client and returning can land on Leads or reset a carefully filtered list, adding repeated navigation work.

**Correction:** Carry an allowlisted internal return URL or structured list context, and generate the explicit client type for client return/delete destinations.

**Acceptance:** Open records from a filtered Clients page, Agents and search results; return to the same authorized context. Reject external return destinations.

**Source:** [inc/views/pages/crm-client.php:151](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/views/pages/crm-client.php#L151); [inc/frontend/routing.php:72](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend/routing.php#L72).

##### CD-07 — P2: WhatsApp composer permits duplicate pending sends

**Trigger and evidence:** The composer does not disable submit or track an in-flight request. Two submits call POST twice with the same message. The server send function has no per-submission idempotency key. A controlled Node VM with mocked DOM/fetch reproduced two identical pending POSTs; it made no network requests.

**Consequence:** A double click or repeated Enter can send duplicate customer messages. A delayed polling response can also repaint an older conversation after a send refresh because refreshes have no request-generation check.

**Correction:** Lock the composer during submission, preserve text on failure, and add a bounded server idempotency key with explicit unknown-outcome handling. Sequence conversation refreshes so late responses cannot replace newer data. Do not blindly retry provider timeouts.

**Acceptance:** Two rapid submits produce one provider attempt. Test success, validation failure, timeout and accepted-but-local-save-failed states; preserve text and explain uncertainty without encouraging duplicate sends. Reject stale polling responses.

**Source:** [assets/frontend/whatsapp-conversation.js:1](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/assets/frontend/whatsapp-conversation.js#L1); [inc/whatsapp.php:1027](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/whatsapp.php#L1027).

#### Page acceptance checks

- Exercise notes, task create/complete/reopen, status, contact edit, property link/portfolio edits, deal create/update/delete, reassignment and delete with permitted and denied roles.
- Confirm accessible labels, keyboard dialogs, mobile section order and retained input on validation failure in a live browser.
- Verify normal and AJAX handlers use the same authoritative blog and audit actor. Test assignment changes between page load and submit.
- Treat provider acceptance, local persistence and delivery status as separate outcomes; do not send real messages as part of this source audit.

### View 04 — Tasks

**Page(s):** `/crm/tasks/`  
**Status:** Source flow review complete at the report baseline; live acceptance pending.

#### Flow and assessment

Entry → shared overview data/template → effective task scope → pending-reminder fetch → today/overdue/upcoming grouping → table/card presentation → open client or submit Mark done → asynchronous row removal. Task creation and editing continue on Client detail. The page is a work queue, so complete coverage matters more than a preview cap.

The page is wired to real reminder actions, but its full-queue wording exceeds its data contract. The initial 200-row fetch can exclude actionable work; date grouping and counts inherit the Overview inconsistencies. Table and card copies make a one-element success update insufficient.

**Shared findings:** OV-01 (client intersection before rendering), OV-03 (200-row cap before date filtering; also affects this full queue), OV-04 (time/date definitions), OV-06 (duplicate rows/counts after completion), OV-08 (shared dashboard work), and CL-10 (URL/presentation precedence). These are cross-page manifestations, not six additional issues.

No additional distinct issue ID is added here; the shared findings below cover the observed defects. This does not certify the view as defect-free.

#### Page acceptance checks

- Populate over 200 pending tasks spread across overdue/today/future; every authorized task must be reachable by a date/status query with paging.
- Reassign a client while leaving a reminder with the previous advisor; no name/note or task action should escape current client policy.
- Complete a task from table and cards; both copies and all counts update, including when switching presentation.
- Check midnight, timezone and invalid-date boundaries plus empty/failed-load states.

Source: [inc/frontend-data/crm-data.php:904](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend-data/crm-data.php#L904); [inc/views/pages/crm-overview.php:516](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/views/pages/crm-overview.php#L516).

### View 05 — Create lead

**Page(s):** `/crm/new/`  
**Status:** Source flow review complete at the report baseline; live acceptance pending.

#### Flow and assessment

Entry → form with required first name, last name and source; optional contact and opening note → nonce/capability validation → contact/source checks → duplicate-email lookup → post creation → contact/ownership/status/note writes → client detail. Errors redirect back with retained fields.

The form is appropriately small and checks access on submission. Error retention must move out of the URL, and creation should become one recoverable command. Email is optional, so do not assume that the duplicate-email check provides general duplicate prevention.

#### Findings

##### NL-01 — P1: Create-lead errors copy contact details and private notes into URLs

**Trigger and evidence:** The error redirect builder appends submitted name, email, phone, source and notes to /crm/new/ query parameters. Validation, duplicate and creation errors use that builder.

**Consequence:** Private lead information becomes part of browser history and potentially request/access/analytics logs. This is an unnecessary exposure path; the audit did not establish that a third party has received those URLs.

**Correction:** Retain failed form data server-side in a short-lived, user-bound record, or render the failed POST directly. Keep only an opaque error/reference in redirects. Review relevant existing logging/retention with the owner after fixing collection.

**Acceptance:** Submit a synthetic note and contact through every error path. Neither the Location header nor resulting URL contains any submitted personal fields; the authorized user can still correct the form.

**Source:** [inc/frontend/routing.php:144](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend/routing.php#L144); [inc/frontend/routing.php:247](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend/routing.php#L247); [inc/frontend/routing.php:269](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend/routing.php#L269).

##### NL-02 — P2: Create lead silently assigns to the real user while viewing as someone else

**Trigger and evidence:** The form remains reachable during view-as, but creation sets post author, owner and both advisor metadata fields from get_current_user_id rather than making the ownership policy explicit.

**Consequence:** A manager working in an advisor’s visible context can create a lead that belongs to the manager and disappears from the advisor’s list.

**Correction:** Define a consistent write policy: block creation during view-as, or show and validate an explicit assignee. Keep the real actor in the audit trail separately from ownership.

**Acceptance:** Create from normal and view-as contexts. Confirm the visible assignment choice, stored ownership, resulting list and audit actor all match the documented policy.

**Source:** [inc/frontend/routing.php:221](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend/routing.php#L221); [inc/frontend/routing.php:322](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend/routing.php#L322); [inc/frontend/routing.php:349](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend/routing.php#L349).

##### NL-03 — P2: Create-lead duplicate checks are vulnerable to concurrent submissions

**Trigger and evidence:** The handler checks an email for an existing record, then inserts a post and contact metadata in separate operations. There is no command idempotency or atomic uniqueness enforcement in this path. Blank-email leads skip the email check.

**Consequence:** Two requests can both pass the initial lookup and create duplicate leads. A disabled browser button alone cannot prevent retry/concurrency duplicates. Phone-only duplicates are also not detected, although whether to merge those is a product policy decision.

**Correction:** Add per-command idempotency and an atomic normalized-contact claim where uniqueness is intended. Offer a scoped duplicate-review flow for phone-only matches without exposing inaccessible records or automatically merging them.

**Acceptance:** Two concurrent identical submissions yield one record and a consistent response. Test retries, normalized email variants and a deliberately distinct phone-only record.

**Source:** [inc/frontend/routing.php:285](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend/routing.php#L285); [inc/frontend/routing.php:322](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend/routing.php#L322).

##### NL-04 — P2: Create lead reports success despite possible follow-on write failures

**Trigger and evidence:** After wp_insert_post succeeds, metadata/contact/status/note writes are performed without checking their outcomes. The handler then redirects to the detail page as success.

**Consequence:** A lead can be created with missing assignment/status/contact data or a lost opening note, while the user is given no recovery path. This is a source-confirmed error-handling gap; no database failure was induced on production.

**Correction:** Use a coordinated creation service with explicit outcomes and recovery/rollback appropriate to WordPress storage. Verify mandatory assignment/contact/status writes and preserve any failed note securely. Keep all operations on the authoritative CRM blog.

**Acceptance:** Inject failures at each follow-on write. Never report full success for an incomplete record; avoid duplicate retry creation and preserve recoverable input. Repeat on single-site and multisite.

**Source:** [inc/frontend/routing.php:331](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend/routing.php#L331); [inc/frontend/routing.php:349](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend/routing.php#L349); [inc/frontend/routing.php:365](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend/routing.php#L365).

#### Page acceptance checks

Verify duplicate matches outside the actor’s scope do not expose a client name or usable detail link. Test blank optional contact, invalid contact, missing source and a double-submit. Ensure errors focus the form and retain input without putting it into a URL.

### View 06 — Pipeline

**Page(s):** `/crm/pipeline/`  
**Status:** Source flow review complete at the report baseline; live acceptance pending.

#### Flow and assessment

Entry → scope/search/advisor parameters → capped post query → party-status lookup → stage buckets and counts → record cards → client detail; Create lead is the other primary action. This implementation is a read-only board: stage changes occur on the record, not by drag-and-drop.

The stage overview is useful, but its counts must represent a declared population. A missing drag action is not classified as a defect because there is no implemented drag contract. Fix truncated populations and misleading filter state first.

**Shared findings:** CL-02 real/effective identity mismatch and OV-04 date definitions apply to future stage/age filters.

#### Findings

##### PL-01 — P2: Pipeline silently truncates records before stage grouping

**Trigger and evidence:** The board fetches at most 250 recently modified records; search match candidates are separately capped at 500. Stage grouping/filtering happens after the final record cap, with no complete paging or truncation indicator.

**Consequence:** A stage can appear empty or smaller than reality because its older records were excluded before grouping. Board counts describe the loaded sample rather than the full authorized pipeline.

**Correction:** Apply scope/search/stage predicates before pagination. Obtain stage totals independently and provide complete per-stage paging or an explicit limited-preview contract with a full-list destination.

**Acceptance:** With more than 250 authorized records and old records in a rare stage, verify counts and stage-filter results remain complete. Search beyond 500 candidates must have a documented complete path.

**Source:** [inc/frontend-data/crm-data.php:1850](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend-data/crm-data.php#L1850); [inc/views/pages/crm-pipeline.php:1](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/views/pages/crm-pipeline.php#L1).

##### PL-02 — P2: Pipeline accepts filter state that it does not apply or expose clearly

**Trigger and evidence:** The helper reads unassigned, stale, new72 and open_scope into active_filter but does not apply those predicates to its query or grouping. It also accepts query/stage/advisor values while the page lacks a corresponding complete filter form and active-filter summary.

**Consequence:** A shared URL can suggest a restricted pipeline while showing a broader board. Users cannot reliably inspect or reset the active URL state. View-as additionally inherits the real-identity inconsistency tracked in CL-02.

**Correction:** Implement and display supported predicates consistently, or reject unsupported parameters. Provide a visible current-filter summary and reset action; reuse canonical effective scope.

**Acceptance:** For each supported filter URL, compare visible records and totals with the same predicate in Clients. Unsupported values are rejected/reset visibly. Verify employee, manager and view-as behavior.

**Source:** [inc/frontend-data/crm-data.php:1850](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend-data/crm-data.php#L1850); [inc/frontend-data/crm-data.php:2075](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend-data/crm-data.php#L2075); [inc/views/pages/crm-pipeline.php:1](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/views/pages/crm-pipeline.php#L1).

#### Page acceptance checks

Test all known stages, missing/legacy stage values, unassigned and inactive records, search/advisor filters, and narrow-screen navigation. Make bucket definitions visible: the Unassigned / New fallback currently represents missing/unrecognized stage, not necessarily every record without an advisor.

### View 07 — Performance

**Page(s):** `/crm/performance/`  
**Status:** Source flow review complete at the report baseline; live acceptance pending.

#### Flow and assessment

Entry → selected date range → effective-role scope choice → current and previous creation cohorts → current status/source distribution and task/activity signals → independently dated deal count → rates/deltas and attention panels. The view is read-only.

The structure gives a useful starting point for operations reporting. Several metrics are not yet reliable enough for advisor evaluation or conversion decisions: scope, cohort denominators, SLA eligibility and current follow-up coverage need explicit definitions.

**Shared findings:** OV-04 timezone/range calculations and OV-08 repeated per-lead query work also affect this page; profile after metric correctness is established.

#### Findings

##### PF-01 — P2: Performance reporting uses employee-role inference for team scope

**Trigger and evidence:** The page uses the general CRM route gate. The summary chooses personal scope only when the effective identity has the employee role; otherwise it passes scope user zero for team-wide aggregation. That is not an explicit full-report capability or canonical client intersection.

**Consequence:** A CRM-enabled custom/legacy non-employee role without full-client authority can receive team aggregates. Assignment conflicts are not resolved with the same policy used by direct client access. Aggregate exposure is narrower than the raw list exposure in CL-01 but still needs an explicit boundary.

**Correction:** Define personal versus team reporting permissions and enforce them server-side. Build cohorts from canonical authorized IDs, including consistent current/legacy assignment resolution.

**Acceptance:** Use employee, manager and a custom scoped CRM role with conflicting assignment metadata. Team metrics are available only with explicit authority, and personal counts include only authorized clients.

**Source:** [inc/views/pages/crm-performance.php:1](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/views/pages/crm-performance.php#L1); [inc/frontend-data/crm-data.php:3009](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend-data/crm-data.php#L3009); [inc/frontend-data/crm-data.php:2183](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend-data/crm-data.php#L2183).

##### PF-02 — P2: Performance rates mix unrelated populations and current-stage snapshots

**Trigger and evidence:** New leads are records created in the selected period. Qualified/viewing counts use only their current exact stage, so progressing onward removes a lead from earlier counts. Deals created are counted by deal creation date across clients, including older leads, then divided by new leads.

**Consequence:** The displayed deal rate can exceed 100% and is not cohort conversion. Historical comparisons change as current stages change. This can lead to incorrect conclusions about advisor/source performance.

**Correction:** Separate period activity from cohort conversion. Either compute progression events for the same cohort or explicitly label current-stage snapshots and show period deal volume independently. State ownership/date rules next to metrics.

**Acceptance:** Use one new lead and three deals from older leads: period deal count is three, but cohort conversion does not become 300%. Move a qualified lead onward and verify historical progression is preserved or accurately relabelled.

**Source:** [inc/frontend-data/crm-data.php:2728](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend-data/crm-data.php#L2728); [inc/frontend-data/crm-data.php:2868](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend-data/crm-data.php#L2868); [inc/frontend-data/crm-data.php:2916](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend-data/crm-data.php#L2916).

##### PF-03 — P2: Response-time warnings classify new untouched leads as already overdue

**Trigger and evidence:** When no first-action timestamp exists, the helper increments both no_action_24h and no_action_48h without checking the lead’s age. The eligible event set also includes administrative activity, so first internal action should not be presented as verified customer response.

**Consequence:** A lead created minutes ago is counted in both warning buckets. Metrics can reward an administrative event as if it demonstrated contact, depending on the UI interpretation.

**Correction:** Only include leads old enough for each SLA and define whether the measure is first internal action or actual customer contact. Separate still-in-window leads from breached leads; retain the stated evidence basis.

**Acceptance:** Test untouched leads aged 5 minutes, 25 hours and 49 hours, plus reassignment-only and real-contact examples. The two warning buckets and labels reflect age and qualifying evidence.

**Source:** [inc/frontend-data/crm-data.php:2500](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend-data/crm-data.php#L2500); [inc/frontend-data/crm-data.php:2599](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend-data/crm-data.php#L2599); [inc/frontend-data/crm-data.php:2658](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend-data/crm-data.php#L2658).

##### PF-04 — P2: Attention metrics treat completed reminders as current follow-up coverage

**Trigger and evidence:** No reminder uses whether any reminder exists, including closed records. Overdue scanning uses only the first 200 reminders returned for each client.

**Consequence:** A client with only completed follow-up is not flagged as lacking a next task. Large histories can also hide pending overdue work. These attention figures do not reliably represent current actionable coverage.

**Correction:** Count active/pending reminders with database predicates and use existence/count queries independent of historical paging. Align the attention links and labels with those exact predicates.

**Acceptance:** Test no tasks, completed-only tasks, a future pending task and more than 200 old closed tasks followed by pending overdue work. Coverage/overdue counts and destinations agree.

**Source:** [inc/frontend-data/crm-data.php:2816](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/frontend-data/crm-data.php#L2816); [inc/repositories/reminders.php:576](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/repositories/reminders.php#L576).

#### Page acceptance checks

Compare hand-calculated fixtures for all supported ranges and previous periods. Include zero denominators, lead reassignment, multi-deal clients, progressed stages and timezone boundaries. Show what each rate measures and whether historical values are snapshots or event-based facts.

### View 08 — WhatsApp logs

**Page(s):** `/crm/whatsapp-logs/`  
**Status:** Source flow review complete at the report baseline; live acceptance pending.

#### Flow and assessment

Entry → operational-log page permission → authoritative click-log table resolution → paginated rows/page-size controls → selection → explicit delete confirmation → AJAX delete → table reload. These are website WhatsApp click records, separate from sent/received conversation messages.

The page has paging and a deletion confirmation, but page denial and AJAX authorization disagree. Fix the server policy before relying on hidden navigation or the denied-page message. Do not interpret click rows as proof that a customer sent a message or that a CRM message was delivered.

#### Findings

##### WA-01 — P1: WhatsApp log AJAX permission falls back after explicit denial

**Trigger and evidence:** The page correctly requires peracrm_can_view_operational_logs. The AJAX access helper returns true for that check, but when it returns false it falls through to peracrm_admin_user_can_manage, which accepts general CRM access. Both list and delete AJAX handlers use the weaker helper plus a nonce.

**Consequence:** An ordinary CRM user can satisfy the AJAX permission even when the log page denies access, exposing global click logs and permitting deletion. Nonces do not restore the missing capability boundary.

**Correction:** When the canonical log helper exists, return its result directly, including false. If compatibility fallback is necessary, require the equivalent narrow capability. Apply the same policy to page, data and delete actions.

**Acceptance:** An employee with general CRM access and valid AJAX nonce receives 403 for listing and deleting logs; an authorized operator succeeds. Denied deletes change no rows. Test direct admin-ajax requests, not just hidden navigation.

**Source:** [inc/views/pages/crm-logs.php:14](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/views/pages/crm-logs.php#L14); [inc/whatsapp-logs.php:40](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/whatsapp-logs.php#L40); [inc/admin/actions.php:42](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/admin/actions.php#L42); [inc/whatsapp-logs.php:655](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/whatsapp-logs.php#L655); [inc/whatsapp-logs.php:684](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/whatsapp-logs.php#L684).

##### WA-02 — P3: WhatsApp log pagination can paint an older request over a newer one

**Trigger and evidence:** The log script allows repeated loadTable calls and writes each response into the table without a request-generation guard. The newest request state can therefore disagree with the last response to arrive.

**Consequence:** Fast page/page-size changes can show the wrong row batch or selection context. This is a source-based race finding; this audit did not run a browser reproduction for this particular script.

**Correction:** Cancel or disregard superseded reads, clear selection on batch changes and keep page controls synchronized with the accepted response.

**Acceptance:** Delay page 1, request page 2, then resolve page 2 before page 1. Page 2 and its selection state remain visible.

**Source:** [inc/whatsapp-logs.php:505](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/whatsapp-logs.php#L505).

#### Page acceptance checks

Verify read and delete separately with employee/operator/admin roles, empty/missing table, invalid pages and multisite log location. Deletion should clear selection and retain the current valid page. After the authorization fix, verify no weaker alternate registered handler remains.

### View 09 — Email logs

**Page(s):** `/crm/email-logs/`  
**Status:** Source flow review complete at the report baseline; live acceptance pending.

#### Flow and assessment

Entry → operational-log permission → theme enquiry-email table lookup → latest 100 rows → escaped recipient/subject/status display. This is enquiry-email history, separate from the campaign operational queue and SES acceptance/event evidence.

The view is useful for recent enquiry triage but lacks a complete incident-history flow and a dependable unavailable state. Any linked enquiry-transient maintenance control resets enquiry protection state; it is not an email-log deletion action. Retain its administrator/nonce guard.

#### Findings

##### EL-01 — P2: Email log failures are presented as an empty history

**Trigger and evidence:** The page yields an empty list when the theme table helper/table is absent, and casts get_results to an array without presenting a read error separately. It also reads the theme-derived table in the current blog rather than using the explicit authoritative-log resolution used by WhatsApp logs.

**Consequence:** An unavailable table/query can look like no email activity. In a multisite setup a non-authoritative route can read the wrong blog’s history or none; deployment applicability needs runtime verification.

**Correction:** Resolve the authoritative log blog/table explicitly. Distinguish unavailable, query failure and genuine zero rows, with an operator-safe diagnostic and retry path.

**Acceptance:** Missing helper/table and a forced read error show Unavailable, not No logs. Verify the authoritative blog is read from each supported CRM host.

**Source:** [inc/views/pages/crm-logs.php:46](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/views/pages/crm-logs.php#L46); [inc/whatsapp-logs.php:58](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/whatsapp-logs.php#L58).

##### EL-02 — P2: Email log history ends at the latest 100 rows

**Trigger and evidence:** The frontend performs ORDER BY id DESC LIMIT 100 with no pagination or search. It shows basic recipient/subject/status fields but no incident-focused correlation/error-detail path.

**Consequence:** Older delivery incidents become unreachable from this page once 100 newer events arrive. Operators cannot reliably follow a request across related evidence from this view.

**Correction:** Add authorized cursor paging and useful date/status/recipient or request-reference filters. Provide a scoped detail view for available error/correlation fields and clearly distinguish mail acceptance from confirmed delivery.

**Acceptance:** With more than 100 synthetic rows, reach the oldest relevant incident and filter it consistently. Preserve operational-log permissions and avoid exposing sensitive raw provider payloads.

**Source:** [inc/views/pages/crm-logs.php:46](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/views/pages/crm-logs.php#L46).

#### Page acceptance checks

Verify denied access, real zero rows, missing schema/helper, failed reads and more than 100 rows. Check long subjects/recipients on mobile. Clearly describe each status without treating wp_mail acceptance as inbox delivery.

### View 10 — Subscribers and intake

**Page(s):** `/crm/subscribers/`, `/crm/subscribers/{id}/`, `/crm/subscribers/import/`, `/crm/subscribers/suppression/`  
**Status:** Source flow review complete at the report baseline; live acceptance pending.

#### Flow and assessment

List → search/state/language/topic/suppression filters → profile → edit explicit preferences or separately link an authorized matching client → explicit withdrawal. Import → administrator readiness decisions → bounded CSV/mapping → preview batch and evidence/errors → explicit bounded apply batches. Suppression → separately record reason/source/time → latest 50 matching suppression records.

These views have materially stronger boundaries than the legacy CRM mutations: dedicated capability checks, authoritative-blog storage, private/no-store headers, consent separate from profile preferences, and writes disabled during impersonation. Import preview does not grant consent or send email. The limits on upload size/rows/columns and the labelled suppression-history window are intentional constraints.

#### Findings

##### SU-01 — P3: Subscriber pagination can land on empty out-of-range pages

**Trigger and evidence:** The list counts matching subscribers but independently applies the requested page offset. The view prints that requested page and Previous even when it exceeds the filtered total. Preview/history pagination elsewhere also warrants boundary consistency checks.

**Consequence:** A bookmarked high page can appear empty despite matching subscribers existing, requiring repeated Previous navigation.

**Correction:** Clamp or visibly reset invalid pages before querying, preserve filters and show total pages/records. Do not confuse storage failure with an empty page.

**Acceptance:** Filter a late-page bookmark down to one page and request zero, negative and very large pages. Land on a valid page or show a clear reset link with the correct result total.

**Source:** [inc/campaigns/management.php:19](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/campaigns/management.php#L19); [inc/views/pages/crm-subscribers.php:81](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/views/pages/crm-subscribers.php#L81).

##### SU-02 — P3: CSV intake mapping relies on manual numeric column positions

**Trigger and evidence:** The import UI requires zero-based column indexes for fields and evidence, even though the file must contain headings. The initial mapping form offers no header-driven field selector; request feedback is generic rather than a field-level correction flow.

**Consequence:** Operators can mis-map similarly named consent/evidence columns and must spend extra effort diagnosing a rejected preview. The preview and readiness gates reduce the consequence; this is workflow polish, not permission to bypass evidence requirements.

**Correction:** Read bounded headers first and provide labelled selectors plus representative sanitized row samples. Retain mapping on errors and identify invalid fields while preserving the evidence requirements and preview-before-apply flow.

**Acceptance:** Use a CSV with reordered and ambiguous headers. The operator can map and correct fields without counting columns or re-entering all settings; no subscriber/consent writes occur during preview.

**Source:** [inc/views/pages/crm-subscribers.php:28](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/views/pages/crm-subscribers.php#L28); [inc/views/pages/crm-subscribers.php:57](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/views/pages/crm-subscribers.php#L57); [inc/campaigns/routes.php:50](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/campaigns/routes.php#L50).

#### Page acceptance checks

Visit all four subviews as allowed staff, denied staff and view-as. Verify unknown/subscribed/unsubscribed and suppressed states, invalid client linkage and unsubscribe confirmation. Test CSV limits, malformed mapping/evidence, preview paging, interrupted/repeated apply batches and recovery. Never clear suppression or grant consent via profile edits. Runtime intake readiness options were not inspected by this audit.

### View 11 — Campaign drafts, configuration, preview and freeze

**Page(s):** `/crm/campaigns/`, `/crm/campaigns/new/`, `/crm/campaigns/{id}/`, `/crm/campaigns/configuration/` and linked revision/freeze panels  
**Status:** Source flow review complete at the report baseline; live acceptance pending.

#### Flow and assessment

List → create/duplicate → edit incomplete draft → save with expected revision → select bounded public content in the explicit language → isolated desktop/mobile/text preview → save rules and count bounded audience batches → review freeze inputs → materialize bounded batches → publish immutable frozen revision → operational queue. Sender/footer configuration is a separate administrator approval flow. Revision history is immutable and paginated.

The implementation deliberately separates editable drafts, advisory previews, frozen evidence and sending. It preserves failed input, checks optimistic revisions, records the real actor and disables mutation during impersonation. Configuration approval is editorial evidence, not DNS/provider verification. Keep these boundaries intact.

#### Findings

##### CA-01 — P3: Campaign form character limits do not match server byte limits

**Trigger and evidence:** The form uses HTML maxlength values equal to the server’s strlen byte limits. A visible explanation says limits are bytes, but there is no matching byte counter/validator. Multibyte text can satisfy the browser limit and fail the server limit.

**Consequence:** Localized subject/body entries can be rejected unexpectedly. Failed input is retained, which limits data-loss risk.

**Correction:** Add UTF-8 byte counters and matching client validation or explicitly revise the server contract. Keep server validation authoritative and preserve incomplete drafts on failure.

**Acceptance:** Test ASCII, Turkish, Chinese and emoji around each limit. The form predicts the server decision and retains all input after rejection.

**Source:** [inc/views/pages/crm-campaigns.php:22](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/views/pages/crm-campaigns.php#L22); [inc/views/pages/crm-campaigns.php:77](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/views/pages/crm-campaigns.php#L77); [inc/campaigns/drafts.php:20](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/campaigns/drafts.php#L20).

##### CA-02 — P2: Email preview is not invalidated when the form changes

**Trigger and evidence:** previewGeneration increments when another preview is requested, but not when document input or block order changes. A preview response for the old form can be rendered after an edit and labelled with its response-time timestamp. Audience counts do have edit invalidation.

**Consequence:** Staff can visually review obsolete email content while believing the preview represents the current draft. Server freeze/revision checks still protect publication; this is not evidence of a publication bypass.

**Correction:** Invalidate pending preview generations on every document/block change and visibly mark existing previews stale. Bind accepted previews to a form hash/revision and show request-time context.

**Acceptance:** Request preview A, edit to B before A completes and then resolve A. It is ignored or clearly marked stale; only a new B preview can appear current. Repeat for block reorder/removal.

**Source:** [assets/frontend/crm.js:2475](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/assets/frontend/crm.js#L2475); [assets/frontend/crm.js:2524](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/assets/frontend/crm.js#L2524); [assets/frontend/crm.js:2591](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/assets/frontend/crm.js#L2591).

##### CA-03 — P3: Freeze and queue actions lack a clear unavailable-script state

**Trigger and evidence:** Freeze/materialization and queue lifecycle controls are JavaScript-driven type=button actions. Unlike the editor’s structured-block notice, these surfaces do not explain the failure mode when the script is unavailable.

**Consequence:** An operator can encounter apparently actionable controls that do nothing, with no next step. This is a resilience/accessibility gap; it does not justify weakening revision checks or disabled dispatch.

**Correction:** Provide a scoped script-unavailable notice and disable unavailable controls, or add equivalent nonce-protected native forms using the same service. Preserve all capability, version and unknown-outcome protections.

**Acceptance:** Block the CRM script and navigate by keyboard. Each control either works through the same guarded command or clearly explains why it is unavailable. Re-enable scripts and verify normal recovery.

**Source:** [inc/views/pages/crm-campaigns.php:94](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/views/pages/crm-campaigns.php#L94); [inc/views/pages/crm-campaign-queue.php:21](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/views/pages/crm-campaign-queue.php#L21); [inc/campaigns/freeze-ui.php:1](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/campaigns/freeze-ui.php#L1); [inc/campaigns/queue-ui.php:1](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/campaigns/queue-ui.php#L1).

#### Page acceptance checks

Test incomplete drafts, byte limits, content unpublished/deleted/translated between selection and preview, wrong-language IDs, conflicting revisions, duplicate commands and browser navigation with unsaved changes. Confirm frozen review uses persisted state rather than silently freezing unsaved text. Exercise materialization/review failure and recovery with existing actor/version constraints; do not activate sending.

### View 12 — Campaign queue and job detail

**Page(s):** `/crm/campaigns/queue/`, `/crm/campaigns/{id}/queue/`, `/crm/campaigns/{id}/queue/{job}/`  
**Status:** Source flow review complete at the report baseline; live acceptance pending.

#### Flow and assessment

Queue overview → campaign published revisions → idempotent Prepare queue → bounded preparation/recovery → job detail with expected-version commands → schedule intent in Europe/Istanbul converted explicitly to UTC → pause/resume/cancel where allowed → reload authoritative state after uncertain outcomes. Job lists and frozen-revision lists use bounded cursor navigation.

The UI prominently states Dispatch disabled — scheduled work will not send. The underlying dispatch gate returns false. Recording schedule intent or publishing a revision is not permission to activate delivery. The reviewed UI preserves version checks, separate preparation/delivery states and recovery rather than resetting unknown outcomes.

**Shared findings:** CA-03 covers script-unavailable controls here; SU-01 prompts boundary checks for pagination but is not asserted as a queue paging defect.

No additional distinct issue ID is added here; the shared findings below cover the observed defects. This does not certify the view as defect-free.

#### Page acceptance checks

Exercise no published revisions, missing campaign/job, version conflict, interrupted preparation, duplicate prepare, past/invalid schedules, ambiguous action outcomes and keyboard/mobile table access. Confirm dispatch remains disabled after every action. No new distinct authorization or lifecycle-write defect was established in this source pass.

Source: [inc/views/pages/crm-campaign-queue.php:1](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/views/pages/crm-campaign-queue.php#L1); [inc/campaigns/queue-ui.php:1](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/campaigns/queue-ui.php#L1); [inc/campaigns/queue.php:5](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/campaigns/queue.php#L5).

### View 13 — Linked public preference and unsubscribe views

**Page(s):** `/email-preferences/`, `/email-unsubscribe/`, `/email-unsubscribe/one-click/`  
**Status:** Source flow review complete at the report baseline; live acceptance pending.

#### Flow and assessment

Legacy preference token → standalone private response → explicit POST for changes. New recipient-token preference route → injected preference service → token/form/origin validation → limited preference edit. Visible unsubscribe uses a deliberate confirmation; machine one-click uses its dedicated route/method contract. Public GET/HEAD reads must not perform consent changes.

These are outside the staff navigation but are part of the end-to-end campaign flow. The new recipient preference/withdrawal production service factories return null, so their unavailable/503 state is intentional until readiness work is complete. The legacy route and new token family must not be conflated. Preference edits never grant global marketing permission or remove suppression.

**Shared findings:** Readiness item R-01 below applies. No distinct production public-edit defect was established from this pass.

No additional distinct issue ID is added here; the shared findings below cover the observed defects. This does not certify the view as defect-free.

#### Page acceptance checks

Before a future authorized pilot, verify token expiry/revocation and deployment/mailbox binding, GET/HEAD no-write behavior, strict POST fields/content type/origin/form proof, legacy/new token separation, invalid/unavailable generic responses, private headers and absence of bearer tokens in operational logs. Repeat tests on the authoritative host without using real recipient links.

Source: [inc/campaigns/routes.php:40](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/campaigns/routes.php#L40); [inc/campaigns/preferences.php:1](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/campaigns/preferences.php#L1); [inc/campaigns/recipient-preferences.php:1](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/campaigns/recipient-preferences.php#L1); [inc/campaigns/recipient-withdrawal.php:1](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/campaigns/recipient-withdrawal.php#L1); [inc/campaigns/recipient-withdrawal-routes.php:1](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/inc/campaigns/recipient-withdrawal-routes.php#L1).

### Readiness and verification items — separate from defect counts

| Item | Urgency / decision point | Required evidence |
|---|---|---|
| R-01 — Campaign delivery/public recipient services remain intentionally blocked | Mandatory before any future authorized live email pilot; not a bug to bypass | Retain disabled dispatch and null production recipient factories until the implementation plan’s provider, deployment/key, privacy/logging, retention/restore and public-endpoint acceptance prerequisites are met. A successful draft, freeze or queue preparation does not satisfy them. |
| R-02 — Runtime/browser acceptance is outstanding | Before treating a source correction as closed | Test the matrix below in a controlled WordPress environment and inspect actual deployed role/capability/schema/option state. No production role migration or configuration change is assumed. |

The latest campaign implementation plan records work through 25 October 2026, including transactional SES suppression application and hosted fixture acceptance. It still states that production factories, dispatch, real intake/collection and cron gates remain disabled. That source document’s prior test claims are not tests executed by this audit. Current runtime evidence takes precedence over old scan recommendations, but disabling gates must not be “fixed” merely because an operational view exists.

### Documentation and LLM guidance reconciliation

The plugin’s authorization deployment note describes one assignment-aware canonical policy, including fail-closed conflicting metadata and removal of generic REST exposure. This is the intended boundary used to evaluate OV-01, CL-01, CD-01, PF-01 and WA-01; a historical “hardening complete” narrative is not evidence that every newer caller uses the policy correctly.

The client-view/admin audit and timeline-parity notes explain why the frontend now contains notes, reminders, properties, deals and a unified timeline. Their existence does not demonstrate that every history is fully paginated or AJAX/native commands are equivalent. The current implementation was traced separately. Impersonation phase notes distinguish the real actor from the effective working identity; recent subscriber/campaign write blocks are intentional, while older CRM creation/list paths need the explicit policy described above.

The living campaign plan is the relevant recent implementation/verification record; its archived through-22-October companion is historical. Treat older theme-owned paths and scan closure claims as context, not instructions to restore obsolete architecture. This report carries forward the uploaded/repository guidance already reviewed and adds no new LLM instructions, skills or deployment authorization.

Documentation references:

- [docs/authorization-hardening-deployment.md:1](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/docs/authorization-hardening-deployment.md#L1)
- [docs/client-view-admin-audit.md:1](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/docs/client-view-admin-audit.md#L1)
- [docs/frontend-timeline-details-parity.md:1](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/docs/frontend-timeline-details-parity.md#L1)
- [docs/peracrm-impersonation-phase3-write-paths.md:1](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/docs/peracrm-impersonation-phase3-write-paths.md#L1)
- [docs/email-campaigns-implementation-plan.md:1](https://github.com/7mus7y-dotcom/pera-wp/blob/e65b22e626081ebc47149cffb9ff50740cdd1f02/wp-content/plugins/peracrm/docs/email-campaigns-implementation-plan.md#L1)

### Acceptance matrix for closing issues

| Area | Required cases |
|---|---|
| Identity and scope | Employee, manager, administrator, custom scoped CRM role, missing/conflicting legacy assignment, reassigned client and view-as. Check page, AJAX, native POST and REST independently. |
| Data completeness | More than 200 pending tasks, 250 pipeline records, 100 log rows, 50 reminders/timeline events, 20 notes/properties; out-of-range pages and rare-stage/search results. |
| Mutation integrity | Foreign deal ID paired with authorized client; duplicate/retried/concurrent create/send; invalid nonce, denied transition, field errors, storage failure and uncertain response. |
| Time and metrics | Site timezone boundaries, new versus aged untouched leads, previous periods, completed-only tasks, older-lead deals and progressed stages. |
| Browser state | Slow/reordered responses, edit during preview, modal keyboard flow, script unavailable, narrow layouts, history/back navigation and stale summary fragments. |
| Campaign boundaries | Capability/revision checks, readonly impersonation, public-token separation, no consent grant through preferences, no real provider traffic and disabled dispatch after lifecycle commands. |
| Multisite | Authoritative CRM/log blog for reads and writes, consistent canonical scope, safe host routing and no unintended cross-blog records. |

### Verification performed and limits

- Read active route/template/data/service/repository/action/JavaScript paths at the pinned repository baseline. Cross-checked relevant recent repository guidance and campaign readiness notes.
- Previously reproduced CL-09 with controlled Node mocks. In this revision, executed a separate mocked Node VM against the actual WhatsApp composer script: two rapid form submissions created two identical pending POST requests. No real browser session or network/provider traffic was involved.
- Inspected relevant existing PHP authorization/search tests and source contracts. PHP/WordPress/database suites were not run here because a PHP runtime is unavailable. Hosted test evidence described in repository docs was not rerun or independently certified by this report.
- No production records, authenticated live pages, responsive screenshots, real performance profile or deployed configuration were inspected. No CRM source, customer data, roles, notifications, messages, delivery gates or provider settings were changed.
- All finding headings, register entries, counts and pinned local-source line references were checked for consistency when generating this revision. Proposed acceptance cases remain outstanding; none of the 46 findings is closed.

The remaining work is remediation and live acceptance, not another unreviewed frontend page in the routed scope above.
