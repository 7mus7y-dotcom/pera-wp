# PeraCRM Dark Mode Audit

## Executive summary
This audit inventories likely dark-mode issues across the already-refactored PeraCRM UI, focusing on the Phase 1–7 shell/primitives/routes plus the final polish pass. The audit is intentionally **inspection only**: no fixes, no redesign, no JS changes.

### Overall assessment
- Dark mode support exists, but it is **incomplete and inconsistent**.
- The main dark token block covers broad base surfaces and form controls, but many later phase-specific components reintroduce **light-only backgrounds, borders, text colors, and hover states** after the dark block.
- The highest-risk pattern is that the `@media (prefers-color-scheme: dark)` overrides appear **before** a large amount of Phase 1/3/5/final-polish CSS. Those later rules frequently hardcode light values like `#ffffff`, `#f8fafc`, `#eef4ff`, `#0f172a`, and pale borders, which likely override the earlier dark-mode intent.
- The most problematic routes are:
  1. **client detail**,
  2. **shell/header/nav**,
  3. **leads/clients + tasks workspaces**,
  4. **overview**,
  5. **pipeline**,
  6. **create lead / embedded deal form**.

### Totals
- **Total issues found:** 33
- **Critical:** 6
- **Major:** 21
- **Minor:** 6

### Issue mix
- **Mostly mixed token/component problems.**
- There are some token-level gaps, but the dominant problem is **component-level light overrides added after the dark token layer**, plus a few **legacy/new primitive mixes**.

## Method and scope
Reviewed:
- `wp-content/plugins/peracrm/assets/frontend/crm.css`
- `wp-content/plugins/peracrm/inc/views/pages/crm-overview.php`
- `wp-content/plugins/peracrm/inc/views/pages/crm-client.php`
- `wp-content/plugins/peracrm/inc/views/pages/crm-pipeline.php`
- `wp-content/plugins/peracrm/inc/views/pages/crm-new.php`
- `wp-content/plugins/peracrm/inc/views/partials/crm-header.php`
- `wp-content/plugins/peracrm/inc/views/partials/crm-side-nav.php`
- referenced implementation notes in `docs/`

No browser pass was available in this audit, so items marked **likely needs browser confirmation** should be visually validated in the next implementation round.

---

## Issues by route / screen

## 1. Shell / header / nav

### 1.1 Page header glass panel likely stays light in dark mode
- **Route/screen:** Shell/header/nav on all CRM routes.
- **Selectors/files:** `.crm-page-header__main`. `crm.css` sets `background: rgba(255, 255, 255, 0.92)` and a light border in the Phase 1 block after the main dark override section. `wp-content/plugins/peracrm/assets/frontend/crm.css:3255-3260`
- **Problem type:** light-only background left behind; translucent/glass effect issue.
- **Why it fails:** The dark token block appears earlier, but this later component rule hardcodes a nearly white translucent panel. In dark mode that likely becomes a pale band floating above dark content.
- **Severity:** critical.
- **Confidence:** confirmed from code.

### 1.2 Header title/meta text likely flips back to dark-on-light assumptions
- **Route/screen:** Shell/header/nav on all CRM routes.
- **Selectors/files:** `.crm-page-header__title`, `.crm-page-header__context`, `.crm-page-header__subtitle`. `crm.css:3270-3294`
- **Problem type:** dark text on dark surface.
- **Why it fails:** These rules force `#0f172a` and `#475569` after the dark block. If the header panel becomes dark through inheritance or mixed rendering, title/context text will be too dark. If the panel remains light, the shell still visually breaks dark mode.
- **Severity:** major.
- **Confidence:** confirmed from code.

### 1.3 Header toolbar surface is hardcoded white
- **Route/screen:** Shell/header/nav on routes using header filters/toolbar, especially leads/clients.
- **Selectors/files:** `.crm-toolbar`. `crm.css:3324-3330`; toolbar markup in `crm-header.php:64-117`.
- **Problem type:** white surface on dark background.
- **Why it fails:** The toolbar container uses `background: #ffffff` after the earlier dark block, so the filter bar likely stays white inside otherwise dark pages.
- **Severity:** critical.
- **Confidence:** confirmed from code.

### 1.4 Segmented toggle shells in header/filter area are light-only
- **Route/screen:** Shell/header/nav and list/workspace controls.
- **Selectors/files:** `.crm-view-toggle`, `.crm-type-toggle`. `crm.css:3350-3355`
- **Problem type:** light-only background left behind; insufficient border contrast.
- **Why it fails:** Both use pale background `#f8fafc` and light border values after the dark block, so segmented controls likely read as light pills inside dark layouts.
- **Severity:** major.
- **Confidence:** confirmed from code.

