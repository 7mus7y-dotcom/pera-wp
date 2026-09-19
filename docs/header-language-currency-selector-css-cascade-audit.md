# Header Language and Currency Selector CSS Cascade Audit

## Scope and constraints

This is a repository-only DOM and CSS cascade audit performed after PR #1335. No browser-based QA, production requests, or dependency installation was used.

The audit compares:

- the desktop header Language selector;
- the desktop header Currency selector;
- their mobile/off-canvas variants;
- global link, button, header, navigation, typography, responsive, and RTL rules that can affect them.

## Executive conclusion

The remaining desktop mismatch is **not caused by a repository-level global `button` rule**. The Currency buttons have explicit component resets, including `border`, `background`, `font`, padding, dimensions, and text alignment.

The primary cause is instead this overly broad header anchor selector in `css/main.css`:

```css
#site-header .header-icons a
```

It was written for header icon links, but it also matches every Language dropdown option because those options are nested `a` elements inside `.header-icons`. Its ID-based specificity overrides the intended Language option `display`, `padding`, and `line-height`. Currency options are `button` elements and are not matched.

Consequently, PR #1335 made the Currency options match the **declared** Language styles, but the Language options do not receive all of those declared styles in the effective desktop cascade.

The root-cause fix can remain CSS-only.

## DOM comparison

### Desktop Language selector

`inc/theme-helpers.php:839-859` renders:

```html
<nav class="header-language-switcher header-language-switcher--desktop">
  <button class="header-language-switcher__toggle">...</button>
  <ul class="header-language-switcher__list">
    <li><a ...>Language name</a></li>
  </ul>
</nav>
```

The trigger is a `button`, while every option is an `a`.

### Desktop Currency selector

`inc/theme-helpers.php:875-889` renders:

```html
<div class="pera-currency-selector pera-currency-selector--header">
  <button class="pera-currency-selector__trigger">...</button>
  <div class="pera-currency-selector__list">
    <button type="button" dir="ltr">USD</button>
  </div>
</div>
```

Both the trigger and options are `button` elements.

### Header placement

`header.php:78-84` renders both desktop selectors as children of `.header-icons`. This is what lets the descendant selector `#site-header .header-icons a` reach into the Language dropdown.

The mobile Language and Currency selectors are rendered in the off-canvas area at `header.php:148-150`, outside `.header-icons`. The desktop anchor collision therefore does not affect the mobile Language options.

## Shared typography and inheritance

The body rule at `css/main.css:264-272` establishes:

```css
font-family: 'Montserrat', system-ui, -apple-system, "Segoe UI", Arial, sans-serif;
font-weight: 400;
font-size: 1rem;
line-height: 1.6;
```

The two selector roots then independently establish the same typography:

```css
font-size: 0.78rem;
line-height: 1.2;
```

Neither changes `font-family` or normal-state `font-weight`, so both inherit Montserrat and weight 400.

## Desktop trigger comparison

### Language trigger

The rule at `css/main.css:1494-1505` gives the Language trigger:

- `min-width: 42px`;
- `min-height: 30px`;
- `padding: 0 6px`;
- `border: 0`;
- `border-radius: 999px`;
- transparent background;
- inherited color and font;
- `font-weight: 600`.

It does not define `display`, `align-items`, `justify-content`, or `text-align`, so its layout model still depends on browser button defaults.

### Currency trigger

The rule at `css/main.css:1578-1592` gives the Currency trigger the same dimensions and typography, plus:

```css
display: inline-flex;
align-items: center;
justify-content: center;
```

Therefore, even the closed controls are not cascade-identical. The difference can affect text/chevron centering and baseline placement.

| Property | Language trigger | Currency trigger |
| --- | --- | --- |
| `font-family` | inherited Montserrat | inherited Montserrat |
| `font-size` | `0.78rem` | `0.78rem` |
| `font-weight` | `600` | `600` |
| `line-height` | `1.2` | `1.2` |
| `padding` | `0 6px` | `0 6px` |
| `min-height` | `30px` | `30px` |
| `min-width` | `42px` | `42px` |
| `display` | browser button default | `inline-flex` |
| `align-items` | not set | `center` |
| `justify-content` | not set | `center` |
| `border-radius` | `999px` | `999px` |

## Desktop dropdown panels

The desktop panels at `css/main.css:1507-1528` and `1594-1610` are effectively matched for:

- absolute positioning beneath the trigger;
- closed `display: none` and open `display: block`;
- `width: max-content`;
- `max-width: calc(100vw - 16px)`;
- `box-sizing: border-box`;
- `padding: 6px`;
- border, radius, background, and shadow.

