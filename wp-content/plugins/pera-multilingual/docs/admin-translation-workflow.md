# Admin object translation workflow

The **Translate this object** action is a synchronous `admin-post.php` request. It
translates each non-empty source independently and stores it immediately. It is
not a transaction: a provider or validation error for one field does not roll
back successful rows. This intentional partial-success behavior makes a later
run capable of filling a missing current row without deleting its siblings.

Post fields are attempted in this explicit order:

1. `post_content`
2. `post_title`
3. `post_excerpt`
4. non-empty approved post-meta fields, in the order returned by
   `Pera_ML_Fields::approved()`
5. assigned terms for `district`, `region`, `property_type`, `property_tags`,
   and `special`, including the existing term descriptions and district meta.

Putting non-empty `post_content` first is a small mitigation for PHP/web-server
request time limits: the previous order made many sequential provider calls and
could save title and metadata before a request ended. It does not make the POST
asynchronous, so hosts must still allow enough time for the bounded sequence of
provider requests. Each provider call retains its 60-second HTTP timeout.

The orchestration retries a field at most once for rate limiting, transient HTTP
statuses (408, 425, and 5xx), or a WordPress HTTP transport failure. It does not
retry validation/permanent errors and does not replace the translator's separate
source-echo segment retry. Notices contain only success/failure counts and up to
50 sanitized, 100-character field identifiers; provider messages and response
bodies are not exposed.
