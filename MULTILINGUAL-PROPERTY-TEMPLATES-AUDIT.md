# Multilingual Coverage Audit: Property Frontend Templates

**Scope:** `single-property.php`, `archive-property.php`, and their complete user-visible render dependency trees  
**Target languages:** Chinese (`zh`), Arabic (`ar`), German (`de`)  
**Canonical language:** English  
**Audit type:** Analysis only; no production behavior changed

## Executive summary

The audit found **10 confirmed translation-bypass findings**, **1 registered but potentially broken path**, and **7 appropriate intentional-exclusion groups**. There are no P0 findings.

The two manually observed symptoms have direct causes:

- **Price/range untranslated UI:** the shared units table renderer hard-codes its section title and conditional disclaimers instead of calling `pera_ml_ui()`.
- **Checkbox/form disclaimer untranslated UI:** the shared enquiry form emits the full consent sentence and Privacy Policy label as literal English, while the single-property caller supplies a raw English submit-label override.

Translation Health misses these cases because its UI inventory contains strings registered through `pera_ml_ui()`, while content inventory follows approved post-type/taxonomy field contracts. Raw PHP/HTML/JavaScript strings never enter either inventory. In two cases, correctly registered SSR/PHP text is later overwritten by raw English from AJAX or JavaScript, so Translation Health can report the registered identity as healthy while the frontend is English.

## Runtime trees audited

### Single Property

`single-property.php`

- WordPress header/footer and current-post loop
- `inc/v2-units-index.php`
  - unit aggregation
  - project/resale price formatting
  - units price table
- `parts/enquiry-form.php`
- `inc/property-card-helpers.php` → `parts/property-card-v2.php`
- `parts/post-card.php`
- `inc/seo-property.php` FAQ parsing
- shared favourites JavaScript
- translated title/excerpt/content filters
- ACF field-format translation filters

### Archive Property

`archive-property.php`

- archive/settings-page ACF content
- taxonomy archive helpers in `inc/seo-helpers.php`
- SSR property query and cards
- `inc/property-card-helpers.php` → `parts/property-card-v2.php`
- `inc/ajax-property-archive.php` AJAX filtering and pagination
- `inc/property-pagination.php`
- `parts/related-taxonomy-card.php`
- `inc/modules/faqs.php`
- inline archive filtering/load-more JavaScript
- shared favourites JavaScript
- translated title/excerpt/content and taxonomy-field paths

## Findings by severity

## P0 — Critical

No P0 findings.

## P1 — High

### ML-PROP-001 — Price-range renderer contains unregistered English copy

- **Classification:** D — TRANSLATION BYPASS
- **Templates affected:** Single Property directly; shared price/card dependencies are also used by Archive Property.
- **Files:**
  - `wp-content/themes/hello-elementor-child/single-property.php`
  - `wp-content/themes/hello-elementor-child/inc/v2-units-index.php`
- **Render path:** `single-property.php` → `pera_v2_render_units_price_table()` → raw `$pricing_title` / `$pricing_subtitle` → `<h2>` / `<p>`.
- **Examples:**
  - `Price range`
  - `Pricing`
  - `Indicative prices by unit type. Availability may change. Contact us for specific pricing and floor plans.`
  - `Final pricing is subject to negotiation with the seller and contract.`
  - project-specific final-pricing disclaimer
- **Current rendering mechanism:** plain PHP string assignment followed by escaping.
- **Why it fails:** escaping is not translation; none of these strings calls Pera ML.
- **Translation Health:**
  - Visible: **Yes**
  - Registered/discoverable: **No**
  - Missing row: **No**
  - Reason: the literals never call `pera_ml_ui()`.
- **Recommended remediation:** register each complete conditional title/subtitle through stable literal `pera_ml_ui()` keys.
- **Scope:** shared renderer, not duplicate template fixes.
- **Regression risk:** Low.

The table accessibility label, columns, update date notice, stale-price warning, and project note already use `pera_ml_ui()`. Card-level project `From %s` also uses `pera_ml_ui()` correctly.

### ML-PROP-002 — Enquiry consent and submit label bypass Pera ML