### 1.5 Search/filter controls likely remain light framed
- **Route/screen:** Header filters on leads/clients.
- **Selectors/files:** `.crm-search-control`. `crm.css:2714`, `3395-3399`; markup in `crm-header.php:67-109`.
- **Problem type:** form input/select/textarea issue.
- **Why it fails:** Global dark input styles exist, but `.crm-search-control` later resets border styling to a light border and starts from a white background rule. Because this class is used on `input` and `select`, visual results may be mixed by specificity/order and need verification.
- **Severity:** major.
- **Confidence:** likely needs browser confirmation.

### 1.6 Shell scrolled logo glass state may have poor contrast in both logo and container
- **Route/screen:** Sticky shell header after scroll.
- **Selectors/files:** `.peracrm-shell-logo-block`, `.peracrm-shell-logo-image`, `.peracrm-shell-logo-link`, dark override only for scrolled block. `crm.css:2335-2375`, `2395-2400`; nav/logo markup in `crm-side-nav.php:48-55`.
- **Problem type:** icon/logo/toggle visibility issue; translucent/glass effect issue.
- **Why it fails:** The logo image is force-filtered to a blue brand treatment, while the scrolled glass panel changes between translucent white and dark translucent surfaces. There is no explicit dark-mode logo treatment, so the logo may lose contrast depending on the underlying hero/page background.
- **Severity:** major.
- **Confidence:** likely needs browser confirmation.

### 1.7 Header action cluster/hamburger icon color logic conflicts
- **Route/screen:** Sticky shell header, especially mobile/drawer entry.
- **Selectors/files:** `.peracrm-header-actions__cluster`, `.crm-side-nav__toggle--header`, `.crm-side-nav__toggle-icon`. `crm.css:2423-2443`, `2479-2488`
- **Problem type:** icon/logo/toggle visibility issue; hover/focus state broken in dark mode.
- **Why it fails:** One block sets header toggle/icon colors to brand blue, while another sets `.crm-side-nav__toggle--header` to white and its hover/focus to white. Combined with dark glass and non-dark glass states, visibility likely changes unpredictably.
- **Severity:** major.
- **Confidence:** confirmed from code.

### 1.8 Desktop side nav stays white in dark mode
- **Route/screen:** Shell/header/nav on desktop routes with rail nav.
- **Selectors/files:** `.crm-side-nav`, `.crm-side-nav__title`, `.crm-side-nav__list a`, `.crm-side-nav__list a.is-active`. `crm.css:2490-2549`; nav markup in `crm-side-nav.php:33-43`
- **Problem type:** white surface on dark background; light chip/button variant breaking in dark mode.
- **Why it fails:** The desktop nav is hardcoded to white, pale borders, pale active state, and dark text after the dark block, so it likely remains a light card on dark pages.
- **Severity:** critical.
- **Confidence:** confirmed from code.

### 1.9 Side nav dividers/borders likely become too weak or mismatched
- **Route/screen:** Shell/header/nav desktop rail.
- **Selectors/files:** `.crm-side-nav__list li`, `.crm-side-nav__list a:hover`, `.crm-side-nav__list a.is-active`. `crm.css:2520-2549`
- **Problem type:** insufficient border contrast; hover/focus state broken in dark mode.
- **Why it fails:** Dividers and hover states are tuned for white surfaces with translucent blue hover fills; on a dark route, the nav either stays inappropriately light or uses weak borders inconsistent with dark tokens.
- **Severity:** minor.
- **Confidence:** confirmed from code.

## 2. Overview

### 2.1 Priority work band uses light gradient in dark mode
- **Route/screen:** Overview.
- **Selectors/files:** `.crm-overview-priority`. `crm.css:1096-1099`; section markup in `crm-overview.php:184-218`
- **Problem type:** light-only background left behind.
- **Why it fails:** The section background is a light blue-to-white gradient added after the dark block, so the top priority module likely reads as a bright card on dark canvas.
- **Severity:** major.
- **Confidence:** confirmed from code.

### 2.2 Priority cards stay white / urgent card stays pink-white
- **Route/screen:** Overview.
- **Selectors/files:** `.crm-overview-priority-card`, `.crm-overview-priority-card--urgent`. `crm.css:1107-1118`; task markup in `crm-overview.php:194-218`
- **Problem type:** white surface on dark background; light-only background left behind.
- **Why it fails:** Both normal and urgent variants are hardcoded to white/light gradient backgrounds and pale borders.
- **Severity:** major.
- **Confidence:** confirmed from code.

### 2.3 Activity and metrics sections are still light-tinted
- **Route/screen:** Overview.
- **Selectors/files:** `.crm-overview-activity`, `.crm-overview-metrics`. `crm.css:1171-1180`; markup in `crm-overview.php:285-355`
- **Problem type:** light-only background left behind.
- **Why it fails:** Later phase-specific backgrounds use `#fbfcfe` and `#fcfdff`, which are not dark-adjusted.
- **Severity:** major.
- **Confidence:** confirmed from code.