One positioning difference remains:

- Language uses physical `top`, `left`, and `right` properties.
- Currency uses logical `inset-block-start` and `inset-inline-start` properties.

This is principally relevant under RTL.

## Desktop option cascade

### Intended Language option styles

`css/main.css:1529-1537` declares:

```css
.header-language-switcher__list a {
  display: block;
  padding: 11px 12px;
  min-height: 20px;
  border-radius: calc(var(--radius-md) - 4px);
  color: #fff;
  line-height: 1.25;
  text-decoration: none;
  white-space: nowrap;
}
```

### Currency option styles after PR #1335

The generic reset at `css/main.css:1611-1623` explicitly establishes the Currency option's display, dimensions, padding, border, radius, background, color, inherited font, and text alignment.

The header override added in PR #1335 at `css/main.css:1624-1631` then declares:

```css
.pera-currency-selector--header .pera-currency-selector__list button {
  min-width: 0;
  min-height: 20px;
  padding: 11px 12px;
  line-height: 1.25;
  text-align: start;
  white-space: nowrap;
}
```

Those values correctly match the intended Language option box model.

### Winning collision

Later in `main.css`, the following rule appears at lines 1753-1765:

```css
#site-header .header-icons a,
#site-header .header-icons .header-menu-toggle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  line-height: 0;
  background: none;
  border: 0;
  padding: 0;
  cursor: pointer;
  color: inherit;
  text-decoration: none;
}
```

Approximate specificity:

- `.header-language-switcher__list a`: `0-1-1`;
- `#site-header .header-icons a`: `1-1-1`.

The ID-based rule wins regardless of source order.

### Effective desktop option values

| Property | Language option | Currency option |
| --- | --- | --- |
| `font-family` | Montserrat | Montserrat |
| `font-size` | `0.78rem` | `0.78rem` |
| `font-weight` | `400` normally | `400` normally |
| `line-height` | **`0`** | `1.25` |
| `padding` | **`0`** | `11px 12px` |
| `min-height` | `20px` | `20px` |
| `min-width` | `auto` | `0` |
| `width` | `auto` | `auto` |
| `display` | **`inline-flex`** | `block` |
| `align-items` | **`center`** | not applicable |
| `justify-content` | **`center`** | not applicable |
| `text-align` | flex centering dominates | `start` |
| `white-space` | `nowrap` | `nowrap` |
| `border-radius` | shared calculated radius | shared calculated radius |

This is the exact remaining desktop mismatch.

## Hover and focus differences

The global anchor rules at `css/main.css:281-294` set normal anchors to weight 400 and hovered/focused anchors to weight 600.

The Language component hover rule only changes the background. Therefore, an inactive Language option becomes weight 600 on hover/focus. The corresponding Currency option remains weight 400 because no global button hover rule changes its weight.

The current Language and Currency options both use weight 700 through their respective active-state rules.

There is a further scrolled-header leak at `css/main.css:1835-1839`:

```css
#site-header.is-scrolled .header-icons a:hover
```

It applies `opacity: 0.85` to Language dropdown links but not Currency buttons.

## Global button assessment

There is no unscoped frontend `button { ... }` rule in the child theme's global stylesheet.

The Currency options explicitly reset the relevant native button properties with `border: 0`, transparent background, `font: inherit`, explicit padding, minimum dimensions, and text alignment at `css/main.css:1611-1623`.

The Pera Currency plugin's stylesheet contains only:

```css
[data-pera-money] { direction: ltr; unicode-bidi: isolate; }
```

It cannot affect the selector UI.

The project also disables the Hello Elementor parent reset/theme styles in `inc/disable-hello-parent-loads.php:10-19`.

Therefore, within repository-controlled CSS, **global button styling is not the cause**. Browser defaults matter only where component CSS leaves a property unspecified, such as the Language trigger's display model. The confirmed option mismatch is caused by the overly broad header anchor rule.

Externally generated Elementor CSS, Customizer CSS, or production cache artifacts are outside the scope of what can be proven by this repository-only audit.

## Responsive audit

### Desktop: above 767px

Both desktop selectors are in `.header-icons`, so the broad anchor rule affects Language options throughout the desktop range. No later desktop media rule restores the intended padding or line-height.

### Tablet: 641-767px

At `max-width: 767px`, `css/main.css:1547-1553` attempts to make desktop Language options flex items with centered cross-axis alignment. The higher-specificity `#site-header .header-icons a` rule still wins for `display` and continues to impose `inline-flex`, `justify-content: center`, `line-height: 0`, and `padding: 0`.

The tablet rule does not resolve the mismatch.