- **Classification:** D — TRANSLATION BYPASS
- **Templates affected:** Single Property.
- **Files:**
  - `wp-content/themes/hello-elementor-child/single-property.php`
  - `wp-content/themes/hello-elementor-child/parts/enquiry-form.php`
- **Render path:** `single-property.php` → `get_template_part('parts/enquiry-form', ..., $args)` → consent checkbox and submit button.
- **Examples:**
  - `I agree for Pera Property to contact me regarding this enquiry...`
  - `Privacy Policy`
  - `Request details`
- **Current rendering mechanism:** literal consent HTML; raw submit-label argument.
- **Why it fails:** consent and link label have no Pera ML call; the raw caller argument overrides the form's translated default.
- **Translation Health:**
  - Visible: **Yes**
  - Registered/discoverable: **No**
  - Missing row: **No**
- **Recommended remediation:** register consent and link text safely; translate or omit the submit override; localize the privacy URL.
- **Scope:** shared form for consent, local call site for submit override.
- **Regression risk:** Medium because translated sentence/link ordering must remain safe.

### ML-PROP-003 — AJAX result count is rebuilt as raw English

- **Classification:** D — TRANSLATION BYPASS
- **Templates affected:** Archive Property.
- **Files:**
  - `wp-content/themes/hello-elementor-child/archive-property.php`
  - `wp-content/themes/hello-elementor-child/inc/ajax-property-archive.php`
- **Render path:** translated SSR count → AJAX filter endpoint → JSON `count_text` → JavaScript replaces count.
- **Example:** `27 properties found`
- **Current rendering mechanism:** SSR uses `sprintf(pera_ml_ui(...))`; AJAX uses `$found . ' properties found'`.
- **Why it fails:** the translated initial value is replaced after filter/sort/load-more interaction.
- **Translation Health:**
  - Visible: **Yes, after AJAX**
  - Registered/discoverable: **SSR identity only**
  - Missing row: **Potentially no; SSR can be current**
  - Reason: AJAX bypasses the registered identity.
- **Recommended remediation:** reuse the exact SSR UI format identity in the AJAX callback.
- **Scope:** shared AJAX endpoint.
- **Regression risk:** Low.

### ML-PROP-004 — Taxonomy headings and contextual CTA copy are synthesized in English

- **Classification:** D — TRANSLATION BYPASS
- **Templates affected:** Archive Property taxonomy contexts.
- **Files:**
  - `wp-content/themes/hello-elementor-child/archive-property.php`
  - `wp-content/themes/hello-elementor-child/inc/seo-helpers.php`
- **Render path:** archive template → district/region/property-type heading helpers and taxonomy CTA helpers.
- **Examples:**
  - `Property for sale in Beşiktaş, Istanbul`
  - `Apartments for Sale in Istanbul`
  - `Looking for property in Beşiktaş?`
  - `Looking for property on the Bosphorus?`
  - `Speak to Pera Property for current availability...`
- **Current rendering mechanism:** raw `sprintf()` formats and raw slug-exception arrays.
- **Why it fails:** neither complete sentence formats nor interpolated term names use appropriate Pera ML helpers.
- **Translation Health:**
  - Visible: **Yes**
  - Registered/discoverable: **No**
  - Missing row: **No**
- **Recommended remediation:** translate complete formats/exceptions through stable UI keys and interpolate `pera_ml_term()` names.
- **Scope:** shared SEO/archive helper layer.
- **Regression risk:** Medium because visible headings and related SEO helpers must not be conflated accidentally.

### ML-PROP-005 — Manual taxonomy headings and fallback descriptions bypass taxonomy translation

- **Classification:** D — TRANSLATION BYPASS
- **Templates affected:** Archive Property taxonomy contexts.
- **Files:**
  - `wp-content/themes/hello-elementor-child/archive-property.php`
  - `wp-content/themes/hello-elementor-child/inc/seo-helpers.php`
  - `wp-content/themes/hello-elementor-child/parts/related-taxonomy-card.php`
- **Render paths:**
  - manual H1 aliases via `pera_get_property_archive_term_manual_heading()`
  - raw property-tag `archive_h1_title`
  - raw `term_excerpt` / `pera_term_excerpt`
  - raw `term_description()` fallback
  - related-card raw excerpt/description
