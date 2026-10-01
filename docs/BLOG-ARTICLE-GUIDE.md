# Pera Property Blog Article Authoring Guide

This document is the canonical authoring guide for LLMs and contributors creating or revising editorial blog posts for Pera Property.

It documents the HTML patterns currently used in recent hand-built articles and the reusable component classes available in the theme. Follow these conventions instead of inventing article-specific markup or CSS.

## 1. Core principle

Article HTML should be semantic, restrained and reusable.

Most article content should consist of normal HTML:

- `<p>` for paragraphs
- `<h2>` for major sections
- `<h3>` for subsections
- `<ul>` / `<ol>` for ordinary lists
- `<strong>` and `<em>` where semantically appropriate
- `<a>` for contextual internal links

Use visual components only when they improve comprehension, comparison, navigation or conversion.

Do **not** turn every section into a card.

Do **not** invent new CSS classes from inside post content. If a new component is genuinely required, implement it in the theme first and document it here.

## 2. H1 and opening structure

Do not place an `<h1>` inside `post_content`. The single-post template supplies the article H1.

Preferred opening pattern:

```html
<p class="lead">A concise opening sentence or paragraph that immediately establishes the subject and search intent.</p>

<p>Supporting introductory paragraph.</p>

<p>Optional second introductory paragraph.</p>
```

`.lead` is defined globally and gives the introduction additional visual emphasis. Use one lead paragraph only.

A strong article normally answers or frames the primary search question immediately rather than delaying the subject behind a generic introduction.

## 3. Article hierarchy

Use:

```html
<h2>Major section</h2>
<p>...</p>

<h3>Subsection</h3>
<p>...</p>
```

Rules:

- H1: template only.
- H2: primary article sections.
- H3: subdivisions within an H2 section or headings inside cards.
- Do not skip heading levels for styling purposes.
- Do not use headings simply to make text larger.

## 4. “In This Guide” navigation block

For substantial guides, the preferred summary near the beginning is:

```html
<h2>In This Guide</h2>
<div class="mb-md">
  <ul class="checklist checklist--card checklist--premium">
    <li>First major subject</li>
    <li>Second major subject</li>
    <li>Third major subject</li>
  </ul>
</div>
```

Use this for comprehensive guides where a reader benefits from seeing the article scope at a glance.

For normal/non-premium editorial treatment, use an appropriate standard checklist treatment rather than automatically applying `checklist--premium`.

Do not duplicate every H2 word-for-word. The list should summarise reader outcomes or major questions.

## 5. Checklist and list system

Several list treatments exist. Choose according to purpose.

### 5.1 Ordinary factual list

Use plain semantic HTML when no visual emphasis is needed:

```html
<ul>
  <li>Item one</li>
  <li>Item two</li>
</ul>
```

This remains the default.

### 5.2 Tick list

Within article content, `.list-tick` removes normal bullets and displays a blue tick.

```html
<ul class="list-tick">
  <li>Feature or advantage</li>
  <li>Feature or advantage</li>
</ul>
```

Use for concise feature, benefit or qualification lists.

### 5.3 Circle checklist

The global component system supports:

```html
<ul class="checklist checklist--circle">
  <li>...</li>
</ul>
```

Use inside cards or content sections when a designed checklist is useful.

### 5.4 Card / boxed / premium checklists

The global stylesheet contains checklist surface variants including:

- `checklist--card`
- `checklist--boxed`
- `checklist--premium`

`checklist--premium` is the luxury/premium treatment and should be reserved for premium editorial contexts or a deliberately premium information block.

Do not make all lists premium merely because the class exists.

## 6. Card system

### 6.1 Standard card

The reusable standard card is:

```html
<div class="card-shell">
  <h3>Heading</h3>
  <p>Content.</p>
</div>
```

`card-shell` provides the standard white surface, neutral border, rounded corners, padding and subtle shadow.

Use for:

- key explanatory points
- comparisons
- warnings or distinctions that deserve separation
- compact summary blocks

Do not use it around ordinary prose sections.

### 6.2 Premium card

For luxury/premium editorial contexts:

```html
<div class="card-shell card-shell--premium">
  <h3>Heading</h3>
  <p>Content.</p>
</div>
```

The premium modifier uses a gold border treatment and additional subtle outline/shadow.

Use `card-shell--premium` when the article itself is deliberately positioned as premium/luxury content or the component represents a premium category.

Do not use the premium modifier in routine legal, tax, landlord, process or general informational articles unless there is a specific design reason.

### 6.3 Card headings

Prefer `<h3>` inside cards when the card belongs within an H2 section.

An H2 inside a card is acceptable when the card itself forms a major standalone article section, such as a major in-content exploration/CTA block.

## 7. Grids

Use grids when readers are genuinely comparing parallel concepts.

### Two-column tight grid

```html
<div class="grid-2--tight mb-md">
  <div class="card-shell">
    <h3>Option A</h3>
    <p>...</p>
  </div>
  <div class="card-shell">
    <h3>Option B</h3>
    <p>...</p>
  </div>
</div>
```

For premium articles:

```html
<div class="grid-2--tight mb-md">
  <div class="card-shell card-shell--premium">...</div>
  <div class="card-shell card-shell--premium">...</div>
</div>
```

### Three-column grid

```html
<div class="grid-3 mb-md">
  <div class="card-shell">...</div>
  <div class="card-shell">...</div>
  <div class="card-shell">...</div>
</div>
```

Use three columns only when three genuinely parallel categories benefit from side-by-side scanning.

Do not create grids simply to make an article look more designed.

## 8. Tables

For structured comparisons, use the established responsive WordPress table wrapper:

```html
<figure class="wp-block-table wp-block-table--responsive pt-sm">
<table>
<thead>
<tr>
<th>Column A</th>
<th>Column B</th>
<th>Column C</th>
</tr>
</thead>
<tbody>
<tr>
<td>...</td>
<td>...</td>
<td>...</td>
</tr>
</tbody>
</table>
</figure>
```

Use tables only when rows and columns make information materially easier to compare.

Do not use tables as a general layout mechanism.

## 9. CTA patterns

### 9.1 Article CTA action group

The reusable article button group is:

```html
<div class="article-cta-actions">
  <a class="btn btn--solid btn--green" href="...">Primary action</a>
  <a class="btn btn--ghost btn--green" href="...">Secondary action</a>
  <a class="btn btn--ghost btn--brand" href="...">Tertiary action</a>
</div>
```

The CSS deliberately makes the first CTA inside a `card-shell` full width, with second and third actions sharing the next row on larger screens and stacking on mobile.

Choose button colours and variants from existing theme classes. Do not invent inline colours.

### 9.2 Section CTA

For centred CTA groups, an existing pattern is:

```html
<div class="section-cta">
  <a href="..." class="btn btn--solid btn--blue">Primary action</a>
  <a href="..." class="btn btn--ghost btn--blue">Secondary action</a>
</div>
```

### 9.3 Closing commercial panel

Recent articles use a closing conversion section such as:

```html
<div class="content-panel mt-md">
  <h2>Relevant commercial question or service heading</h2>
  <p>Concise explanation of how Pera Property can help.</p>

  <div class="article-cta-actions">
    <a class="btn btn--solid btn--green" href="...">Primary action</a>
    <a class="btn btn--ghost btn--green" href="...">Secondary action</a>
    <a class="btn btn--ghost btn--brand" href="...">Browse property</a>
  </div>
</div>
```

Keep closing CTAs specific to the article's reader intent. Avoid generic sales copy.

### 9.4 Mid-article CTA

A single in-content CTA may be used when it is exceptionally relevant to the reader's next step.

Example pattern:

```html
<div class="card-shell card-shell--premium">
  <h2>Explore Luxury Property in Istanbul</h2>
  <p>...</p>
  <div class="section-cta">...</div>
</div>
```

Do not scatter CTA blocks throughout the article. Normally use no more than one meaningful mid-article CTA plus the closing CTA.

## 10. Buttons

Use existing global button classes.

Patterns already in active editorial content include:

- `btn btn--solid btn--blue`
- `btn btn--ghost btn--blue`
- `btn btn--solid btn--green`
- `btn btn--ghost btn--green`
- `btn btn--ghost btn--brand`

Do not create inline button styles.

Use visual hierarchy intentionally: one obvious primary action, then secondary actions.

## 11. Internal links

Internal links should be contextual and useful.

Preferred new-post convention:

```html
<a href="/relevant-slug/">descriptive anchor text</a>
```

Relative URLs are preferred for new internal links because they remain portable between environments.

Rules:

- Link where the reader naturally needs deeper information.
- Use descriptive anchor text.
- Do not force links to every remotely related article.
- Do not repeatedly link the same destination without a reader benefit.
- Avoid generic anchors such as “click here”.
- Check the actual WordPress slug before authoring the link.
- Preserve established URLs; do not infer a slug from the current title because older posts often retain legacy slugs.

## 12. External links and factual sourcing

For regulatory, legal, tax, immigration, market-data or government-process articles:

- research current primary sources before drafting
- prefer official Turkish government, regulator, municipality, legislation or statistical sources where available
- distinguish Pera's practical experience from statutory/legal claims
- do not copy large passages from sources
- verify current thresholds, deadlines, penalties and administrative procedures
- include a suitable information-only disclaimer when the subject warrants one

The article should read as Pera Property editorial content, not as a stitched collection of citations.

## 13. FAQ field

FAQs are not normally authored as HTML inside `post_content`.

Use the site's separate FAQ field in pipe format:

```text
Question one?|Answer one.

Question two?|Answer two.

Question three?|Answer three.
```

Rules:

- maximum 5 FAQs unless there is a clear reason otherwise
- answer directly in the first sentence
- avoid duplicating the article verbatim
- target genuine follow-up questions/search intent
- keep legal/regulatory answers appropriately qualified

## 14. Premium vs normal editorial treatment

The site deliberately supports both normal and premium visual treatments.

### Normal article

Use predominantly:

- semantic paragraphs/headings
- standard `card-shell`
- ordinary `<ul>` / `.list-tick`
- standard checklist treatments
- restrained green/brand/blue CTA hierarchy as appropriate

Typical subjects:

- tax
- legal/process guides
- landlord guidance
- citizenship process
- regulations
- market updates
- general district guides

### Premium article

May use:

- `card-shell card-shell--premium`
- `checklist--premium`
- premium cards inside `grid-2--tight` / `grid-3`
- stronger visual categorisation

Typical subjects:

- luxury property guides
- prime real estate
- high-end neighbourhood comparisons
- branded residences
- luxury villa markets

Premium styling should communicate editorial positioning, not decorate unrelated content.

## 15. Spacing utilities

The theme provides spacing utilities based on global spacing tokens. Existing article content uses classes such as:

- `mb-sm`
- `mb-md`
- `mt-md`
- `pt-sm`

Use existing utilities instead of inline `style="margin..."` or empty `<br>` elements.

Do not guess utility names. Confirm a class exists in `main.css` before introducing it into article content.

## 16. Responsive video embeds

`blog.css` provides an article-specific responsive video wrapper:

```html
<div class="video-embed">
  ... embed ...
</div>
```

The component uses a responsive 16:9 aspect ratio and article spacing. Use it for supported video embeds rather than manually sizing iframes.

## 17. HTML cleanliness

Required:

- valid, readable HTML
- semantic headings
- properly closed elements
- paragraph text inside `<p>` tags for new articles
- existing reusable classes only
- clean indentation for component blocks

Avoid:

- inline CSS
- `<font>` tags
- repeated `<br>` tags for spacing
- arbitrary fixed widths/heights
- empty paragraphs
- decorative wrappers with no purpose
- new class names that are not defined in the theme
- H1 inside post content
- copied Word/Google Docs formatting

Although WordPress may tolerate bare text between headings, new articles should use explicit `<p>` tags consistently.

## 18. Editorial component restraint

A well-designed Pera article is not a landing page.

As a rule of thumb:

1. Start with lead + normal prose.
2. Use an “In This Guide” checklist for substantial guides.
3. Let most sections remain normal H2/H3 + paragraphs.
4. Add cards/grids only for information that benefits from visual separation.
5. Use a table only for true structured comparison.
6. Use at most one strong mid-article CTA when contextually justified.
7. Finish with a relevant conversion panel.
8. Keep FAQs in the FAQ field.

## 19. SEO and search-intent discipline

Before creating a new article:

- inspect the existing article inventory
- identify the primary search intent
- check for an existing article serving the same intent
- update/expand an existing article instead of creating a cannibalising duplicate where appropriate
- identify supporting internal links before drafting
- distinguish informational intent from commercial intent
- ensure the article adds a genuinely new angle to the content cluster

Do not create another generic “complete guide” when a strong existing guide already serves the same query.

## 20. Content cluster strategy

New articles should normally strengthen an existing topic cluster.

Examples:

- Airbnb regulations → foreign-owner Airbnb questions → Airbnb property management → rental tax → landlord guidance
- luxury Istanbul → wealthy districts → Nişantaşı → Bosphorus → villa communities → luxury inventory
- citizenship requirements → valuation → DAB → Certificate of Conformity → citizenship timeline

Internal linking should help users move logically through these clusters rather than simply maximising link count.

## 21. Examples from current editorial practice

### Standard guide opening

```html
<p class="lead">Direct statement of the article's core subject and reader benefit.</p>

<p>Context and qualification.</p>

<h2>In This Guide</h2>
<div class="mb-md">
  <ul class="checklist checklist--card checklist--premium">
    <li>...</li>
    <li>...</li>
  </ul>
</div>
```

### Standard comparison

```html
<div class="grid-2--tight mb-sm">
  <div class="card-shell">
    <h3>Best for lifestyle buyers</h3>
    <p>...</p>
  </div>
  <div class="card-shell">
    <h3>Best for investment buyers</h3>
    <p>...</p>
  </div>
</div>
```

### Premium comparison

```html
<div class="grid-2--tight mb-md">
  <div class="card-shell card-shell--premium">
    <h3>Luxury Apartments</h3>
    <p>...</p>
  </div>
  <div class="card-shell card-shell--premium">
    <h3>Luxury Villas</h3>
    <p>...</p>
  </div>
</div>
```

## 22. Pre-publish LLM checklist

Before returning or publishing article HTML, verify:

- [ ] No H1 appears in `post_content`.
- [ ] The lead answers/frames the search intent immediately.
- [ ] Heading hierarchy is H2 → H3.
- [ ] New prose is wrapped in `<p>` tags.
- [ ] All classes used already exist in the theme.
- [ ] Premium classes are used only where editorially appropriate.
- [ ] Components are not overused.
- [ ] Internal URLs use verified existing slugs.
- [ ] Anchor text is descriptive.
- [ ] Regulatory/legal/tax claims have been checked against current authoritative sources.
- [ ] CTA copy matches the article's reader intent.
- [ ] FAQs are supplied separately in pipe format.
- [ ] No inline CSS or spacing hacks are present.
- [ ] Tables are responsive and used only where appropriate.
- [ ] Mobile behaviour has been considered for grids, CTAs and tables.
- [ ] The article does not unnecessarily duplicate an existing search intent.

## 23. Theme sources of truth

When in doubt, inspect the current theme rather than relying solely on this document.

Primary files:

- `wp-content/themes/hello-elementor-child/css/main.css` — global component system, cards, checklists, grids, buttons, typography, spacing utilities
- `wp-content/themes/hello-elementor-child/css/blog.css` — article-specific layout, article body behaviour, CTA groups, video embeds and blog/single-post styling
- `wp-content/themes/hello-elementor-child/css/posts.css` — post-card component styling
- `wp-content/themes/hello-elementor-child/css/cards-post.css` — sidebar/post strip card styling

If CSS and this guide diverge, the current CSS implementation is the technical source of truth. Update this document when intentionally changing the editorial component system.

---

**Maintenance rule:** whenever a new reusable blog/article component is added to the theme, update this guide in the same change or immediately afterwards so future LLMs do not invent competing markup patterns.
