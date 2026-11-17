# Pera Property — AI SEO Knowledge Catalogue

Status: repository-mapped catalogue; 130 property canonicals verified by owner-run HTTP audit (2026-11-17). Topic-map canonicals and broader Google indexation remain unverified. This is an internal catalogue, **not** a public AI feed or a ranking guarantee.

## Purpose
Maintain a source-controlled inventory of entities, topics, questions, evidence, canonical public URLs and internal linking opportunities. Public, crawlable WordPress pages remain the primary discoverable source.

## Editorial rules
- Verify legal, tax, financial and citizenship claims against current primary sources before publication.
- Do not invent statistics, reviews, credentials, prices or case studies.
- Record canonical URLs only after verifying the live page; use `null` otherwise.
- Distinguish factual claims, editorial recommendations and unverified hypotheses.
- Link to relevant pages naturally; avoid thin pages or mass-produced FAQs.
- Keep private client data, contracts, CRM data and credentials out of public content.
- Use Schema.org types only where accurately supported by visible page content.
- Review citizenship rules and price-sensitive topics at least quarterly.

## Sections
- [Company entity](company.md)
- [Citizenship](citizenship.md)
- [Districts](districts.md)
- [Property investment](property-investment.md)
- [Property management](property-management.md)
- [Machine-readable topic map](topic-map.json)
- [Entity registry](entity-registry.md)
- [Property CPT catalogue](property-cpt.md) ([JSON](property-cpt.json))
- [Live verification checklist](live-verification-checklist.md)

## Workflow
1. Audit current WordPress posts, pages, property CPT and canonical URLs.
2. Populate `topic-map.json` with verified URLs and content status.
3. Prioritise improvements to existing pages over duplicating them.
4. Add sourced answers, links and suitable structured data to public pages.
5. Track Search Console impressions/clicks and manually validate AI citations separately.

## Status meanings
`inventory-needed` = subject known, public URL not yet verified; `existing-verified` = canonical public page checked; `gap` = no adequate public content confirmed; `review` = factual refresh needed.

## Verification evidence (2026-11-17)
- [130-property HTTP audit](property-http-verification-2026-11-17.md): 130 HTTP 200, self-referencing canonicals, no reported issues.
- [12-property multilingual sample](hreflang-sample-verification-2026-11-17.md): 60 language targets, no reported issues.
- Property district links: 128 explicit district mappings; two Bahçeşehir region-only entries without inferred administrative districts.
