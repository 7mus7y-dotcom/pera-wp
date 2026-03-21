# PeraCRM impersonation UI polish

## Summary
This pass keeps the existing impersonation behavior intact and focuses on front-end CRM header polish.

## What caused the mobile overflow
The impersonation controls were inside nested flex containers where the select kept a `min-width: 220px` and the action area did not consistently allow children to shrink.

On narrow screens, the select, action button, and reset button could therefore compete for space inside the same flex row and push beyond the banner width.

## What changed to fix it
- Allowed the impersonation meta and action groups to shrink with `min-width: 0` and responsive flex sizing.
- Changed the select from a fixed minimum-width behavior to a flexible control that can shrink on desktop and expand to full width on mobile.
- Kept the banner action layout compact on desktop, but forced the select and buttons to stack and fill the row below `767px`.
- Preserved spacing and tap targets so the controls remain readable and easy to use on touch devices.

## UX improvements included
- Added a restrained active-state badge so impersonation is easier to identify at a glance.
- Split the viewing identity into clearer “Viewing as” / “Signed in as” rows.
- Updated action copy from “Switch view” to “Apply view” and the default option from “My view” to “View as myself” for clarity.
- Improved long-name handling with wrapping-friendly banner value styling.

## Advisor-filter behavior while impersonating
The advisor filter was hidden only while impersonation is active because it can conflict conceptually with the shell-level impersonated view.

In its place, the header now shows a short locked-state note explaining that the current results already follow the active impersonated advisor context.

## Manual QA checklist

### Mobile
- [ ] impersonation banner stays inside container
- [ ] no horizontal overflow
- [ ] dropdown is usable
- [ ] switch button is usable
- [ ] reset button is usable
- [ ] text remains readable
- [ ] no clipping or overlap

### Desktop
- [ ] layout remains clean and aligned
- [ ] no unnecessary wrapping at normal desktop widths
- [ ] active impersonation state remains obvious

### Interaction
- [ ] switching still works
- [ ] reset still works
- [ ] no conflicting advisor filter confusion on pages where filters are present


## Final pre-deploy cleanup
- Replaced the invalid mobile `justify-content: stretch;` declaration with `justify-content: flex-start;` so the stacked action area uses a valid flexbox value.
- Simplified the advisor lock badge sizing by removing `max-width: max-content;`, letting the existing `inline-flex` sizing keep the badge content-fit naturally.
- Reviewed the banner at medium widths and added a light tablet breakpoint so the meta/actions/select controls wrap earlier and stay readable before the mobile stack takes over.

## Follow-up diagnosis: real mobile overflow cause
The remaining mobile overflow was not just a flex-wrapping problem.

The impersonation dropdown inherits the shared `.crm-search-control` sizing, which gives it `width: 100%`, `padding: 0.7rem 0.9rem`, and later a `min-height: 42px`.

Inside the impersonation banner, that inherited control sizing caused two issues at once:
- the select looked oversized because it also inherited the CRM-wide `line-height: 1.6`, which made the internal vertical space feel much larger than the surrounding banner UI;
- the select had no component-level `box-sizing: border-box`, so its full-width/padded sizing was more likely to push wider than the available action column on narrow mobile widths.

## Final scoped fix
The final fix stays specific to the impersonation UI instead of shrinking CRM selects globally.

- Added a scoped override on `.peracrm-impersonation-switcher__select` to set `box-sizing: border-box`, compact/select-appropriate padding, a tighter line-height, and a slightly smaller minimum height.
- Kept the select visually consistent by preserving the rounded CRM control styling and adding a component-scoped dropdown arrow treatment instead of changing all selects.
- Let the impersonation select take the full action-row width earlier at tablet/mobile breakpoints so it does not compete with the buttons for horizontal space.
- Preserved the stacked mobile order: state text, dropdown, apply button, reset button.

## Manual QA checklist
- [ ] impersonation select fits inside container on mobile
- [ ] no horizontal overflow
- [ ] select no longer has excessive internal padding
- [ ] apply button fits and is tappable
- [ ] reset button fits and is tappable
- [ ] medium-width layout still works
- [ ] desktop layout still looks clean