### 2.4 KPI cards mix old `card-shell` with new chips and likely stay light
- **Route/screen:** Overview KPI snapshot / pipeline overview tiles.
- **Selectors/files:** `.card-shell`, `.crm-kpi-card`; markup in `crm-overview.php:323-345`; base `card-shell` and broad dark block in `crm.css:42-50`, `2197-2214`.
- **Problem type:** mixed primitive issue; white surface on dark background.
- **Why it fails:** Broad dark styling covers `.card-shell`, but overview metrics still use old `card-shell` composition inside newer sections. Depending on cascade, these may partially darken while surrounding overview sections stay light-tinted, creating mixed containers.
- **Severity:** major.
- **Confidence:** likely needs browser confirmation.

### 2.5 Push panel likely inherits mixed light shells and chips
- **Route/screen:** Overview notices / push panel.
- **Selectors/files:** `.crm-push-panel`, `.crm-chip`, push panel markup in `crm-overview.php:376-402`; final polish radius-only rule at `crm.css:4095-4103`
- **Problem type:** mixed primitive issue.
- **Why it fails:** The push panel was converted from `card-shell` to `crm-section`, but there is no dedicated dark treatment in the later final-polish section. It likely depends on base section dark styles while embedded chips/buttons remain light-leaning in places.
- **Severity:** minor.
- **Confidence:** likely from code.

### 2.6 Workspace notices chip + text contrast may be inconsistent
- **Route/screen:** Overview workspace notices.
- **Selectors/files:** workspace notices markup in `crm-overview.php:356-373`; chips from `crm.css:2836-3127`
- **Problem type:** insufficient text contrast; mixed primitive issue.
- **Why it fails:** Neutral chips are tokenized, but the notice section itself uses generic section styling while the “Notice” chip may remain too low-contrast if the section background is light-tinted or if text soft colors stack together.
- **Severity:** minor.
- **Confidence:** likely needs browser confirmation.

## 3. Leads / clients list

### 3.1 Workspace toolbar remains white
- **Route/screen:** Leads/clients list.
- **Selectors/files:** `.crm-list-workspace-toolbar`. `crm.css:3855-3861`, `4105-4108`; markup in `crm-overview.php:601-623`
- **Problem type:** white surface on dark background.
- **Why it fails:** The toolbar container is hardcoded to `background: #ffffff` after the dark override block.
- **Severity:** critical.
- **Confidence:** confirmed from code.

### 3.2 Secondary view toggle remains light capsule with white selected state
- **Route/screen:** Leads/clients list.
- **Selectors/files:** `.crm-view-toggle--secondary`, `.crm-view-toggle--secondary .btn--solid`, `.crm-view-toggle--secondary .btn--ghost`. `crm.css:3905-3928`, `4119-4131`; markup in `crm-overview.php:620-622`
- **Problem type:** light chip/button variant breaking in dark mode.
- **Why it fails:** The wrapper uses pale background, the selected pill is white, and the selected text is dark brand blue. This is a classic light segmented-control treatment left behind.
- **Severity:** major.
- **Confidence:** confirmed from code.

### 3.3 Type toggle shell is light-only
- **Route/screen:** Leads/clients list.
- **Selectors/files:** `.crm-type-toggle`. `crm.css:3350-3355`; markup in `crm-overview.php:615-618`
- **Problem type:** light chip/button variant breaking in dark mode.
- **Why it fails:** The type toggle wrapper uses light background/border values with no later dark override.
- **Severity:** major.
- **Confidence:** confirmed from code.

### 3.4 Table wrapper likely remains white even when table cells darken
- **Route/screen:** Leads/clients list table view.
- **Selectors/files:** `.crm-page--leads .crm-table-wrap`. `crm.css:3997-4002`; table primitives/dark block at `2933-2972`, `2197-2214`
- **Problem type:** mixed primitive issue; white surface on dark background.
- **Why it fails:** Table primitive surfaces are token-aware, but the list-page wrapper is explicitly white after the dark block, likely causing a mismatched container around darker internals.
- **Severity:** major.
- **Confidence:** confirmed from code.

### 3.5 Row hover treatment is still a light gray wash
- **Route/screen:** Leads/clients list table view.
- **Selectors/files:** `.crm-page--leads .crm-leads-table tbody tr:hover`. `crm.css:4004-4006`
- **Problem type:** hover/focus state broken in dark mode.
- **Why it fails:** Hover state is hardcoded to `#f8fafc`, which is appropriate for light mode only.
- **Severity:** major.
- **Confidence:** confirmed from code.