- **Why it fails:** manual H1/excerpt aliases are outside the approved taxonomy contract; supported `term_description` is read without `pera_ml_term($term, 'description')`.
- **Translation Health:**
  - Visible: **Yes when populated/fallback selected**
  - Registered/discoverable: term description **Yes**, aliases/excerpts **No**
  - Missing row: term description may appear, but a current translation is still bypassed; aliases/excerpts never appear.
- **Recommended remediation:** choose canonical H1/excerpt fields, approve only those, and route description fallback through `pera_ml_term(..., 'description')`.
- **Scope:** systemic taxonomy contract.
- **Regression risk:** Medium-high because existing terms may use multiple legacy aliases.

### ML-PROP-006 — Archive settings content has a post-type contract mismatch

- **Classification:** E — REGISTERED BUT POTENTIALLY BROKEN
- **Templates affected:** Main Archive Property.
- **Files:**
  - `wp-content/themes/hello-elementor-child/archive-property.php`
  - `wp-content/plugins/pera-multilingual/includes/class-fields.php`
- **Affected sources:**
  - `archive_h1`
  - `archive_subtitle`
  - `archive_intro_content`
  - `archive_bottom_content`
  - `archive_cta_heading`
  - `archive_cta_text`
  - `archive_whatsapp_message`
- **Render path:** settings-page ID → `get_field('archive_*', page ID)` → ACF multilingual filter → `approved('page')` → canonical fallback.
- **Why it may fail:** archive fields occur in the legacy/post field list, while the actual settings object is a `page`. The page-specific contract excludes them. Union-based ACF hook registration makes the integration appear valid, but runtime resolution checks the actual post type.
- **Translation Health:**
  - Visible: **Yes when populated**
  - Registered/discoverable for the settings page: **No/incorrect**
  - Missing row: **Generally no**
- **Recommended remediation:** create an explicit, scoped archive-settings-page content contract and include its real fields in health/generation.
- **Scope:** systemic.
- **Regression risk:** Medium; blindly adding fields to every page would over-broaden inventory.

### ML-PROP-007 — Direct `home_url()` links do not reliably preserve translated routes

- **Classification:** D — TRANSLATION BYPASS
- **Templates affected:** Both.
- **Files:**
  - `wp-content/themes/hello-elementor-child/single-property.php`
  - `wp-content/themes/hello-elementor-child/archive-property.php`
  - `wp-content/themes/hello-elementor-child/parts/enquiry-form.php`
  - `wp-content/plugins/pera-multilingual/includes/class-router.php`
- **Examples:** Privacy Policy, Contact, Book a Consultancy, Citizenship by Investment, and hard-coded district links.
- **Current rendering mechanism:** direct `home_url('/path/')` construction.
- **Why it fails:** Pera ML filters post/page/post-type/term links, not general `home_url()`. Constructed paths require `pera_ml_url()` or object-aware link resolvers.
- **Translation Health:**
  - Visible behavior: **Yes**
  - Registered/discoverable: **No**
  - Missing row: **No; routes are outside copy health**
- **Recommended remediation:** standardize internal URLs on WordPress object link functions or `pera_ml_url(home_url(...))` for constructed paths.
- **Scope:** systemic URL contract first, then local call sites.
- **Regression risk:** Medium due to possible double language prefixes.

## P2 — Medium

### ML-PROP-008 — Related-taxonomy headings and button nouns remain English

- **Classification:** D — TRANSLATION BYPASS
- **Templates affected:** Archive Property taxonomy contexts.
- **Files:**
  - `wp-content/themes/hello-elementor-child/archive-property.php`
  - `wp-content/themes/hello-elementor-child/parts/related-taxonomy-card.php`
- **Examples:** `Related districts`, `Related regions`, `Related tags`, `View district`, `View region`, `View tag`.
- **Current rendering mechanism:** raw group headings/nouns; only `View %s` is registered.
- **Why it fails:** translating the surrounding format cannot translate the inserted English noun and may produce invalid target-language grammar.
- **Translation Health:** format string **Yes**; headings/nouns **No**.
- **Recommended remediation:** register full semantic variants rather than inserting untranslated nouns.
- **Scope:** shared component.
- **Regression risk:** Low.

### ML-PROP-009 — Favourites JavaScript overwrites translated accessibility labels

