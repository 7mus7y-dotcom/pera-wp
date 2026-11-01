# Retired public author archives

`inc/modules/author-archives.php` runs on `parse_request` priority 1, after
Pera ML restores the public URI at priority 0. WordPress has already populated
rewrite/query variables at this point. Author names/IDs, pagination, feeds and
unknown authors return HTTP 410 with `Gone` as plain text, no-cache headers and
`X-Robots-Tag: noindex`. The callback exits before WordPress's main query,
canonical redirects, language negotiation, template rendering or head metadata.
Existing rewrite rules remain intact; no rewrite flush is required.

Frontend author archives return 410 for both anonymous and logged-in visitors.
Admin, REST (including `rest_route`), AJAX, cron and XML-RPC requests are exempt.
Public bylines remain visible without archive links in the post template,
core linked bylines, author lists and core author/name blocks. Article schema
retains the individual `Person` name; only the now-retired author URL is omitted.
Post URLs, canonical calculation, property SEO and multilingual routing are unchanged.

Link removal uses WordPress's HTML tokenizer and `WP_HTML_Tag_Processor` (WordPress
6.2+) to inspect anchor destinations. Only local author archive wrappers are
removed; visible names, nested safe markup and unrelated links remain intact.
Matching respects the site's home path, configured WordPress author base and
enabled multilingual prefixes, and covers pagination and author query URLs.
External profiles, biography/contact/property links and admin/REST links remain.
Fragments are not reserialized, so unrelated markup is preserved byte-for-byte.

The existing `inc/modules/sitemaps.php` already rejects the core `users`
provider. This excludes author sitemap entries, index references and direct
provider access. Repository inspection found no separate custom author sitemap
generator. A future SEO plugin or custom component generating its own author
links/sitemaps must adopt the same policy.

## Local validation

Run `php wp-content/themes/hello-elementor-child/tests/author-archives-test.php`.
This standalone hook harness checks parsed query variables for the four required
author URLs, unknown authors, feeds, pagination across all registered language
prefixes, normal post/blog/property/multilingual/sitemap pass-through, privileged
request exemptions, logged-in frontend 410 responses, sitemap provider filtering,
targeted link removal, unrelated links, nested markup and schema source.
Terminating responses are checked in isolated PHP subprocesses.
The standalone HTML API adapters use DOM for attribute reading (tests require
PHP DOM); production uses WordPress's HTML API. The harness does not boot
WordPress or validate Apache/CDN responses.

Also run the existing multilingual `router-test.php` and `geo-redirect-test.php`,
plus theme `multilingual-url-routing-audit-test.php`,
`multilingual-property-url-routing-test.php` and
`property-archive-seo-preview-test.php`. PHP lint and `git diff --check` must pass.

## Verification after deployment

No deployment is performed by this change. After the separately approved release:

- Purge any cached author responses/redirects at the host/CDN. As both anonymous
  and logged-in frontend visitors, request `/author/admin/`, `/author/admin/page/2/`, `/?author=1`
  and `/?author_name=admin` without following redirects. Expect 410, plain text
  `Gone`, no Location, canonical, hreflang or schema. Repeat with enabled language
  prefixes, unknown usernames and HEAD requests.
- Verify a real post, blog archive, property single/archive and translated post
  retain their existing status, canonical, metadata and Article/BlogPosting author
  name. Verify bylines remain visible without author archive hyperlinks and
  unrelated biography/profile/contact links remain functional.
- Inspect `/wp-sitemap.xml` and any externally configured sitemap index such as
  `/sitemap_index.xml`; no author URLs/providers should appear. Verify the old
  direct users sitemap cannot serve entries.
- Verify REST author filters, wp-admin and normal logged-in frontend functionality.
- Check for manually stored author links in menus/editor/Elementor/database
  content and any hosting redirect rules; these are outside this repository.