### 3.6 Grouped row-list containers stay white
- **Route/screen:** Leads/clients list row/list fallback.
- **Selectors/files:** `.crm-list-workspace__group`. `crm.css:3971-3978`; grouped sections rendered via `crm-overview.php:423-476` and leads row fallback further down the file.
- **Problem type:** white surface on dark background.
- **Why it fails:** Group containers use `background: #ffffff` after the dark block.
- **Severity:** major.
- **Confidence:** confirmed from code.

## 4. Tasks

### 4.1 Task workspace toolbar remains white
- **Route/screen:** Tasks list/workspace.
- **Selectors/files:** `.crm-list-workspace-toolbar`. `crm.css:3855-3861`, `4105-4108`; markup in `crm-overview.php:479-498`
- **Problem type:** white surface on dark background.
- **Why it fails:** Same pattern as leads/clients; the toolbar shell is explicitly white.
- **Severity:** critical.
- **Confidence:** confirmed from code.

### 4.2 Task grouped sections stay white, urgent border tuned only for light backgrounds
- **Route/screen:** Tasks row-list fallback.
- **Selectors/files:** `.crm-list-workspace__group`, `.crm-list-workspace__group--urgent`. `crm.css:3971-3978`; markup in `crm-overview.php:423-476`
- **Problem type:** white surface on dark background; insufficient border contrast.
- **Why it fails:** Group backgrounds remain white and the urgent accent uses a very subtle red border intended for white surfaces.
- **Severity:** major.
- **Confidence:** confirmed from code.

### 4.3 Task table hover stays light gray
- **Route/screen:** Tasks table view.
- **Selectors/files:** `.crm-page--tasks .crm-leads-table tbody tr:hover`. `crm.css:4004-4006`
- **Problem type:** hover/focus state broken in dark mode.
- **Why it fails:** Same light-only hover fill as leads/clients.
- **Severity:** major.
- **Confidence:** confirmed from code.

### 4.4 Overdue/open client ghost button variants may invert poorly on dark surfaces
- **Route/screen:** Tasks overview cards + task workspace actions.
- **Selectors/files:** `.btn--ghost.btn--red`, `.btn--ghost.btn--blue` used in `crm-overview.php:114-123`, `456-465`, `547-550`; base button logic `crm.css:84-102`
- **Problem type:** light chip/button variant breaking in dark mode.
- **Why it fails:** Ghost buttons rely on transparent backgrounds plus brand/red borders/text, then hover to solid brand/red. On dark surfaces this can be acceptable or too low-contrast depending on context; there is no dark-specific button tuning.
- **Severity:** minor.
- **Confidence:** likely needs browser confirmation.

## 5. Pipeline

### 5.1 Lane columns use light gradient backgrounds in dark mode
- **Route/screen:** Pipeline.
- **Selectors/files:** `.crm-pipeline-column`. `crm.css:945-953`; markup in `crm-pipeline.php:69-76`
- **Problem type:** light-only background left behind.
- **Why it fails:** Column background is a fixed light gradient added after the dark block.
- **Severity:** critical.
- **Confidence:** confirmed from code.

### 5.2 Pipeline cards stay white
- **Route/screen:** Pipeline.
- **Selectors/files:** `.crm-pipeline-item`. `crm.css:989-997`; markup in `crm-pipeline.php:127-154`
- **Problem type:** white surface on dark background.
- **Why it fails:** Cards are explicitly `background: #ffffff` after the dark block.
- **Severity:** critical.
- **Confidence:** confirmed from code.

### 5.3 Lane header divider and metadata rely on light-mode contrast
- **Route/screen:** Pipeline.
- **Selectors/files:** `.crm-pipeline-column-header`, `.crm-pipeline-column-header__meta`. `crm.css:956-982`
- **Problem type:** insufficient border contrast; insufficient text contrast.
- **Why it fails:** The divider uses very low-opacity dark border and meta text is tuned for light surfaces, not for columns that should be dark in dark mode.
- **Severity:** major.
- **Confidence:** confirmed from code.

### 5.4 Empty lane state uses translucent white panel
- **Route/screen:** Pipeline.
- **Selectors/files:** `.crm-pipeline-empty`. `crm.css:1059-1067`; markup in `crm-pipeline.php:78-80`
- **Problem type:** translucent/glass effect issue; white surface on dark background.
- **Why it fails:** The empty state uses `background: rgba(255, 255, 255, 0.78)` after the dark block, so it likely shows as a bright translucent slab inside a dark lane.
- **Severity:** major.
- **Confidence:** confirmed from code.