- **Classification:** D — TRANSLATION BYPASS
- **Templates affected:** Both.
- **Files:**
  - `wp-content/themes/hello-elementor-child/js/favourites.js`
  - `wp-content/themes/hello-elementor-child/single-property.php`
  - `wp-content/themes/hello-elementor-child/parts/property-card-v2.php`
- **Examples:** `Remove from favourites`, `Add to favourites`.
- **Render path:** translated PHP `aria-label` → favourites initialization/toggle → raw JS `setAttribute()` overwrite.
- **Translation Health:** PHP add-label can be current while runtime accessibility text is English; remove-label is undiscoverable.
- **Recommended remediation:** pass registered state labels into JavaScript configuration or translated `data-*` attributes.
- **Scope:** shared favourites component.
- **Regression risk:** Low.

### ML-PROP-010 — Advisor position is unsupported free-text metadata

- **Classification:** D — TRANSLATION BYPASS
- **Templates affected:** Single Property.
- **Files:**
  - `wp-content/themes/hello-elementor-child/single-property.php`
  - `wp-content/plugins/pera-multilingual/includes/class-fields.php`
- **Example:** `Senior Property Advisor`.
- **Render path:** selected team/advisor post → `get_field('position')` → raw output.
- **Why it fails:** `position` is not in a team/post multilingual field contract.
- **Translation Health:** visible **Yes**; discoverable/missing row **No**.
- **Recommended remediation:** define a translatable team-position field or a controlled role vocabulary.
- **Scope:** systemic content-type contract.
- **Regression risk:** Medium; depends on whether roles are free text or controlled.

## P3 — Low

### ML-PROP-011 — Minor admin/accessibility text remains outside Pera ML

- **Classification:** D for confirmed literals; F for media metadata policy.
- **Templates affected:** Primarily Single Property; shared cards affect both.
- **Files:**
  - `wp-content/themes/hello-elementor-child/single-property.php`
  - `wp-content/themes/hello-elementor-child/parts/property-card-v2.php`
- **Examples:** raw `Q:` FAQ prefix, frontend-admin `Edit` pill, custom attachment/floor-plan alt text.
- **Translation Health:** none is discoverable.
- **Recommended remediation:** remove or register `Q:`; decide whether frontend-admin copy needs visitor-language localization; establish a deliberate media-alt translation policy.
- **Scope:** mixed local/shared/systemic.
- **Regression risk:** Low for literals, medium for media metadata.

## Coverage table

