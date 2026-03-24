# Single Post SEO Audit (Theme-Level)

## 1. Executive summary

- **Overall grade:** **C+** (good baseline metadata coverage, weak structured data + canonical duplication risk + thin single-post semantic enhancements).
- **Biggest strengths:**
  - Single-post template is explicit (`single-post.php`) and outputs crawlable HTML content via `the_content()`.
  - Theme has a centralized SEO meta module (`inc/seo-all.php`) that outputs description/canonical/OG/Twitter for non-property contexts.
  - Social image tags use featured image + alt fallback and include Twitter/OG parity.
- **Biggest weaknesses:**
  - No post-specific structured data (`Article`/`BlogPosting`) found in single-post path.
  - Likely duplicate canonical tags on single posts (custom canonical output + WordPress core canonical).
  - No post-specific title strategy beyond default WordPress title generation.
  - Weak recirculation pattern for editorial SEO (only same-first-category related posts; no next/prev or breadcrumbs on single posts).
  - Single-post hero uses full-size image request (`full`) without explicit size constraints in template.
- **Top 5 priority fixes (implementation direction only):**
  1. Add dedicated `BlogPosting` JSON-LD for `is_singular('post')` with author/publisher/date/image.
  2. Resolve canonical duplication by selecting one canonical owner for standard posts.
  3. Improve single-post metadata strategy (title pattern + description source controls).
  4. Improve internal linking blocks for posts (next/prev, taxonomy breadcrumbs, broader related logic).
  5. Improve media/perf semantics for hero image (appropriate size, explicit dimensions/fetch priority strategy).

## 2. Template/render path map

### Confirmed render path for standard single post

1. Theme bootstrap loads in `functions.php` (`inc/bootstrap.php`, `inc/theme-helpers.php`, `inc/theme-modules.php`).
2. SEO loader is included via `inc/theme-modules.php` and hooks into `wp` to conditionally load SEO modules.
3. For standard post singular requests (not `property`), `inc/modules/seo-loader.php` falls through to `inc/seo-all.php`.
4. WordPress template hierarchy should select `single-post.php` for `post` post type.
5. `single-post.php` calls `get_header()` / `get_footer()`, and `header.php` runs `wp_head()` where SEO tags are injected.

### Head/meta output ownership

- `header.php` owns the `<head>` wrapper and executes `wp_head()`.
- `inc/seo-all.php` adds `wp_head` output for:
  - meta description
  - canonical link
  - OG tags
  - Twitter tags
- `inc/seo-all.php` also filters `wp_robots` (search pages + filtered property archives), but does not add post-specific robot directives.

## 3. Metadata audit

### `<title>`

- **Confirmed:** No post-specific title filter in the non-property SEO stack.
- **Current behavior:** `inc/seo-all.php` reads title from `wp_get_document_title()` and mirrors it into OG/Twitter title fields.
- **Inference:** Standard post title formatting is mostly WordPress core + theme title-tag behavior (no custom editorial pattern logic for posts).

### Meta description

- **Confirmed:** Generated in `inc/seo-all.php` using:
  1. post excerpt
  2. fallback: stripped post content
  3. truncated to 160 chars
- **Strength:** Automatic fallback exists when excerpt missing.
- **Weakness:** No per-post manual override field, no quality guardrails, and simplistic character cutoff.

### Canonical

- **Confirmed:** `inc/seo-all.php` outputs `<link rel="canonical">` and computes canonical using `wp_get_canonical_url()` for non-property contexts.
- **Risk (high confidence):** WordPress core usually outputs canonical on singular via `rel_canonical`; no code removes this hook in theme, so duplicate canonicals are likely on single posts.

### Robots

- **Confirmed:** `inc/seo-all.php` modifies robots for search and filtered property archives only.
- **For single posts:** no explicit `noindex`; default indexable behavior remains unless plugin/core/site settings alter it.

### Open Graph tags

- **Confirmed in `inc/seo-all.php`:**
  - `og:site_name`
  - `og:type` (`article` on singular)
  - `og:title`
  - `og:url`
  - optional `og:description`
  - optional `og:image`
  - optional `og:image:alt`
- **Gap:** No explicit article-specific OG fields like `article:published_time`, `article:modified_time`, `article:author`, `article:section`, `article:tag`.

### Twitter/X card tags

- **Confirmed in `inc/seo-all.php`:**
  - `twitter:card` (`summary_large_image` if image exists)
  - `twitter:title`
  - optional `twitter:description`
  - optional `twitter:image`
  - optional `twitter:image:alt`
- **Gap:** No `twitter:site` / `twitter:creator` handling.