### 5.5 Context and next-step text may become dark-on-light or low-contrast depending on cascade
- **Route/screen:** Pipeline.
- **Selectors/files:** `.crm-pipeline-item__context-line`, `.crm-pipeline-item__next-step`, `.crm-meta-line strong`. `crm.css:1027-1056`; markup in `crm-pipeline.php:135-153`
- **Problem type:** insufficient text contrast.
- **Why it fails:** These text colors are tuned around light cards and may not be fully retuned if the card backgrounds are darkened later.
- **Severity:** major.
- **Confidence:** likely from code.

## 6. Client detail

### 6.1 Client summary header card remains white
- **Route/screen:** Client detail summary/header.
- **Selectors/files:** `.crm-client-summary`. `crm.css:3544-3552`; client route markup in `crm-client.php` summary section.
- **Problem type:** white surface on dark background.
- **Why it fails:** The summary wrapper is explicitly `background: #fff` after the dark block.
- **Severity:** critical.
- **Confidence:** confirmed from code.

### 6.2 Summary context list uses pale translucent background and dark body text
- **Route/screen:** Client detail summary/header.
- **Selectors/files:** `.crm-summary-header__context-list`, `.crm-summary-header__context-item dd`. `crm.css:3631-3649`
- **Problem type:** light-only background left behind; dark text on dark surface.
- **Why it fails:** The context list uses `rgba(248, 250, 252, 0.9)` and hardcoded `#111827` text. If the container darkens, the text is too dark; if it stays light, the panel breaks dark mode.
- **Severity:** major.
- **Confidence:** confirmed from code.

### 6.3 KPI cards remain white
- **Route/screen:** Client detail summary/header KPI strip.
- **Selectors/files:** `.crm-client-kpi-card`. `crm.css:3666-3670`
- **Problem type:** white surface on dark background.
- **Why it fails:** KPI cards are explicitly white after the dark block.
- **Severity:** major.
- **Confidence:** confirmed from code.

### 6.4 Reminder and next-step cards use light blue-only treatment
- **Route/screen:** Client detail reminder/next-step areas.
- **Selectors/files:** `.crm-client-reminders`, `.crm-client-next-step__item`, `.crm-client-next-step__item--focus`. `crm.css:3714-3740`
- **Problem type:** light-only background left behind; insufficient border contrast.
- **Why it fails:** These sections use pale blue fills/borders intended for white mode only.
- **Severity:** major.
- **Confidence:** confirmed from code.

### 6.5 Client task groups/subsections stay white or near-white
- **Route/screen:** Client detail notes/reminders/related supporting panels.
- **Selectors/files:** `.crm-client-task-group`, `.crm-client-subsection`, `.crm-client-task-group` variant. `crm.css:3749-3759`
- **Problem type:** white surface on dark background.
- **Why it fails:** Generic client subsections are white and the task-group variant is near-white blue.
- **Severity:** major.
- **Confidence:** confirmed from code.

### 6.6 Timeline panel remains pale with pale left rail
- **Route/screen:** Client detail timeline.
- **Selectors/files:** `.crm-client-timeline`, `.crm-client-timeline .crm-activity-list__item`, `.crm-client-timeline .crm-chip--neutral`. `crm.css:3780-3792`; markup in `crm-client.php:631-662`
- **Problem type:** table/header-body contrast issue; light chip/button variant breaking in dark mode.
- **Why it fails:** Backgrounds, left-border rails, and neutral chips are all pale values designed for light mode.
- **Severity:** major.
- **Confidence:** confirmed from code.

### 6.7 Related-records section and nested subsections remain near-white
- **Route/screen:** Client detail related records.
- **Selectors/files:** `.crm-client-related`, `.crm-client-related .crm-client-subsection`. `crm.css:3799-3806`; markup in `crm-client.php:667-757`
- **Problem type:** white surface on dark background; mixed primitive issue.
- **Why it fails:** Parent and nested subsections both use off-white backgrounds, causing stacked light boxes in dark mode.
- **Severity:** major.
- **Confidence:** confirmed from code.

### 6.8 Notes/timeline “See more / See less” toggle has light-only hover/focus states
- **Route/screen:** Client detail notes/timeline toggles.
- **Selectors/files:** `.archive-hero-desc__toggle`, hover/focus rules. `crm.css:4064-4092`; toggle markup in `crm-client.php:612-614`, `662`
- **Problem type:** hover/focus state broken in dark mode; light chip/button variant breaking in dark mode.
- **Why it fails:** Final-polish toggle uses pale background `var(--surface-soft)` plus explicit hover/focus `#eef4ff` and pale blue border. In dark mode the default may be acceptable if tokens update, but hover/focus remain light-mode-only.
- **Severity:** major.
- **Confidence:** confirmed from code.

