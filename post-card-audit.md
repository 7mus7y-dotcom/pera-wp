# Post Card Usage & Styling Audit

## A) Canonical template

- **Canonical post card template:** `wp-content/themes/hello-elementor-child/parts/post-card.php`.
- There is **no** `partials/post-card.php` (or any second post-card template file) in this theme.
- This canonical template is a configurable component fed by `set_query_var( 'pera_post_card_args', ... )` and rendered via `get_template_part( 'parts/post-card' )`.

## B) Usage map

### Shared template usages (direct)

| File | Usage type | Likely frontend context | Shared template or duplicate |
|---|---|---|---|
| `wp-content/themes/hello-elementor-child/archive.php` | Loop calls `set_query_var(...)` + `get_template_part( 'parts/post-card' )` | Blog/category/tag/date archives (`.section-posts .cards-masonry`) | **Shared template** |
| `wp-content/themes/hello-elementor-child/parts/home-editorial-posts.php` (included by `home-page.php`) | Editorial query loop calls shared template, slider variant | Home page “Latest Istanbul property insights” slider | **Shared template** |
| `wp-content/themes/hello-elementor-child/single-post.php` | Related posts sidebar slider uses shared template (`variant=sidebar`) | Single blog post page, right sidebar “Related articles” | **Shared template** |
| `wp-content/themes/hello-elementor-child/single-property.php` | Further reading slider loop uses shared template (`variant=sidebar`) | Single property page “Further reading” section | **Shared template** |
| `wp-content/themes/hello-elementor-child/archive/single-property-v2.php` | Same further-reading loop pattern as `single-property.php` | Legacy/archived template copy (no active routing reference found) | **Shared template** (in that file) |

### Duplicate/custom markup (not using shared template)

| File | Usage type | Likely frontend context | Shared template or duplicate |
|---|---|---|---|
| `wp-content/themes/hello-elementor-child/page-posts.php` | Inline hard-coded post card markup using `post-card*` classes | “Blog / Posts (Lean)” page template | **Custom duplicate** |
| `wp-content/themes/hello-elementor-child/parts/home-editorial-posts.php` | Inline CTA card in slider with `post-card` classes | Last card in home editorial slider (“Want to see more?”) | **Custom sibling card** |

## C) Styling audit

## Shared card structure/classes (canonical template)

The canonical `parts/post-card.php` uses:

- Wrapper/article: `post-card`, `post-card--{variant}`, `pera-card-shell`.
- Media: `post-card-media`, `post-card-thumb`, `post-card-thumb-placeholder`, `post-card-thumb-overlay`.
- Content: `post-card-body`, `post-card-title`, `post-card-excerpt`, `post-card-date`, `post-card-readmore`.
- Badges/pills in overlay: `pill`, `pill--green`, `pill--blue`, `post-card-subtitle`.

### CSS sources affecting card appearance

- `css/posts.css`: primary post-card component rules (base, thumb, overlay, typography, sidebar variant, editorial slider sizing).
- `css/main.css`: shared shell styling (`.pera-card-shell`) and global utilities/tokens.
- `css/blog.css`: archive-specific masonry and media sizing overrides for `.section-posts .cards-masonry` contexts.
- `css/slider.css`: slider container/card width/overflow behavior for `.cards-slider`, `.slider-card`, `.cards-slider--sidebar`, etc.

### Context and loading implications

- `inc/modules/enqueue-assets.php` enqueues `posts.css` for home, blog page template, posts index, single post, blog archive, and single property.
- `slider.css` is enqueued where slider contexts appear (home, single post, single property, etc.).
- `main.css` is globally enqueued (except CRM shell routes).

### Divergence findings

1. **Canonical usages are structurally consistent** when they call `get_template_part( 'parts/post-card' )`.
2. **Presentation still varies by context wrappers**:
   - archive context (`.cards-masonry`) gets masonry-specific sizing in `blog.css`;
   - slider contexts (`.cards-slider`, `.slider-card`) get width/snap behavior in `slider.css`;
   - home editorial slider adds `home-editorial-posts__slider` sizing overrides in `posts.css`.
3. **`page-posts.php` is a divergent duplicate implementation**:
   - Does not use `parts/post-card.php`.
   - Uses `post-card-cat` and `post-card-meta` row (canonical now uses overlay pills, not `.post-card-cat`).
   - Uses fallback placeholder text instead of logo placeholder used by canonical template.
   - Uses `class="btnbtn btn--solid btn--black post-card-readmore"` (likely typo / style mismatch vs canonical button classes).
4. **Legacy knob mismatch:** call sites pass `'pill_class' => 'pill pill--outline'`, but `parts/post-card.php` currently hardcodes pill classes (`pill--green`/`pill--blue`) and does not consume `pill_class`.

## D) Verdict

**Partially consistent.**

- Most live usages share a canonical template (`parts/post-card.php`).
- However, real presentation differs by wrapper context (masonry vs slider).
- Also, there is at least one true duplicate implementation (`page-posts.php`) with divergent markup/classes.

## E) Minimal cleanup actions

1. **Refactor `page-posts.php`** loop to call `get_template_part( 'parts/post-card' )` with `set_query_var( 'pera_post_card_args', ... )`.
2. **Decide and enforce one category treatment** (overlay pill vs meta-row category link), then remove stale selectors/markup.
3. **Either wire `pill_class` into `parts/post-card.php` or remove it from all call sites** to avoid misleading configuration drift.
4. (Optional) Confirm whether `archive/single-property-v2.php` is intentionally kept as legacy; if not used, remove/archive clearly to reduce duplicate maintenance surface.