### Featured image social tags

- **Confirmed:** Featured image used for OG/Twitter image; alt from `_wp_attachment_image_alt`; fallback to default image constant if configured.
- **Gap:** No explicit image width/height tags for OG.

### Duplication/conflict notes

- **Likely duplicate canonical tags** on single posts (custom + core canonical).
- **Potential duplicate description tags** if an SEO plugin is also active (theme removes Hello Elementor description tag, but does not explicitly coordinate with plugin stacks in this file).

## 4. Structured data audit

### What schema exists (post scope)

- **Confirmed:** No `application/ld+json` output found for standard single posts in template or non-property SEO module.

### What is missing

- `BlogPosting` / `Article` graph for standard posts.
- Key properties for rich results:
  - `headline`
  - `author`
  - `datePublished`
  - `dateModified`
  - `image`
  - `mainEntityOfPage`
  - `publisher` (`Organization` with logo)
- Breadcrumb schema for post hierarchy/category trail.

### Eligibility / richness assessment

- **Current status:** Low-to-moderate rich-result support for editorial posts because core article schema is absent.

## 5. On-page semantic structure audit

### H1/H2/H3

- **Confirmed:** One template-level `<h1>` in hero (`the_title()`).
- **Confirmed:** Sidebar blocks use `<h3>` headings.
- **Unknown (content-dependent):** H2/H3 hierarchy in article body comes from editor content (`the_content()`), so quality depends on authoring discipline.

### Intro/excerpt handling

- **Confirmed:** Optional lead paragraph shown only when manual excerpt exists.
- **Implication:** Posts without excerpts may have no concise intro in hero despite having body content.

### Author/date/category/tag markup

- **Confirmed:** Category link shown (first category only), date uploaded, updated date, and author name rendered as plain text spans.
- **Gap:** No semantic `time` elements with `datetime`, and no explicit author profile link in template.
- **Gap:** Tags are not surfaced in single-post template.

### Body copy exposure

- **Confirmed:** Full post content rendered server-side via `the_content()` in `<article>`; crawlable without JS dependency.

### Image handling and alt exposure

- **Confirmed:** Hero uses `wp_get_attachment_image()` (full size) which can carry alt from media library.
- **Confirmed:** Related post cards use featured thumbnail output (`get_the_post_thumbnail()`) with lazy loading; placeholder image has empty alt for decorative usage.
- **Risk:** Single-post hero requests `full` image size and does not visibly set intrinsic width/height in template-level attributes (WP may include dimensions, but source media may still be oversized for viewport).

## 6. Internal linking & recirculation audit

### Related posts

- **Confirmed:** Sidebar query for up to 3 related posts by first category only (`cat` of primary category).
- **Strength:** Provides recirculation link block.
- **Weakness:** Single-taxonomy heuristic can be weak for topical relevance.

### Taxonomy links

- **Confirmed:** Primary category pill links to category archive.
- **Gap:** No tag links in single-post template.

### Breadcrumbs

- **Confirmed:** No breadcrumb UI in `single-post.php`.
- **Confirmed:** No breadcrumb schema for posts.

### Next/prev posts

- **Confirmed:** No `next_post_link()` / `previous_post_link()` in single-post template.

### Contextual internal linking support

- **Confirmed:** Template does not auto-inject contextual internal links in body; relies on manual editorial linking.

## 7. Indexation and duplicate-risk audit

### Canonicalization issues

- **High confidence risk:** duplicate canonical tags for single posts due to theme canonical + likely core canonical.

### Archive overlap

- **Observed:** Single-post template links category, and blog archives exist; no explicit post-level canonical anti-overlap beyond canonical itself.
- **Unknown:** Taxonomy archive noindex policy for blog category/tag archives is not customized here.

### Attachment/media issues

- **Confirmed:** Custom `attachment.php` exists and renders indexable media pages.
- **Risk:** If media pages remain indexable without strategic canonical/noindex policy, they can create thin-index noise.

### Thin/duplicate metadata risks

- **Observed:** Description fallback pulls first content text and truncates; quality may be generic or duplicated across similar posts.
- **Observed:** No post-specific override framework in theme code for finer metadata control.

## 8. Performance/UX factors affecting SEO

- **Critical content visibility:** Main article content is server-rendered, not JS-gated (good).
- **Potential LCP/bytes issue:** Hero image uses `full` size with eager loading on single posts.
- **Potential layout stability concern:** Template does not manually set image dimensions in markup options; usually WordPress includes them, but output depends on attachment data and could vary with SVG/fallback behavior.
- **Script/style load:** Single posts load several bundles (`main.css`, `slider.css`, `blog.css`, `posts.css`, `property-card.css`) plus scripts (`main.js`, `favourites.js`, whatsapp logger), which may increase transfer/parse cost.