| Area | Single Property | Archive Property | Translation mechanism | Translation Health coverage | Status | Notes |
|---|---|---|---|---|---|---|
| Property title | `get_the_title()` | Shared cards | `Pera_ML_Content::title()` | Yes | PASS | Translated-route titles use the content filter. |
| Property excerpt | Hero/cards | Cards | `get_the_excerpt` filter | Yes | PASS | Correct runtime path. |
| Property descriptions/editorial | Multiple ACF fields | Card excerpts | Approved property field contract | Yes | PASS | Summary/about/editorial/FAQ text fields are approved. |
| Facilities | Pills | N/A | Controlled vocabulary | Controlled contract | PASS | Appropriate controlled-field translation. |
| Key advantages | Pills | N/A | Controlled vocabulary | Controlled contract | PASS | Appropriate. |
| Target buyers | Pills | N/A | Controlled vocabulary | Controlled contract | PASS | Appropriate. |
| Price amounts | Hero/table/cards | Filters/cards | Numeric formatting | Excluded | INTENTIONAL | Numeric values do not require translation. |
| Currency symbols/codes | `$`, `USD` | `$`, `USD` | Technical identifiers | Excluded | INTENTIONAL | Appropriate neutral identifiers. |
| Hero `From` | `pera_ml_ui('From')` | N/A | UI registry | Yes | PASS | Correct. |
| Card `From` | Shared formatter | Shared formatter | `pera_ml_ui('From %s')` | Yes | PASS | Correct current shared path. |
| Price table copy | Shared renderer | N/A | Mixed raw/UI | Partial | BYPASS | ML-PROP-001. |
| Bedrooms | Numeric `N+1` + UI headings | Numeric filters/cards | Numeric/UI | Labels yes | PASS | No raw bedroom noun in pills. |
| Bathrooms | No active renderer found | No active renderer found | N/A | N/A | INTENTIONAL | Not in current render trees. |
| Size/measurements | Numeric + `m²` | Numeric + `m²` | Numeric formatting | Excluded | INTENTIONAL | Standard unit symbol. |
| Property type | Terms | Vocabulary/terms | `pera_ml_term()` / `pera_ml_vocab()` | Yes | PASS | Archive filter types use controlled vocabulary. |
| District/region | Terms | Hero/filters/cards | `pera_ml_term()` | Yes | PASS | Correct standard term labels. |
| Project/resale badges | Shared cards | Shared cards | `pera_ml_term()` | Yes | PASS | Tooltips also use UI registration. |
| Taxonomy H1 | N/A | Synthesized/manual | Raw helpers/meta | No/partial | BROKEN | ML-PROP-004/005. |
| Taxonomy subtitle/body | N/A | District/region fields | `pera_ml_term_meta()` | Yes | PASS | Approved paths are correct. |
| Term excerpt/description fallback | N/A | Hero/related cards | Raw meta/description | Partial | BYPASS | ML-PROP-005. |
| Main archive settings content | N/A | Settings page ACF | Wrong post-type contract | No effective coverage | BROKEN | ML-PROP-006. |
| Archive fallback UI copy | N/A | Main/search archive | `pera_ml_ui()` | Yes | PASS | Template is in approved static discovery. |
| Filter labels/options | N/A | All principal controls | UI/vocabulary/terms | Yes | PASS | Price/type/beds/location/tags/keyword covered. |
| Sorting controls | N/A | Newest/Oldest/Price | `pera_ml_ui()` | Yes | PASS | Covered. |
| Initial result count | N/A | SSR | `pera_ml_ui()` | Yes | PASS | Correct before AJAX. |
| AJAX result count | N/A | Filtered results | Raw JSON text | No | BYPASS | ML-PROP-003. |
| Empty/loading/error states | Related state | SSR/JS/AJAX | `pera_ml_ui()` | Yes | PASS | Main states covered. |
| Property cards | Related properties | SSR/AJAX results | Content/term/UI helpers | Mostly | PARTIAL | Favourites JS is an exception. |
| Pagination/load more | N/A | SSR/AJAX | Shared pagination/UI | Yes | PASS | Covered. |
| Map/location labels | UI helpers | No map UI here | `pera_ml_ui()` | Yes | PASS | Address value remains investigation. |
| Distances | Approved ACF field | N/A | ACF field translation | Yes | PASS | Covered. |
| Floor plans | Approved ACF/UI | N/A | Field/UI translation | Yes | PASS | Media alt metadata is separate. |
| Video | Approved ACF/UI | N/A | Field/UI translation | Yes | PASS | Custom apartment-tour block is disabled; active fallback paths translate. |
| Gallery UI | UI labels/states | Card media | `pera_ml_ui()` | Yes | PASS | Media metadata not included. |
| Advisor names | Proper names/title | N/A | Title filter/proper name | Appropriate exclusion | INTENTIONAL | Names should generally remain canonical. |
| Advisor positions | Raw ACF | N/A | None | No | BYPASS | ML-PROP-010. |
| Form labels/placeholders | Shared form | No archive form | `pera_ml_ui()` | Yes | PASS | Most form UI is correct. |
| Consent/privacy | Raw shared form | No archive form | None | No | BYPASS | ML-PROP-002. |
| Submit button | Raw override | No archive form | None | No | BYPASS | ML-PROP-002. |
| Form success/failure | Shared form/template | N/A | `pera_ml_ui()` | Yes | PASS | Covered. |
| Browser validation | Native controls | N/A | Browser-owned | N/A | INTENTIONAL | No custom English JS validation found. |
| WhatsApp labels/messages | UI helpers | UI/config field | Mixed | Partial | PARTIAL | Custom archive message falls under ML-PROP-006. |
| Related taxonomy cards | N/A | Taxonomy archives | Terms/UI | Partial | PARTIAL | Headings/nouns/excerpts have gaps. |
| Further reading | Post cards | N/A | Content/term/UI filters | Yes | PASS | Titles/excerpts/categories covered. |
| FAQs | Approved property field | Approved page/term FAQ field | Field/term meta | Yes | PARTIAL | Content works; raw `Q:` is low severity. |
| Internal URLs | Mixed | Mixed | Link filters/occasional `pera_ml_url()` | Outside Health | PARTIAL | ML-PROP-007. |
| Header/footer/menu | Shared | Shared | Current global ML/menu mechanisms | Shared | PASS | No current template-specific regression found. |

