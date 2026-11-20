# Delivery review and list cleanup

Open **Subscribers → Bounces and cleanup**, or
`/crm/subscribers/?subscriber_view=delivery-review`.

The page reuses the subscriber shell, forms, table layout, permissions and private
response headers. No schema migration, provider permission change or rewrite flush
is required. Existing suppression flags are the cleanup flags; this feature does
not introduce a separate permission state or enable dispatch.

## Address review

Search and paginate hard bounces, complaints, unsubscribes and administrative
blocks separately. The latest matching suppression evidence provides the source
and occurrence date. Records without a current subscriber remain visible because
the durable mailbox suppression is the primary record. Stored CRM links are shown
only when the viewer can open that client; restricted links remain masked.

Temporary delivery delays and transient/undetermined bounces have a separate
read-only event view with type, subtype and occurrence time. Historical temporary
events can remain visible after delivery. They never authorize profile removal.

For a current suppressed profile, expand **Remove subscriber profile**, review the
address and linked-client notice, check confirmation, then submit. Cleanup requires
subscriber-management write authority and a valid CSRF nonce. Under the existing
mailbox transaction/lock, the service rechecks suppression, subscriber ID and
version. Changed or recreated profiles fail closed. Successful cleanup records an
actor-attributed deletion event, retires recipient identity through the existing
lifecycle boundary and removes only the current subscriber row.

Suppression, consent/suppression events, frozen recipients, delivery journals,
submission evidence and CRM client/contact history remain. Reimport/recreation
cannot clear a suppression. Repeated submissions for a removed profile are no-ops.
No bulk or automatic deletion is performed.

## Remaining unsent recipients

Enter a paused job number. Subscriber-management and campaign-report access are
required. The default filter selects current profiles sourced from the owner-attested
Zoho migration; **Include all sources** also reveals missing current profiles and
other origins. Only held/retry deliveries are inspected. Accepted and unknown
outcomes remain excluded and unchanged.

The view shows source, language, consent evidence dates, authorized CRM links and
advisory format/placeholder/current-consent flags. Consent dates are not engagement
dates. An address passing these checks has not been verified to exist or to accept
mail. No DNS/SMTP probing, external validation service, consent grant, queue mutation
or resume action takes place.

A private CSRF-protected CSV export supports up to 10,000 matching recipients. Rows
come from one bounded SELECT and cells use the existing spreadsheet-formula escaping.
The export is current review data, not an immutable eligibility snapshot or a
mailbox-validation certificate. Review list provenance and independent evidence of
recent contact before deciding whether further sending is appropriate.

## Validation

Standalone tests cover advisory hints and denial of unconfirmed/unauthorized
cleanup. Real WordPress/MySQL fixtures cover category separation, literal search,
version/replacement conflicts, suppression/audit/client preservation, idempotence,
recreation remaining blocked, temporary-only records, and SELECT-only paused-job
review/export excluding accepted and unknown outcomes. Browser checks exercise
mobile/desktop review, private responses, missing-nonce rejection and explicit
profile removal through the actual staff form.
