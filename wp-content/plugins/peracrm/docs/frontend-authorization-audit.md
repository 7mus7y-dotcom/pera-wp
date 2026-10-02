# PeraCRM frontend authorization audit

## Scope and status

This audit records the current server-side authorization boundaries for PeraCRM frontend routes. It is documentation only: it does not change roles, capabilities, schemas, or runtime behaviour.

The frontend route gate in `inc/frontend/routing.php` requires a logged-in CRM-capable user before selecting a plugin template. Route access is not, by itself, authorization to read every client. Client-specific reads and writes must additionally enforce record scope.

## Current authorization boundaries

| Surface | Current guard | Scope observation |
|---|---|---|
| CRM route shell | `pera_crm_gate_or_redirect()` | Rejects logged-out users and users without CRM access before the CRM template is selected. |
| Client lists, overview, tasks, pipeline, and search | Their frontend-data/service query builders | Employee/adviser results must remain constrained to the allowed-client set; users with `manage_options` or `peracrm_manage_all_clients` can use the broader scope. |
| Direct client URL `/crm/client/{id}/` | `pera_crm_client_view_access_state()` in `inc/frontend-data/crm-client-view.php` | Verifies the object is a `crm_client`, requires `edit_post`, and, unless the user can manage all clients, requires the current user to be the assigned adviser. The client template calls this check before `pera_crm_client_view_load_data()`. |
| Client mutations | Frontend/admin handlers plus service/repository checks | Nonces and general capabilities are not substitutes for record-level authorization; writes must preserve the same assigned-client boundary. |

## Direct client URL analysis

The main client content has a genuine server-side access check. In `inc/views/pages/crm-client.php`, `pera_crm_client_view_access_state()` runs before the full client view model is loaded. An employee/adviser requesting an unassigned client receives the restricted-state content instead of the normal client detail content.

That denied response is **not currently fully non-disclosing**. `pera_crm_get_client_detail_document_title()` in `inc/frontend/routing.php` recognizes a client route and validates only that the requested ID belongs to a `crm_client`; it does not call the client access-state check or an equivalent record-scope authorization guard. It then reads:

- the client's post title, used as the client name in the document title; and
- `_peracrm_client_type`, with `peracrm_client_type` as its fallback, formatted as the client type.

The function supplies those values to both the `pre_get_document_title` and `document_title_parts` filters. The CRM shell subsequently calls `wp_head()` in `inc/views/shell/header.php`; WordPress title rendering executes those filters while building the HTML `<title>`. Consequently, an assigned-client-only employee can request another client's numeric ID, fail the client-content authorization check, yet receive that client's name/title and client type in the HTML document title.

This finding does not mean the client-view authorization check is absent. The defect is ordering and reuse: client-specific page-shell metadata is constructed without first proving that the requester may view that client.

## Exposure matrix

| ID | Surface | Unauthorized result | Exposed metadata | Severity | Status |
|---|---|---|---|---|---|
| FE-AUTH-01 | Direct client content | `pera_crm_client_view_access_state()` denies an unassigned employee/adviser and suppresses the normal client detail view model. | The normal client detail payload is not rendered; page-shell disclosure is assessed separately in FE-AUTH-02. | Informational (guard present) | Preserve and regression-test. |
| FE-AUTH-02 | Direct client HTML `<title>` / page-shell metadata | The request is denied in the page body, but the title filters read the requested client first. | Client name/title and client type. | **High (P1)** | Open; implementation deferred to the authorization remediation PR. |

No additional exposed fields are asserted by FE-AUTH-02. Its verified scope is the client name/title and client type read by `pera_crm_get_client_detail_document_title()`.

## Recommended authorization remediation

1. Preserve `pera_crm_client_view_access_state()` as the authoritative direct-client content guard, or centralize its policy in a side-effect-free record authorization service shared by all client surfaces.
2. Require successful record authorization **before** `pera_crm_get_client_detail_document_title()` reads the post title or client-type metadata. An unauthorized, missing, or wrong-type record must use a generic CRM/denied title that reveals no client-specific value.
3. Apply the same rule to any current or future page-shell metadata derived from a client: authorize the record before constructing document titles, header metadata, breadcrumbs, social/head metadata, or similar shell output.
4. Keep the route-level capability gate and the existing client-content check; fixing the metadata path must not replace or weaken either guard.
5. Review client-specific frontend reads and mutations for the same ordering rule, while leaving adviser/employee role definitions unchanged in this audit PR.

## Proposed authorization test matrix

| Actor and request | Expected result |
|---|---|
| Assigned-client-only employee requests their assigned client ID | Client detail renders; the authorized client name and type may appear in the document title. |
| Assigned-client-only employee requests another employee's client ID | Restricted/denied client content; response HTML `<title>` and the rest of the page shell contain neither that client's name nor client type, and contain no other protected metadata from that client. |
| Assigned-client-only employee requests a missing or non-`crm_client` ID | Generic not-found/denied response and generic title/shell, with no record metadata. |
| Manager/administrator with manage-all scope requests a valid client ID | Client detail and client-specific title render according to the authorized broad scope. |
| Logged-out visitor requests a direct client URL | Login redirect; no CRM client metadata in the redirect response or page shell. |
| CRM user lacking record scope probes IDs repeatedly | Every denied direct-client response remains non-disclosing in status/body, HTML `<title>`, and identified CRM shell metadata; response differences do not reveal the verified client name/type fields. |

The key regression assertion is broader than hiding the client body: a denied direct-client request must be non-disclosing across the complete HTML response. At minimum, parse the returned HTML and assert that the protected client's known name and client-type label are absent from `<title>` and the CRM page shell, while separately confirming that the existing server-side restricted-state content still renders.