### 6.9 Notes fade overlay is light gray gradient
- **Route/screen:** Client detail notes/timeline collapsed content.
- **Selectors/files:** `.archive-hero-desc[data-collapsed="true"] .archive-hero-desc__content::after`. `crm.css:164-170`
- **Problem type:** translucent/glass effect issue.
- **Why it fails:** The fade uses `rgba(240,240,240,...)`, which will create a light wash over dark content when collapsed.
- **Severity:** major.
- **Confidence:** confirmed from code.

### 6.10 Client notice/state-panel dark coverage is incomplete
- **Route/screen:** Client detail notices/state panels.
- **Selectors/files:** `.crm-client-state-panel`, `.crm-client-notice` only get spacing/radius polish at `crm.css:4134-4147`; markup includes notice/state sections in `crm-client.php`.
- **Problem type:** mixed primitive issue.
- **Why it fails:** Final polish converted old shells to `crm-section`, but later route-specific client styles still hardcode many light sub-panels around them. The notices may darken correctly while adjacent sections remain pale, causing inconsistent composition.
- **Severity:** minor.
- **Confidence:** likely from code.

### 6.11 Portfolio inline status colors lack dark-mode tuning
- **Route/screen:** Client detail related records / portfolio actions.
- **Selectors/files:** `.crm-inline-status.is-success`, `.crm-inline-status.is-error`. `crm.css:2791-2793`; markup in `crm-client.php:725`
- **Problem type:** insufficient text contrast.
- **Why it fails:** Success/error text colors are plain green/red tuned to light backgrounds and may be too dim against dark surfaces.
- **Severity:** minor.
- **Confidence:** likely from code.

## 7. Create lead

### 7.1 Form workspace outer card likely darkens, but later shadow/elevation remains light-only
- **Route/screen:** Create lead.
- **Selectors/files:** `.crm-form-card`, `.card-shell`. `crm.css:42-50`, `4155-4157`; markup in `crm-new.php:133-257`
- **Problem type:** shadow/elevation issue that only works in light mode.
- **Why it fails:** The final-polish shadow is extremely light and tuned for white mode. Even if the card darkens via broad dark block, the elevation cue becomes weak or visually wrong.
- **Severity:** minor.
- **Confidence:** likely from code.

### 7.2 Create-lead inputs/selects/textareas likely regress to mixed light styling
- **Route/screen:** Create lead.
- **Selectors/files:** `.crm-form-stack input/select/textarea`, `.crm-search-control`, global dark input block. `crm.css:339-409`, `2230-2241`, `2714`; markup in `crm-new.php:169-240`
- **Problem type:** form input/select/textarea issue.
- **Why it fails:** Global dark form controls exist, but later generic rules still establish white backgrounds and light borders. Client-detail-specific dark form overrides do **not** include `.crm-page--new`, so create-lead relies on the more fragile generic cascade.
- **Severity:** major.
- **Confidence:** likely from code.

### 7.3 Inline error notices all use red/dark treatment, but base notice semantics are overcoupled
- **Route/screen:** Create lead notices.
- **Selectors/files:** `.crm-inline-notice`, `.crm-inline-notice--error`. `crm.css:413-445`, `2244-2252`; markup in `crm-new.php:150-167`
- **Problem type:** mixed primitive issue.
- **Why it fails:** The dark override recolors the base `.crm-inline-notice` to a red-toned scheme, not just the error variant. That may make any non-error notice in the create flow read like an error in dark mode.
- **Severity:** minor.
- **Confidence:** confirmed from code.

## 8. Embedded deal form

### 8.1 Embedded deal form fields get client-only dark treatment, but surrounding containers stay light
- **Route/screen:** Client detail embedded deal form.
- **Selectors/files:** client dark form overrides at `crm.css:1644-1667`; deal form markup in `crm-client.php:740-751`; surrounding client containers at `crm.css:3749-3806`
- **Problem type:** mixed primitive issue.
- **Why it fails:** Inputs/selects in client view do get a dedicated dark override, but the surrounding deal/list/related panels remain white or pale. The form may be dark controls inside light cards.
- **Severity:** major.
- **Confidence:** confirmed from code.

### 8.2 Deal list row actions likely sit inside pale row groups
- **Route/screen:** Client detail embedded deal list.
- **Selectors/files:** deal list markup in `crm-client.php:748-751`; row-list/container primitives in `crm.css:3008-3084`, client section styles at `3749-3806`
- **Problem type:** mixed primitive issue.
- **Why it fails:** The deal rows use newer row-list primitives, but they are nested inside related-record subsections that are still pale/near-white.
- **Severity:** major.
- **Confidence:** likely from code.

## 9. Notices / dialogs / toggles / misc controls

