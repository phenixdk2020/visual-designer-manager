# Visual Designer Manager 2

Visual Designer Manager is a model-driven visual WordPress designer.

## Current version

`2.0.0-beta.1`

## Version 2 principles

- Independent repository and release history.
- Product identity is Visual Designer Manager / VDM everywhere.
- Plugin folder: `visual-designer-manager`.
- Main plugin file: `visual-designer-manager.php`.
- PHP namespace: `VisualDesignerManager\`.
- Runtime constants: `VDM_*`.
- Admin slugs and actions use `vdm-*` / `vdm_*`.
- REST namespace: `visual-designer-manager/v2`.
- No compatibility aliases from earlier product generations.
- Site transfer is handled through an explicit portable import format, not through old runtime identifiers.
- Destructive data changes are never performed on plugin deactivation.

## Architecture

```text
visual-designer-manager.php
src/
  Admin/
  Core/
  Events/
  Forms/
  Frontend/
  Gallery/
  Http/
  Model/
  Storage/
  Support/
  Vehicles/
assets/
templates/
tests/
docs/
.github/workflows/
```

## Current Designer foundation

Beta.1 includes:

- V2-only page layout storage and version history.
- Section, Container, Text, Image, Button, Spacer and Divider node contracts.
- Events, Vehicles and Billedgalleri dynamic Designer nodes.
- Contact Form and Membership Form page nodes using one canonical form renderer.
- Parent and cycle validation.
- One PHP renderer for Designer preview and frontend output.
- Desktop/Laptop/Tablet/Mobile geometry.
- Palette, canvas, selection, Inspector and save/reload.
- Pointer drag and resize on the canonical grid.
- 12-column horizontal snap and 8 px vertical snap.
- Undo/Redo with bounded history and keyboard shortcuts.
- Automatic Section/Container height reconciliation.
- Rich text editing.
- WordPress Media Library image selection.
- Extended Button controls.
- Compact theme-aware VDM color popup with recent colors.
- Independent Header and Footer V2 documents with the same Designer toolchain.
- Global Site Design tokens.
- Optional complete VDM site shell with canonical Header → Page → Footer rendering.
- V2-native Events with fact ribbon and detail pages.
- V2-native Vehicles with fixed and flexible technical data plus image-left/data-right details.
- V2-native Gallery albums with ordered WordPress media and responsive album details.
- Server-validated Contact and Membership form submissions via WordPress mail without an additional VDM submission database.
- Form node validation against the stored page layout before mail is sent.
- Shared form CSS for canonical Designer/live field, textarea, consent and submit-button geometry.

The V2 layout document uses schema version `2` and an 8 px row grid.

## Site shell

`Site Design` contains an explicit `Brug VDM som sideskal` switch. When disabled, WordPress continues to use the active theme template and VDM only replaces page content that has a V2 layout. When enabled, VDM renders its own shell using `wp_head()`, `wp_body_open()` and `wp_footer()` plus the V2 Header and Footer documents. Event, Vehicle and Gallery details can use the same shell.

## Forms

The page Designer contains two form presets: `Kontaktformular` and `Bliv medlem`. Both use the same V2 form renderer and Inspector controls for columns, spacing, padding, radius, colors, fields, message height, consent text, submit label and success message. Form submissions are checked against the stored V2 page/node contract before sending. The recipient is `vdm_contact_email` when configured, otherwise the WordPress administration e-mail address.

Forms are intentionally not available in the Header/Footer palette.

## Development sequence

1. `2.0.0-alpha.1` — bootstrap, identity gate and canonical model foundation. **Done.**
2. `2.0.0-alpha.2` — Designer workspace, node model and renderer contract. **Done.**
3. `2.0.0-alpha.3` — drag/resize, grid snap, auto-height and Undo/Redo. **Done.**
4. `2.0.0-alpha.4` — text editing, Media Library, button controls and shared theme-aware color picker. **Done.**
5. `2.0.0-alpha.5` — Header/Footer and global site design. **Done.**
6. `2.0.0-alpha.6` — Events. **Done.**
7. `2.0.0-alpha.7` — Vehicles. **Done.**
8. `2.0.0-alpha.8` — Gallery. **Done.**
9. `2.0.0-beta.1` — forms. **Done.**
10. `2.0.0-beta.2` — navigation and site settings. **Next.**
11. `2.0.0-beta.3` — portable export/import and package validation.
12. `2.0.0-rc.1` — full site import and Editor/Preview/Live acceptance QA.
13. `2.0.0` — production release.

## QA rule

Every push is syntax checked and scanned for previous-generation identifiers before it can be treated as a V2 candidate. Each completed milestone adds a regression contract that remains active for later versions.
