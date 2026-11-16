# Entity registry — Pera Property

This registry is an **internal relationship model**, not public structured data. Identifiers are stable catalogue IDs, not schema.org IDs or WordPress term IDs. URL candidates are drawn from repository code and taxonomy inventories; they are not confirmed canonical URLs.

## Entities

| ID | Type | Name | Relationship / scope |
| --- | --- | --- | --- |
| `org:pera-property` | organisation | Pera Property | Parent business entity; website `https://www.peraproperty.com/` |
| `place:istanbul` | city | Istanbul | Geographic parent of listed administrative districts and neighbourhoods |
| `process:turkish-citizenship-property` | process | Turkish citizenship by property investment | Consultancy subject, not a guarantee of approval |
| `service:property-management` | service | Property management | Service offered by Pera Property; public service-page URL needs verification |
| `concept:property-purchase` | concept | Buying property in Turkey | Covers title deed, costs, due diligence and international buyers |

## Geographic entities

| Entity ID | Place name | Classification | Parent / caution |
| --- | --- | --- | --- |
| `area:kadikoy` | Kadıköy | district | Istanbul |
| `area:sisli` | Şişli | district | Istanbul |
| `area:besiktas` | Beşiktaş | district | Istanbul |
| `area:kagithane` | Kağıthane | district | Istanbul |
| `area:zeytinburnu` | Zeytinburnu | district | Istanbul |
| `area:kucukcekmece` | Küçükçekmece | district | Istanbul |
| `area:gaziosmanpasa` | Gaziosmanpaşa | district | Istanbul |
| `area:eyup` | Eyüpsultan (archive slug: eyup) | district | Istanbul; verify naming in live archive |
| `area:maltepe` | Maltepe | district | Istanbul |
| `area:umraniye` | Ümraniye | district | Istanbul |
| `area:atasehir` | Ataşehir | district | Istanbul |
| `area:istanbul-maslak-property-for-sale` | Maslak | neighbourhood / market area | Sarıyer; archive uses **region**, not district taxonomy |
| `area:bomonti` | Bomonti | neighbourhood | Şişli |
| `area:atakent` | Atakent | neighbourhood | Küçükçekmece |
| `area:halkali` | Halkalı | neighbourhood / area | Küçükçekmece |
| `area:topkapi` | Topkapı | neighbourhood / market label | Do not infer a district; validate addresses |
| `area:davutpasa` | Davutpaşa | neighbourhood / area | Do not relabel as Topkapı without geographic evidence |

## Relationships

- `org:pera-property` → **provides** → `service:property-management`
- `org:pera-property` → **advises-on** → `concept:property-purchase`
- `org:pera-property` → **advises-on** → `process:turkish-citizenship-property`
- `place:istanbul` → **contains** → Istanbul districts listed above
- `area:sisli` → **contains** → `area:bomonti`
- `area:kucukcekmece` → **contains** → `area:atakent` and `area:halkali`
- `area:istanbul-maslak-property-for-sale` → **part-of** → Sarıyer (Sarıyer entity to be added when mapped)

## Provenance and maintenance

- Blog matches: `docs/blog-post-inventory.csv`
- Taxonomy URLs and counts: `docs/taxonomy-inventory.csv`
- Existing llms endpoints and hub URL references: theme `inc/modules/llms.php`
- Current property and page objects still require WordPress inventory export.
- Do not conflate taxonomy labels with administrative geography.
- Historically correct citizenship guidance may be marked **superseded** with a link to newer guidance. The owner confirmed the older valuation article was correct when written and TRY 55,000 is currently the stated fee (2026-11-16); this is owner-provided context, not independent regulatory verification.