### 9.1 Danger/confirm dialogs likely remain light despite broad dark attempt
- **Route/screen:** Client detail danger zone and portfolio dialog.
- **Selectors/files:** `.crm-danger-dialog`, `.crm-confirm-dialog`, `dialog`. `crm.css:2197-2214`, `2800-2806`; dialog markup in `crm-client.php:735`, `762`
- **Problem type:** mixed primitive issue; white surface on dark background.
- **Why it fails:** Broad dark rule includes dialogs, but `.crm-confirm-dialog` later forces `background: #fff`. There is no equivalent late dark override for confirm dialogs, so modal treatments are inconsistent.
- **Severity:** major.
- **Confidence:** confirmed from code.

### 9.2 Green primary in portfolio dialog may be too bright/legacy against dark modal
- **Route/screen:** Embedded portfolio dialog.
- **Selectors/files:** `btn btn--solid btn--green` in `crm-client.php:735`; base button rules in `crm.css:84-105`
- **Problem type:** light chip/button variant breaking in dark mode.
- **Why it fails:** The green solid variant has no dark-specific tuning. Against a dark dialog it may be acceptable, but it continues a legacy bright CTA treatment unlike the newer calmer CRM blue system.
- **Severity:** minor.
- **Confidence:** likely needs browser confirmation.

### 9.3 Checkbox-chip options may look too light and low-contrast in dark mode
- **Route/screen:** Toggles / preference fields where checkbox pills appear.
- **Selectors/files:** `.crm-checkbox-option`. `crm.css:2717`
- **Problem type:** light chip/button variant breaking in dark mode.
- **Why it fails:** The chip background follows `--surface-soft`, but there is no explicit selected/checked dark treatment visible here, so state distinction may be weak.
- **Severity:** minor.
- **Confidence:** likely from code.

### 9.4 Screen-reader-text focus state is still white popup
- **Route/screen:** Accessibility support across CRM shell.
- **Selectors/files:** `.screen-reader-text:focus`. `crm.css:2293-2303`
- **Problem type:** white surface on dark background.
- **Why it fails:** Focused skip/sr text uses white background and near-black text even on dark pages. Functional, but visually inconsistent.
- **Severity:** minor.
- **Confidence:** confirmed from code.

---

## Most likely root-cause patterns

### 1. Dark block order is too early
The main dark-mode override block (`@media (prefers-color-scheme: dark)`) appears around `crm.css:2182-2260`, but many later phase-specific blocks reintroduce light-mode values afterward:
- Phase 1 shell/header rules.
- Phase 3 client-detail rules.
- Phase 5 list/task workspace rules.
- Final polish rules.

### 2. Component hardcoding overrides tokens
Many components ignore the dark-capable tokens and hardcode:
- `#ffffff`
- `#f8fafc`
- `#eef4ff`
- `#fbfdff`
- `#fcfcfd`
- dark text like `#0f172a` / `#111827`
- pale borders like `#d7e3f4`, `#e5e7eb`, `rgba(15, 23, 42, 0.08)`

### 3. Refactor phases improved structure, but not always dark variants
From the implementation notes, Phase 1–7 focused on shell density, primitives, client detail, overview, lists/tasks, pipeline, and form workspaces. The structural refactor is real, but the dark-mode layer was not carried forward consistently into the route-specific polish work.

### 4. Mixed legacy/new primitives are still present
Examples:
- `card-shell` mixed with `crm-section` and `crm-chip`.
- row-list/table primitives inside older pale container cards.
- final-polish toggle treatment (`archive-hero-desc__toggle`) layered on top of an older collapse/fade system.

### 5. Glass/translucent states were tuned mostly for light mode
Shell glass and collapse overlays use translucent white or light-gray blends that are likely wrong on dark backgrounds.

---

## Highest-priority fix order

### Priority 1 — global shell and workspace chrome
Fix first because these affect every route:
1. `.crm-page-header__main`
2. `.crm-toolbar`
3. `.crm-side-nav`
4. `.crm-view-toggle`, `.crm-type-toggle`, `.crm-view-toggle--secondary`
5. `.crm-search-control`
6. header glass/logo/hamburger states

### Priority 2 — client detail
Biggest concentration of route-specific regressions:
1. `.crm-client-summary`
2. `.crm-summary-header__context-list`
3. `.crm-client-kpi-card`
4. `.crm-client-next-step__item*`
5. `.crm-client-task-group`, `.crm-client-subsection`
6. `.crm-client-timeline`
7. `.crm-client-related*`
8. `archive-hero-desc` fade/toggle states

### Priority 3 — list/task workspaces
1. `.crm-list-workspace-toolbar`
2. `.crm-list-workspace__group`
3. `.crm-page--leads/.crm-page--tasks .crm-table-wrap`
4. list/table hover states
5. type/view segmented controls

### Priority 4 — pipeline
1. `.crm-pipeline-column`
2. `.crm-pipeline-item`
3. `.crm-pipeline-empty`
4. lane header/meta/border states

