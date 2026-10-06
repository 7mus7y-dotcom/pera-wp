# Property SEO content completeness audit

Run from the WordPress root with WP-CLI, ACF, and the Pera child theme loaded. The script explicitly loads the shared FAQ parser; no frontend or singular-property query context is required:

```sh
wp eval-file wp-content/themes/hello-elementor-child/tools/audit-property-seo-content.php status=publish limit=100
wp eval-file wp-content/themes/hello-elementor-child/tools/audit-property-seo-content.php status=publish,draft limit=100 offset=100
```

Pass audit options as positional `name=value` arguments. WP-CLI treats `--status` and `--limit` as command flags and rejects them before the script runs, including with a `--` separator.

Defaults: published and draft properties, limit 100, offset 0. Results are ordered by post ID ascending. Increase offset in batches until all properties are covered; run against a stable dataset for consistent pagination. Do not use `--skip-themes` or `--skip-plugins`. The script only reads posts, ACF fields, attachment metadata, and taxonomy relationships; it never updates content or rebuilds pricing indexes.

Each CSV row contains ID, title, status, and findings (or OK). Checks include:

- Missing or weak `property_editorial_intro`, `property_district_analysis`, and `property_investment_potential`: fewer than 30 whitespace-separated words after stripping HTML/shortcodes and decoding entities. This is a review heuristic, not a quality score; languages without word spacing need manual review.
- Missing `property_buyer_suitability` and an explicitly saved excerpt (auto-generated excerpts do not count).
- Fewer than three valid `property_faq_text` rows, using the same parser as the page: one `Question|Answer` per line with both parts nonempty.
- Missing/unresolvable `main_image` or blank alt text. A featured image or title fallback does not satisfy this field. URL-only images without stored alt need manual verification.
- Missing `district`, `region`, or `property_type` terms.
- Missing V2 rows for properties tagged `special=project` or `special=resales`, or missing positive numeric `v2_price_usd_min` in any existing V2 row. Other properties without V2 rows are not flagged. Confirm applicability manually: intentional price-on-request entries may legitimately lack numeric pricing; never invent a price to clear a finding.
- Empty/missing further reading `post_heading` relationship when its ACF field definition or post metadata exists. Relationships with no resolvable post IDs are flagged.

Review findings in the property editor. Check that editorial sections describe this specific property and district, buyer suitability is factual, FAQs answer real buyer questions, and image alt describes the image. Verify further-reading links are relevant and published. This audit does not assess factual accuracy, duplicates, stale prices, taxonomy specificity, or writing quality. It does not create schema, generate copy, or change any listing.
