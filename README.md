# Visual Designer Manager 2

Visual Designer Manager is a model-driven visual WordPress designer.

## Current version

`2.0.0-alpha.3`

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
  Frontend/
  Http/
  Model/
  Storage/
  Support/
assets/
templates/
tests/
docs/
.github/workflows/
```

## Current Designer foundation

Alpha.3 includes:

- V2-only page layout storage and version counter.
- Section, Container, Text, Image, Button, Spacer and Divider node contracts.
- Parent and cycle validation.
- One PHP renderer for Designer preview and frontend output.
- Desktop/Laptop/Tablet/Mobile geometry.
- Palette, canvas, selection, Inspector and save/reload.
- Pointer drag and resize on the canonical grid.
- 12-column horizontal snap and 8 px vertical snap.
- Undo/Redo with bounded history and keyboard shortcuts.
- Automatic Section/Container height reconciliation.

The V2 layout document uses schema version `2` and an 8 px row grid.

## Development sequence

1. `2.0.0-alpha.1` — bootstrap, identity gate and canonical model foundation. **Done.**
2. `2.0.0-alpha.2` — Designer workspace, node model and renderer contract. **Done.**
3. `2.0.0-alpha.3` — drag/resize, grid snap, auto-height and Undo/Redo. **Current.**
4. `2.0.0-alpha.4` — text editing, Media Library, button controls and shared theme-aware color picker.
5. `2.0.0-alpha.5` — Header/Footer and global site design.
6. `2.0.0-alpha.6` — Events.
7. `2.0.0-alpha.7` — Vehicles.
8. `2.0.0-alpha.8` — Gallery.
9. `2.0.0-beta.1` — forms.
10. `2.0.0-beta.2` — navigation and site settings.
11. `2.0.0-beta.3` — portable export/import and package validation.
12. `2.0.0-rc.1` — full site import and Editor/Preview/Live acceptance QA.
13. `2.0.0` — production release.

## QA rule

Every push is syntax checked and scanned for previous-generation identifiers before it can be treated as a V2 candidate.
