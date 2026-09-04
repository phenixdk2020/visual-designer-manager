# Visual Designer Manager 2

Visual Designer Manager is a model-driven visual WordPress designer.

## Current version

`2.0.0-rc.7`

## Version 2 principles

- Independent repository and release history.
- Product identity is Visual Designer Manager / VDM everywhere.
- Plugin folder: `visual-designer-manager`.
- Main plugin file: `visual-designer-manager.php`.
- PHP namespace: `VisualDesignerManager\`.
- Runtime constants: `VDM_*`.
- Admin slugs and actions use `vdm-*` / `vdm_*`.
- REST namespace: `visual-designer-manager/v2`.
- No runtime compatibility aliases from earlier product generations.
- Site transfer uses explicit validated portable package formats and an isolated migration boundary.
- Destructive data changes are never performed on plugin deactivation.

## Architecture

```text
visual-designer-manager.php
src/
  Admin/
  Core/
  Diagnostics/
  Events/
  Fields/
  Forms/
  Frontend/
  Gallery/
  Http/
  Model/
  Navigation/
  Storage/
  Support/
  Transfer/
  Update/
  Vehicles/
assets/
templates/
tests/
docs/
.github/workflows/
```

## Designer foundation

VDM2 includes:

- V2-only page layout storage and version history.
- Section, Container, Text, Image, Button, Spacer and Divider node contracts.
- V1-parity Link, Icon, Badge, Data List and Table nodes.
- Composable Event Value, Event Image, Event Field and Event Facts nodes plus Vehicle Detail and Gallery Detail nodes.
- Events, Vehicles and Billedgalleri dynamic Designer nodes.
- Contact Form and Membership Form page nodes using one canonical form renderer.
- WordPress Navigation nodes for pages and Header/Footer with responsive mobile behavior.
- Siteindstillinger for title, tagline, organization, contact details, VDM logo and site icon.
- Parent and cycle validation with a controlled root-level exception for floating buttons.
- One PHP renderer for Designer preview and frontend output.
- Desktop/Laptop/Tablet/Mobile geometry.
- Palette, canvas, selection, Inspector and save/reload.
- Pointer drag and resize on the canonical grid.
- V1-style Over/Under/Venstre/Højre/Ind i placement helpers.
- 12-column editor snap and 8 px vertical snap.
- VDM2-native 120-step fine horizontal geometry for precise migrated placement.
- Undo/Redo with bounded history and keyboard shortcuts.
- Automatic Section/Container height reconciliation.
- Rich text editing and WordPress Media Library image selection.
- Extended Button controls, floating-button mode and a shared theme-aware color popup.
- Named Header and Footer templates with stable IDs, defaults, independent histories and per-page assignment.
- Global Site Design tokens and an optional complete VDM site shell.
- V2-native Events, Vehicles and Gallery detail rendering.
- Native reusable vehicle and event field registries.
- Server-validated Contact and Membership form submissions via WordPress mail.
- Native V2 portable export/import with preflight, SHA-256 package validation and ID remapping.
- Controlled schema `1.0` to native schema `2.0` package migration during preflight.

The V2 layout document uses schema version `2` and an 8 px row grid.

## Administration parity

RC.2 restored the established user-facing administration flow and terminology, including Dashboard, Køretøjer, Køretøjsfelter, Events, Eventfelter, Billedgalleri, Sider, Backup, Opdateringer, Log, Brugermanual, Visual Designer, Eksport, Menu, Header / Footer, Tema and Siteindstillinger.

RC.7 extends that parity with named Header/Footer management, per-page assignment, full in-manager WordPress menu editing with 30 snapshots, page duplicate/status/trash actions, public/admin manual parity, Word `.docx` export and page-filtered diagnostics.

The implementation behind those screens is native VDM2. Reusable field definitions use VDM-owned storage and are included in native portable transfer packages.

## GitHub updates

RC.3 adds a native VDM2 update channel. The `Opdateringer` page checks the public `update.json` manifest from the Visual Designer Manager repository and integrates the same release into WordPress' normal plugin-update system.

The updater:

- accepts only the VDM repository manifest and versioned VDM package path;
- compares the published version with `VDM_VERSION`;
- verifies the downloaded ZIP against the manifest SHA-256 before WordPress can install it;
- creates a program backup of the currently installed VDM plugin before replacement;
- supports manual `Tjek GitHub-opdatering` and `Opdater` actions;
- also supports WordPress' normal Plugins update flow once the manifest reports a newer version.

GitHub Actions is the canonical publisher. After a version reaches `main`, the package workflow validates the runtime, builds the installable ZIP, publishes it under `dist/`, calculates SHA-256 and writes `update.json`. The publish commit is guarded against recursive package builds.

RC.3 is the bootstrap version for this channel. Installations older than RC.3 do not contain the native VDM2 updater and therefore require one final manual RC.3 ZIP installation. Subsequent published versions can be installed from WordPress.

## Navigation and Siteindstillinger

VDM Navigation uses WordPress menus as canonical navigation data. RC.7 restores the established VDM menu-management workflow for creating menus, adding pages/custom links/headings, editing hierarchy and order, assigning theme locations and restoring up to 30 snapshots. The Designer controls placement, orientation, alignment, spacing, typography, colors and mobile toggle behavior. Siteindstillinger use WordPress canonical title/tagline/site-icon values plus VDM-owned organization, contact and logo options.

## Forms

The page Designer contains two form presets: `Kontaktformular` and `Bliv medlem`. Both use the same V2 form renderer and Inspector controls. Submissions are checked against the stored V2 page/node contract before mail is sent. RC.7 supports a recipient per form node, with fallback to the configured VDM contact address and then the WordPress administration address, plus optional receipt mail to the sender. Forms are intentionally not available in the Header/Footer palette.

## Portable transfer

`Eksport` creates native V2 portable packages with schema `2.0`. The package contains pages/layouts, Header/Footer, Tema/Site Design, Siteindstillinger, reusable fields, Events, Vehicles, Gallery albums, WordPress menus and referenced original media. Every listed file has a size and SHA-256 record and the complete manifest has a deterministic content digest.

Import is a two-step operation: upload/preflight first, then explicit confirmation. Preflight rejects unsafe ZIP paths, duplicates, symlinks, unlisted files, size mismatches and SHA-256 mismatches. The staged ZIP is tied to the current administrator and its SHA-256 plus complete package are validated again immediately before import. Media, post, page, menu and menu-item source IDs are remapped to target IDs.

The migration boundary can accept validated schema `1.0` site packages through an isolated conversion step. The source package is validated, converted into a temporary native schema `2.0` package, and that native package is validated again before the canonical V2 importer changes WordPress content.

RC.2 preserves reusable field definitions and values, maps previous module-list styling into native V2 list nodes, and preserves previous 120-step horizontal placement through VDM2-native fine geometry while retaining the 12-column editor model.

RC.4 hardens schema 1.0 preflight for image nodes that contain an uploads URL but no media ID. VDM first retries the same uploads path on the package source site and may then use the original URL through WordPress' safe HTTP client. A recovered file is size/type checked, SHA-256 hashed and embedded into the temporary native V2 package before the canonical importer runs. Preflight reports both recovered and unresolved legacy images and still does not create WordPress media objects.

## RC.7 V1 parity completion batch

RC.7 restores a large group of behaviors from the established V1 reference while keeping the independent V2 identity and storage model. It adds named Header/Footer templates with stable IDs, defaults and histories; page-specific Header/Footer selection including `Ingen`; the VDM menu editor and snapshots; Link/Icon/Badge/Data List/Table nodes; floating buttons and layer control; V1-style placement helpers; composable Event/Vehicle/Gallery detail nodes; page lifecycle actions; manual/Word export; form recipient/receipt parity; and page-filtered support diagnostics.

RC.7 adds a permanent regression contract on top of every earlier Alpha/Beta/RC gate. Production `2.0.0` remains blocked until the remaining module/export/theme parity work and the real test3→test4 visual acceptance are complete.

## RC.6 update checkpoint parity

RC.6 restores the established update-checkpoint workflow on the native VDM2 updater. After the existing SHA-256 verified program backup succeeds, VDM creates a second checkpoint using the canonical portable site exporter. That VDM-data ZIP includes pages/layouts, Header/Footer, Site Design, Site settings, reusable fields, Events, Vehicles, Gallery, menus and referenced media.

Both checkpoint files are validated before installation may continue. A failed data checkpoint returns a WordPress updater error and stops the update. RC.6 keeps up to 12 complete checkpoint pairs and exposes their from/to versions, local timestamp, filenames, sizes and authenticated download actions on the `Opdateringer` page. Older V2 program-only backups are surfaced as legacy checkpoint rows when they still exist.

The RC.5 to RC.6 update itself is the bootstrap transition for this feature: RC.5 can only create its existing program backup before RC.6 code is installed. From RC.6 onward, subsequent updates create the complete program + VDM-data pair automatically.

## RC.5 V1 page workflow parity

RC.5 restores the established daily page workflow from the shipped V1 product on top of the native VDM2 model. The `Sider` administration page can create a new WordPress page with title, optional slug, parent and draft/published status and open it directly in Visual Designer. The Designer exposes `Forhåndsvis`, `Gem som ny version` and `Gem & vis`, plus session clipboard controls for Copy/Paste/Duplicate.

Unsaved preview is user-bound and temporary. VDM normalizes the unsaved document, stores it under a short-lived opaque token tied to the current administrator and renders that document through the actual page frontend/site-shell path. The saved page is not changed by preview.

Saved page history is available directly under the Designer. Historical versions can be previewed, restored non-destructively as a new version, or copied to a new draft page with its own version history.

## Acceptance status

RC.7 carries all earlier migration, updater, checkpoint and page-workflow work forward. Production `2.0.0` remains blocked until environment acceptance confirms V1 parity on the designated WordPress test target.

Acceptance includes administration parity, Designer → Preview → Live parity, Header/Footer, Menu, Tema, forms, Events, Vehicles, Gallery, responsive breakpoints, images, Siteindstillinger, site-shell routing and a successful updater manifest/check path.

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
12. `2.0.0-rc.1` — controlled schema 1.0 migration and automated migration/parity QA. **Technical candidate completed; environment parity failed.**
13. `2.0.0-rc.2` — V1 functional/admin/visual parity, fine geometry and migration hardening. **Superseded by RC.3.**
14. `2.0.0-rc.3` — RC.2 parity plus native GitHub updater, verified package publishing and update-page integration. **Done; superseded by RC.4 acceptance.**
15. `2.0.0-rc.4` — controlled recovery of URL-only legacy upload images during schema 1.0 preflight, with explicit recovery reporting. **Superseded by RC.5 acceptance.**
16. `2.0.0-rc.5` — V1 page lifecycle, unsaved frontend preview, save/view, version restore/copy and Designer clipboard parity. **Superseded by RC.6 acceptance.**
17. `2.0.0-rc.6` — V1-style dual update checkpoints with full portable VDM-data backup and 12-entry history. **Superseded by RC.7 acceptance.**
18. `2.0.0-rc.7` — major V1 parity completion batch for Designer elements, named Header/Footer, Menu, page lifecycle, manual, forms and diagnostics. **Candidate for environment acceptance.**
19. `2.0.0` — production release after successful WordPress acceptance QA.

## QA rule

Every push is syntax checked and scanned for previous-generation identifiers before it can be treated as a V2 candidate. Each completed milestone adds a regression contract that remains active for later versions.
