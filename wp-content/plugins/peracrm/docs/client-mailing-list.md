# Client mailing list

Subscriber managers can see a **Mailing list** panel on an authorized client page.
It compares the client's current `_peracrm_email` profile field with Subscribers
and suppression records, including opt-outs without a subscriber profile. Invalid,
missing or duplicate email metadata is never repaired or guessed.

An absent or unconfirmed subscriber can be explicitly added/confirmed with a
permission reference and the checkbox: “I have confirmed this client’s permission
to receive Pera Property marketing emails.” The action records the staff actor,
current UTC confirmation time, client reference and supplied evidence reference.
`crm-marketing-confirmation-v1` identifies this staff confirmation statement; it
does not assert an original historic signup time or imported policy version.

Real subscriber intake must be enabled. The POST requires subscriber capability,
client access, a client-specific nonce, and the unchanged saved email. The mailbox,
client post and email metadata are locked while permission is saved. Creation and
confirmation share a single transaction. A storage failure rolls back both.

Existing opt-outs, complaints, hard bounces, administrative suppression and retained
withdrawal timestamps cannot be cleared here. A repeated request for an already
confirmed subscriber preserves the existing evidence/profile. An unknown or pending
subscriber requires the same explicit confirmation as a missing subscriber.
Existing language/topics are retained; new subscribers have neither inferred.

The panel matches by email. It never writes CRM profile fields, creates a sales
client, automatically links Subscribers to a client, or sends email. Existing links
are retained; linked-client eligibility and campaign audience rules still apply.
The Manage subscriber link opens the existing subscriber tools for withdrawal and
profile preferences. Staff impersonation permits authorized reads only.

To compare existing clients with the list after deployment, run as administrator:

```sh
wp --path=/home/d21882/public_html --url=https://www.peraproperty.com --user=1 \
  peracrm campaigns client-mailing-audit --format=json
```

This read-only command reports counts for subscribed, suppressed, unsubscribed,
missing, unconfirmed, missing-email and invalid-email client records. It outputs
no mailboxes and performs no enrolment. Counts are **per client record**, not unique
email, using a fixed maximum client ID and live rows (not a transactional snapshot).
Deleted/trash/auto-draft clients are excluded. Only the currently saved primary email
is checked; historical addresses and alternative metadata aliases are not enrolled.