## 9. Findings table

| Severity | Area | Finding | Why it matters | File(s) | Recommended fix direction |
|---|---|---|---|---|---|
| Critical | Canonical | Likely duplicate canonical tags on single posts (theme + core). | Conflicting canonicals can dilute canonical signals and create crawler ambiguity. | `inc/seo-all.php` | Set single canonical owner; remove redundant output or unhook core canonical for targeted contexts. |
| High | Structured Data | No `BlogPosting`/`Article` schema for standard posts. | Reduces rich result eligibility and explicit entity understanding. | `inc/seo-all.php`, `single-post.php` | Add post-only schema graph with author/publisher/date/image + mainEntityOfPage. |
| High | Internal Linking | No breadcrumbs and no next/prev links on single posts. | Weakens crawl paths and topical graph reinforcement across editorial content. | `single-post.php` | Add breadcrumb trail and adjacent post navigation. |
| Medium | Metadata Quality | Description generation is generic excerpt/content truncation with no editor override logic. | Can lead to weak SERP snippets and duplicate-ish descriptions. | `inc/seo-all.php` | Add per-post custom meta description field with fallback hierarchy and sanitization. |
| Medium | Social Enrichment | OG/Twitter lacks article-specific enrichments and image dimension hints. | Can reduce social preview consistency and context quality. | `inc/seo-all.php` | Add article OG fields and optional `og:image:width/height`. |
| Medium | Content Semantics | Author/date rendered as plain spans, no machine-readable `time` semantics. | Missed semantic clarity for crawlers and assistive tooling. | `single-post.php` | Use `<time datetime>` and author URL/rel conventions where appropriate. |
| Medium | Media/Perf | Hero image requests `full` image eagerly. | Potentially heavier LCP payload and slower perceived load on mobile. | `single-post.php` | Serve tuned hero size/srcset strategy + dimensions + fetchpriority policy. |
| Low | Topical Coverage | Related posts uses only first category matching. | Limits relevance/coverage for multi-topic articles. | `single-post.php` | Expand related logic to tags + shared terms + recency/quality filters. |
| Low | Taxonomy Discovery | Tags not displayed on single-post template. | Reduces internal taxonomy navigation and topic clustering signal. | `single-post.php` | Add tag list with crawlable links if editorially appropriate. |
| Medium | Index Hygiene | Attachment pages are templated and potentially indexable. | Can create thin pages and index bloat if unmanaged. | `attachment.php`, `inc/seo-all.php` | Decide policy: noindex attachments or canonicalize to parent where suitable. |

## 10. Recommended implementation roadmap

### Sprint 1 (highest-value fixes)

1. Canonical deduplication for standard posts.
2. Add `BlogPosting` schema for `is_singular('post')`.
3. Add metadata override hierarchy for post descriptions (manual > excerpt > content).
4. Add semantic publish/modified `time` elements and author link semantics.

### Sprint 2 (secondary enhancements)

1. Add breadcrumb UI + breadcrumb schema for posts.
2. Add next/previous post links and optionally “more from topic” navigation.
3. Improve OG/Twitter article enrichments (`article:*`, dimensions, optional account handles).
4. Improve related-post relevance logic (multi-term matching + exclusion rules).

### Sprint 3 (nice-to-haves)

1. Add editorial quality guardrails (minimum intro length checks, optional lint tooling).
2. Add hero image performance tuning and consistent dimension strategy.
3. Evaluate attachment index policy and apply chosen rule.

## 11. PR slicing recommendation

1. **PR A — Canonical ownership + metadata safety**
   - Canonical dedupe for single posts.
   - Add tests/checks for one canonical only.
2. **PR B — Post schema graph**
   - Introduce `BlogPosting` + optional breadcrumb schema output for posts.
3. **PR C — Single-post semantic template pass**
   - Time/author semantics, tags, next/prev links, breadcrumb UI.
4. **PR D — Related content quality + performance polish**
   - Improve related query logic and hero image delivery strategy.

## Evidence notes

- **Code-level confirmed findings** are based on direct inspection of `single-post.php`, SEO loader/module files, and head output hooks.
- **Likely inferred behavior** includes duplicate canonical risk from WordPress core canonical output (not removed in theme code).
- **Unknowns requiring live runtime inspection** include active plugin interference (e.g., SEO plugins), final rendered head order, and exact output under production content conditions.
