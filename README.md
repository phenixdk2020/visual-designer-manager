# Visual Designer Manager 2

Visual Designer Manager is a model-driven visual WordPress designer.

## Current version

`2.0.0-beta.3`

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
- Site transfer uses an explicit validated portable package format.
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
  Navigation/
  Storage/
  Support/
  Transfer/
  Vehicles/
assets/
templates/
tests/
docs/
.github/workflows/
```

## Current Designer foundation

Beta.3 includes:

- V2-only page layout storage and version history.
- Section, Container, Text, Image, Button, Spacer and Divider node contracts.
- Events, Vehicles and Billedgalleri dynamic Designer nodes.
- Contact Form and Membership Form page nodes using one canonical form renderer.
- WordPress Navigation nodes for pages and Header/Footer with responsive mobile behavior.
- Siteindstillinger for title, tagline, organization, contact details, VDM logo and site icon.
- Parent and cycle validation.
- One PHP renderer for Designer preview and frontend output.
- Desktop/Laptop/Tablet/Mobile geometry.
- Palette, canvas, selection, Inspector and save/reload.
- Pointer drag and resize on the canonical grid.
- 12-column horizontal snap and 8 px vertical snap.
- Undo/Redo with bounded history and keyboard shortcuts.
- Automatic Section/Container height reconciliation.
- Rich text editing and WordPress Media Library image selection.
- Extended Button controls and a shared theme-aware color popup.
- Independent Header and Footer V2 documents with the same Designer toolchain.
- Global Site Design tokens and an optional complete VDM site shell.
- V2-native Events, Vehicles and Gallery detail rendering.
- Server-validated Contact and Membership form submissions via WordPress mail.
- Native V2 portable export/import with preflight, SHA-256 package validation and ID remapping.

The V2 layout document uses schema version `2` and an 8 px row grid.

## Navigation and Siteindstillinger

VDM Navigation uses WordPress menus as canonical navigation data. The Designer controls placement, orientation, alignment, spacing, typography, colors and mobile toggle behavior. Siteindstillinger use WordPress canonical title/tagline/site-icon values plus VDM-owned organization, contact and logo options.

## Forms

The page Designer contains two form presets: `Kontaktformular` and `Bliv medlem`. Both use the same V2 form renderer and Inspector controls. Submissions are checked against the stored V2 page/node contract before mail is sent. The recipient is the configured VDM contact address, otherwise the WordPress administration address. Forms are intentionally not available in the Header/Footer palette.

## Portable transfer

`Eksport / import` creates native V2 portable packages with schema `2.0`. The package contains pages/layouts, Header/Footer, Site Design, Siteindstillinger, Events, Vehicles, Gallery albums, WordPress menus and referenced original media. Every listed file has a size and SHA-256 record and the complete manifest has a deterministic content digest.

Import is a two-step operation: upload/preflight first, then explicit confirmation. Preflight rejects unsafe ZIP paths, duplicates, symlinks, unlisted files, size mismatches and SHA-256 mismatches. The staged ZIP is tied to the current administrator and its SHA-256 plus complete package are validated again immediately before import. Media, post, page, menu and menu-item source IDs are remapped to target IDs. Repeated native V2 imports can reuse records marked as originating from the same source.

Beta.3 intentionally accepts native schema `2.0` only. Controlled migration of older portable schema `1.0` packages is an RC task.

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
10. `2.0.0-beta.2` — navigation and site settings. **Done.**
11. `2.0.0-beta.3` — portable export/import and package validation. **Done.**
12. `2.0.0-rc.1` — controlled schema 1.0 migration, full site import and Editor/Preview/Live acceptance QA. **Next.**
13. `2.0.0` — production release.

## QA rule

Every push is syntax checked and scanned for previous-generation identifiers before it can be treated as a V2 candidate. Each completed milestone adds a regression contract that remains active for later versions.
