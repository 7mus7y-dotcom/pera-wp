# Analytics audit: Chinese archive visit-ID churn (2026-11-11)

## Observations from production read-only queries
- On `/zh/property/`: Nov 9 = 5 visits/5 IDs, Nov 10 = 18/18, Nov 11 = 882/882 at first query (885 at later query).
- One distinct `user_agent_hash` across all days.
- On Nov 11, 364 visits during hour 08 and 385 during hour 11 (database/server time).
- All grouped records were internal referrals to `peraproperty.com`. Referrer URLs contain repeatedly concatenated encoded `/page/N/` fragments inside `_specials`.
- GA4 app reported zero active Chinese users for its selected period; GA4 blocking or tracking differences remain possible.
- Chinese-language rollout preceded the spike. This does **not** establish that the requests came from a verified Chinese search engine.
- PR #1600 addressed a plausible pagination URL query/path boundary issue; verify on production after deploy.

## Code findings (main at audit time)
1. `js/page-visit-tracker.js` sends `admin-ajax.php?action=pera_track_page_visit` via `navigator.sendBeacon` (or fetch fallback). SessionStorage only deduplicates identical URL within 15 seconds in a single browser session.
2. `inc/modules/analytics/tracker.php` creates a fresh `pera_vid` UUID whenever the request lacks a usable cookie. `setcookie` runs on the AJAX response. A client not persisting or returning cookies therefore produces one unique visitor per request. The raw table does **not** prove distinct people or IPs.
3. The public tracking handler accepts POSTs with absent `Origin`; a cross-origin `Origin` is rejected, but non-browser clients can omit/spoof headers. No cryptographic request token or independently verifiable page-view proof is present. Treat referer/page_url as untrusted.
4. User-agent exclusion is based on `pera_analytics_is_likely_bot_ua`; a generic browser UA passes. User-agent hash alone cannot establish one IP or one bot.
5. `queries.php` existing suspected-bot rule groups by `visitor_id` (>=10 visits in <=180 sec, with no referrer host). It **cannot** catch cookie-less one-ID-per-request patterns, particularly those with internal referrers.
6. `pera_analytics_get_request_ip` trusts `CF-Connecting-IP`, `True-Client-IP`, and `X-Forwarded-For` before `REMOTE_ADDR` without checking a trusted proxy. `HTTP_CF_IPCOUNTRY` is also accepted without proxy validation. Country attribution can be spoofed unless upstream infrastructure strips untrusted headers.

## Recommended next implementation (separate, tested change)
- Preserve legitimate search-engine crawling and `/zh/` indexing. Never blanket-block CN IPs or a crawler name inferred from a UA.
- Validate proxy headers only when `REMOTE_ADDR` belongs to a configured trusted proxy, otherwise use `REMOTE_ADDR`. Reuse trusted GeoIP logic where possible.
- Inspect redacted server access logs for requests to `admin-ajax.php` and malformed archive URLs around 2026-11-11 08:00 and 11:00 server time; check actual remote IP/network, cookie persistence, response codes, and frequency. Avoid storing raw IPs in analytics.
- Add a **separate** suspected-automation signal for high-volume same-UA bursts with visitor-ID churn and malformed URL patterns; require multiple signals and avoid broad UA-only exclusion (false positives). Keep aggregate suspicious-visit counts visible.
- Consider a short-lived, server-issued first-party tracking token bound to page rendering, with replay protection and rate limiting. This raises abuse cost but does not prove humanity. Avoid making pages inaccessible to crawlers.
- Test that ordinary users with disabled cookies, privacy browsers, and Chinese visitors are not silently discarded. Test origin-absent legitimate beacon behavior, proxy spoofing, and IPv6.
- Audit daily aggregation: bot reclassification of historical rows needs recomputation of existing daily summaries, including dates whose surviving valid visits become zero. Do not delete historical raw rows.

## Status
Read-only code/data audit, no production changes, no bot identities verified. Follow-up code changes require tests and staging validation.
