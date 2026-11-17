# AI SEO content inventory audit — Phase 1

Date: 2026-11-16
Scope: repository-based review, **not** a live WordPress or HTTP crawl.

## Inputs
- `docs/blog-post-inventory.csv` (137 data rows in current repository snapshot)
- `docs/taxonomy-inventory.csv`
- `docs/property-inventory.csv` (identified; not individually assessed in this phase)
- Theme `inc/modules/llms.php`, which implements `/llms.txt` and `/llms-full.txt`
- Theme citizenship templates and existing SEO/schema modules identified through code search

## Results
- 29 candidate topics in the initial topic map (corrected from the earlier informal 32-topic estimate).
- 20 topics have at least one title-level match in the blog inventory.
- 9 topics lack a mapped title-level match; this **does not establish** missing content because service pages, property listings and article bodies have not yet been audited.
- All matched URLs remain `inventory_url`, not verified canonical URLs. No topic is marked live-verified.

## Highest-priority next actions
1. **P0: Canonical/redirect verification.** Check actual HTTP status, redirects, canonical tags, indexability and hreflang for matched URLs. Inventory contains legacy `_ID` permalink patterns; recent redirect changes make this especially important.
2. **P0: Citizenship legal freshness.** Review 2026 requirements, DAB, valuation, conformity and the September 2026 valuation changes together; resolve contradictory statements and link to current primary government sources.
3. **P1: District hub architecture.** Audit guide versus taxonomy archive intent for Kadıköy, Şişli/Bomonti, Kağıthane, Zeytinburnu, Küçükçekmece, Ataşehir and Maltepe. Add contextual links where helpful, avoiding duplicate keyword-targeted pages.
4. **P1: Ownership/landlord hub.** Link purchase costs, title deed, power of attorney, rental guide, tax and management services. Confirm current law and dates.
5. **P1: Content gaps to validate.** Gross versus net floor area, rental yield methodology, off-plan due diligence, tenant-occupied resale, Ümraniye and Maslak district coverage, and explicit property-management lifecycle content.
6. **P2: Structured data audit.** Inspect existing Organization, Article, FAQ and real-estate schema outputs for accuracy and duplication before adding any new markup.
7. **P2: Measurement.** Establish Search Console baselines per cluster; use separate manual citation checks for ChatGPT, Google AI Overviews and Perplexity. Do not attribute AI referrals without evidence.

## Cautions
- An inventory match indicates a relevant title, **not** quality, completeness, freshness or indexation.
- `llms.txt` is supplementary and not an official AI ranking requirement.
- Do not generate mass near-duplicate pages or auto-publish legal claims.
- Avoid adding schema for content not visible to readers.
- No changes to production website are included in this phase.

## Suggested live verification workflow
Export published WordPress posts/pages/CPT records (ID, title, slug, permalink, status, modified date, categories and canonical metadata). Crawl each URL, record HTTP final URL, indexability, canonical, hreflang and main heading. Join by post ID rather than slug; resolve discrepancies and review any content flagged as stale.