### Narrow header: at or below 640px

At `css/main.css:1653-1655`, the Currency header panel moves to the inline end. The Language panel remains physically left-aligned. This can create an additional panel-position difference.

### Mobile/off-canvas options

The mobile Language options receive:

- `display: block`;
- `min-height: 40px`;
- `padding: 11px 8px`;
- `line-height: 1.25`;
- natural/start text alignment;
- `white-space: nowrap`.

The off-canvas Currency options receive:

- `display: block`;
- `min-width: 54px`;
- `min-height: 40px`;
- `padding: 10px 8px`;
- inherited `line-height: 1.2`;
- `text-align: center`;
- no explicit `white-space` value.

| Property | Mobile Language | Off-canvas Currency |
| --- | --- | --- |
| `font-family` | Montserrat | Montserrat |
| `font-size` | `0.78rem` | `0.78rem` |
| `font-weight` | `400` normally | `400` normally |
| `line-height` | `1.25` | **`1.2`** |
| vertical padding | `11px` | **`10px`** |
| horizontal padding | `8px` | `8px` |
| `min-height` | `40px` | `40px` |
| `min-width` | automatic | **`54px`** |
| `display` | `block` | `block` |
| `text-align` | start/default | **center** |
| `white-space` | `nowrap` | not explicitly set |
| `border-radius` | same | same |

Their section margins also differ:

- Language: `margin: -12px 0 24px`;
- Currency: `margin: -16px 0 24px`.

These mobile differences are separate from the desktop cascade leak and predate the PR #1335 header override.

## RTL audit

The multilingual plugin enqueues `assets/css/rtl.css` after `pera-main-css` when an RTL language is active (`wp-content/plugins/pera-multilingual/includes/class-content.php:40-45`).

The RTL stylesheet keeps structural layout LTR, then applies RTL direction and right text alignment to header/off-canvas anchors and buttons. Both element types are included, so there is no `a` versus `button` omission in that rule.

Notable effects:

1. Currency options include `dir="ltr"`, but the author-level RTL `direction` declaration can override the presentational direction implied by that attribute.
2. The desktop Currency option rule has the more-specific `text-align: start`; under RTL direction, `start` resolves to the right.
3. Currency uses logical dropdown positioning, while Language uses physical `left: 0`. The two panels can therefore open from different sides in RTL.

RTL does not create the principal box-model mismatch, but it exposes an additional panel-alignment inconsistency.

## Smallest recommended CSS fix

### Root-cause fix

Narrow the two icon-anchor selectors to direct children of `.header-icons`:

```css
#site-header .header-icons > a,
#site-header .header-icons .header-menu-toggle {
  /* existing declarations */
}
```

And:

```css
#site-header.is-scrolled .header-icons > a:hover,
#site-header.is-scrolled .header-icons .header-menu-toggle:hover {
  opacity: 0.85;
}
```

This is the smallest root-cause change because it:

- preserves the intended styling of actual header icon links;
- stops icon-link declarations from leaking into nested Language options;
- allows the existing Language option styles to become effective;
- lets the PR #1335 Currency option declarations match those Language styles without another duplicate override block.

### Exact hover parity

For exact inactive-option hover parity, locally neutralize the global link weight transition:

```css
.header-language-switcher__list a {
  font-weight: 400;
}
```

That selector is more specific than the global `a:hover` rule, while the current-language selector remains more specific and continues to apply weight 700.

### Exact trigger parity

Give the Language trigger the same layout declarations as Currency, preferably through a grouped rule:

```css
display: inline-flex;
align-items: center;
justify-content: center;
```

### Mobile parity

If the requirement includes the off-canvas selectors, normalize the Currency mobile options to the Language values:

```css
.pera-currency-selector--offcanvas .pera-currency-selector__list button {
  min-width: 0;
  padding: 11px 8px;
  line-height: 1.25;
  text-align: start;
  white-space: nowrap;
}
```

The Currency section margin can also change from `-16px` to `-12px`.

## Final findings

1. The primary culprit is `#site-header .header-icons a`, which overrides Language option display, padding, line-height, alignment, and color inheritance.
2. Global `a:hover, a:focus` creates a secondary weight mismatch between inactive Language links and Currency buttons.
3. The scrolled header's broad anchor hover selector changes Language option opacity but not Currency option opacity.
4. The triggers are not identical because Currency is explicitly centered `inline-flex`, while Language relies on the native button display model.
5. Mobile has separate padding, line-height, minimum-width, alignment, white-space, and section-margin differences.
6. No repository-level unscoped button rule causes the mismatch.
7. The fix can remain entirely CSS-only.
