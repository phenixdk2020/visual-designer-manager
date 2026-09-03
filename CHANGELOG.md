# Changelog

## 2.0.0-alpha.6

- Added the V2-native `vdm_event` content type with title, editor and featured image support.
- Added canonical Event fields for date, start/end time, location, address, contact and summary.
- Added an Events Designer node with count, past-event visibility, columns, spacing, card padding, radius and display toggles.
- Added theme-aware card, text, heading and accent color controls using the shared VDM color popup.
- Added one canonical Event renderer used by Designer preview and frontend page layouts.
- Added Event cards with featured image, fact ribbon, summary and detail links.
- Added detail facts for Dato, Tid, Sted, Adresse and Kontakt.
- Added an Event detail renderer and optional VDM site-shell route using the same Header/Footer/Site Design stack as V2 pages.
- Added responsive Event card CSS for desktop, tablet and mobile.
- Added rewrite-rule refresh on plugin activation/deactivation for the V2 Event content type.
- Expanded QA with the alpha.6 Events contract.

## 2.0.0-alpha.5

- Added independent V2 Header and Footer layout documents with their own version/history storage.
- Added a Header / Footer Designer using the same canonical node model, renderer, breakpoints, drag/resize, media controls and color picker as page layouts.
- Added V2 global Site Design storage for content width, side padding, background, text, heading, links, base font size and font family.
- Added an explicit `Brug VDM som sideskal` switch; no destructive theme changes are performed automatically.
- Added a canonical VDM page shell with `wp_head()`, `wp_body_open()` and `wp_footer()` integration.
- Added site-shell frontend routing for V2 pages while retaining the normal theme path when the shell is disabled.
- Added global site CSS variables shared by Header, page content and Footer.
- Expanded the V2 REST layout endpoint to support `global-header` and `global-footer` documents without compatibility aliases.
- Expanded QA with the alpha.5 site-shell contract.

## 2.0.0-alpha.4

- Added a compact VDM color popup that opens only when a color control is clicked.
- Added picker and theme modes with `Annuller`, `Tema` / `Farvevælger` and `Anvend` actions.
- Added WordPress theme-palette discovery plus recently used colors.
- Removed native browser color inputs from Designer controls.
- Added rich text editing with paragraph, headings, bold, italic and links.
- Added WordPress Media Library selection for image nodes.
- Expanded Button controls with target, alignment, padding, font size and font weight.
- Kept Button rendering canonical between Designer preview and frontend output.
- Expanded QA with the alpha.4 editor-controls contract.

## 2.0.0-alpha.3

- Added pointer-based drag interaction directly on the canonical Designer canvas.
- Added east, south and southeast resize handles for selected elements.
- Drag and resize snap to the 12-column / 8 px canonical grid.
- Added bounded in-memory Undo/Redo with toolbar buttons and keyboard shortcuts.
- Added keyboard nudge with arrow keys plus Ctrl/Cmd+S, Ctrl/Cmd+Z and redo shortcuts.
- Added automatic Section/Container height reconciliation with persisted minimum-height rows.
- Manual height changes define the minimum height while automatic height still prevents child overflow.
- Added interaction state styling and one-history-entry pointer transactions.
- Expanded QA with the alpha.3 interaction contract.

## 2.0.0-alpha.2

- Added V2-only page layout storage and version history.
- Added canonical node schema for Section, Container, Text, Image, Button, Spacer and Divider.
- Added hierarchy validation with root and cycle rules.
- Added one shared PHP renderer used by frontend and Designer preview.
- Added responsive geometry for Desktop, Laptop, Tablet and Mobile.
- Added the first V2 Designer workspace with palette, canvas, breakpoint selection and Inspector.
- Added save/reload through the V2 REST API.
- Added frontend rendering for pages that contain a V2 layout.
- Expanded CI with JavaScript syntax and the alpha.2 Designer contract.

## 2.0.0-alpha.1

- Started the independent Visual Designer Manager 2 repository.
- Added VDM-only plugin bootstrap and namespace autoloading.
- Added the VDM admin dashboard.
- Added `visual-designer-manager/v2/health` REST endpoint.
- Added schema version 2 layout document foundation.
- Added canonical Desktop/Laptop/Tablet/Mobile breakpoint definitions.
- Added CI identity and syntax gates.