### Priority 5 — overview special sections
1. priority band/cards
2. activity/metrics backgrounds
3. push/notices consistency

### Priority 6 — dialogs/forms polish
1. confirm/danger dialogs
2. create-lead control consistency
3. portfolio/danger button variants

---

## What can be fixed tokenically in CSS vs what needs per-component treatment

## Token-first fixes likely to help broadly
These should be the first implementation pass:
- dark surface tokens for section/card/table wrappers.
- dark border tokens for shell dividers and table headers.
- dark text-soft tokens for metadata and subtext.
- dark chip tokens for neutral/status/urgent/selected variants.
- dark hover/focus tokens for segmented controls and row hovers.
- dark translucent/glass tokens for shell header and overlays.

## Component-level overrides still needed
A token pass alone is **not sufficient** for these:
- `.crm-page-header__main`
- `.crm-toolbar`
- `.crm-side-nav` desktop rail
- `.crm-view-toggle--secondary` selected state
- `.crm-overview-priority*`
- `.crm-pipeline-column`, `.crm-pipeline-item`, `.crm-pipeline-empty`
- `.crm-client-summary`
- `.crm-summary-header__context-list`
- `.crm-client-next-step__item*`
- `.crm-client-task-group`, `.crm-client-subsection`, `.crm-client-timeline`, `.crm-client-related*`
- `.archive-hero-desc__toggle` and collapsed-content fade overlay
- `.crm-confirm-dialog`

## Mixed legacy/new problem areas needing especially careful treatment
- overview KPI cards using `card-shell` inside newer sections.
- client detail related-record subsections mixing row-list and older pale wrappers.
- final-polish toggle styling layered over older archive/hero collapse behavior.
- any route where a base dark token exists, but a later phase-specific rule hardcodes a light value.

---

## What requires browser QA to confirm
The following are especially important to validate visually after CSS fixes:
- shell logo visibility in scrolled and unscrolled states.
- hamburger/toggle icon contrast in the sticky header on mobile.
- page-header translucency over real page backgrounds.
- search/select rendering differences across browsers for `.crm-search-control`.
- selected/unselected state clarity for `.crm-view-toggle--secondary` and `.crm-type-toggle`.
- hover/focus contrast on ghost buttons in dark mode.
- overview KPI card mixing (`card-shell` inside section context).
- client notes/timeline collapsed fade overlay and toggle readability.
- dialog surfaces/backdrops and focus handling.
- pipeline lane/card contrast when horizontal scrolling is active.

---

## Dark-mode fix strategy recommendation

### Is a token-first patch sufficient?
**No, not by itself.**
A token-first pass is necessary, but the audit suggests the current regressions are driven mainly by **later component-level light overrides** that bypass tokens.

### Are component-level overrides needed?
**Yes. Definitely.**
The safest implementation is:
1. strengthen/centralize dark tokens,
2. then add a dedicated **late dark-mode override section** after all phase-specific light rules,
3. then patch the highest-risk route components individually.

### Did any phase introduce systematic regressions?
**Yes, likely systematically.**
The implementation notes point to a recurring pattern:
- Phase 1 shell/header introduced new shell chrome with translucent/light surfaces.
- Phase 3 client detail introduced many route-specific pale blue/white surfaces.
- Phase 5 list/task workspaces introduced white toolbars/groups and pale segmented controls.
- Phase 6 pipeline introduced light lane/card gradients.
- Final polish introduced light-mode-only toggle and selected-control refinements.

So the regressions are not from one isolated bug; they are a **systematic byproduct of refactor phases adding later component styles without dark counterparts**.

### Safest order of implementation
1. Add a **single final dark-mode section at the end of `crm.css`** so cascade order works in dark mode.
2. Patch global shell primitives first: header, toolbar, nav, buttons/toggles, search control.
3. Patch route shells: overview/list/task/pipeline/client/create surfaces.
4. Patch client-detail special cases: summary, timeline, related sections, notes toggles.
5. Patch dialogs/notices/embedded forms.
6. Perform browser QA route-by-route in this order:
   - shell/header/nav,
   - leads/clients,
   - tasks,
   - pipeline,
   - overview,
   - client detail,
   - create lead,
   - embedded deal form/dialogs.

This minimizes regressions while keeping the next pass tightly scoped to dark mode only.

---

## Final summary for next implementation pass
- **Total number of dark-mode issues found:** 33
- **Critical / major / minor:** 6 / 21 / 6
- **Most problematic routes:** client detail; shell/header/nav; leads/clients; tasks; pipeline.
- **Issue character:** mostly **mixed token + component problems**, with a strong pattern of **legacy/new primitive mixing** and **light-only component overrides added after the main dark-mode block**.