## Translation Health/discovery conclusions

`Pera_ML_UI::get()` registers strings only when `pera_ml_ui()` is called, and falls back to canonical English for absent, stale, or non-current rows. Theme discovery statically scans literal `pera_ml_ui(source, key)` calls in approved files. It does not discover raw HTML, variable string assignments, concatenated AJAX text, or JavaScript literals.

Field health follows the approved post-type/taxonomy contract. Merely registering a union-wide ACF format hook does not make a field valid for an object's real post type.

Consequently:

- ML-PROP-001, 002, 004, 007, 008, and 010 are entirely invisible to Translation Health.
- ML-PROP-003 and 009 can leave Translation Health green because registered SSR/PHP text exists, while AJAX/JS overwrites it with English.
- ML-PROP-005 mixes unsupported metadata with a supported term-description source that its render path does not consume.
- ML-PROP-006 is a runtime post-type contract mismatch and is not accurately inventoried for the settings page.

## Intentional exclusions

Seven appropriate exclusion groups were identified:

1. Price amounts.
2. Bedroom/unit counts such as `2+1`.
3. Measurements and `m²`.
4. Currency identifiers such as `$` and `USD`.
5. Dates as data, assuming WordPress locale selection is active.
6. Proper personal and place names.
7. Media, icons, coordinates, technical IDs, and query parameters.

Addresses and custom attachment alt text were not automatically declared neutral; they require an explicit policy.

## Recommended remediation sequence

1. **Low-risk shared UI fixes**
   - Register price renderer headings/disclaimers.
   - Reuse the SSR result-count identity in AJAX.
   - Pass registered favourites state labels to JavaScript.
   - Register complete related-taxonomy labels.
   - Remove or register `Q:`.
2. **Shared form fix**
   - Register consent and Privacy Policy copy safely.
   - Remove/translate the raw submit override.
   - Localize the privacy URL.
3. **Archive settings contract**
   - Define the settings page as a scoped multilingual content owner.
   - Add all real archive fields to generation and Health.
4. **Taxonomy contract cleanup**
   - Select canonical H1/excerpt fields.
   - Translate description fallbacks correctly.
   - Register complete synthesized heading/CTA formats.
5. **Internal route localization**
   - Prefer object-aware WordPress link functions.
   - Use `pera_ml_url()` for constructed internal paths.
   - Test against double-prefixing.
6. **Lower-priority contracts**
   - Decide team-position vocabulary/free-text behavior.
   - Define media-alt and map-address translation policies.

## Files likely to change in a future implementation PR

### Theme

- `wp-content/themes/hello-elementor-child/single-property.php`
- `wp-content/themes/hello-elementor-child/archive-property.php`
- `wp-content/themes/hello-elementor-child/parts/enquiry-form.php`
- `wp-content/themes/hello-elementor-child/parts/related-taxonomy-card.php`
- `wp-content/themes/hello-elementor-child/inc/v2-units-index.php`
- `wp-content/themes/hello-elementor-child/inc/ajax-property-archive.php`
- `wp-content/themes/hello-elementor-child/inc/seo-helpers.php`
- `wp-content/themes/hello-elementor-child/js/favourites.js`

### Pera Multilingual

- `wp-content/plugins/pera-multilingual/includes/class-fields.php`
- Potentially `wp-content/plugins/pera-multilingual/includes/class-translation-health.php` for scoped settings-page inventory.
- `class-theme-ui-discovery.php` should not require changes for ordinary literal `pera_ml_ui()` fixes.

## Final totals

- **Confirmed translation bypasses:** 10 findings.
- **Registered but potentially broken paths:** 1 finding.
- **Intentional exclusions:** 7 groups.
- **Manual price/range symptom:** explained by ML-PROP-001.
- **Manual checkbox/disclaimer symptom:** explained by ML-PROP-002.
