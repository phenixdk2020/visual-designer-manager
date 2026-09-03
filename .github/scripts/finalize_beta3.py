from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]


def replace_once(path: Path, old: str, new: str) -> None:
    text = path.read_text(encoding='utf-8')
    count = text.count(old)
    if count != 1:
        raise SystemExit(f'{path}: expected exactly one match, found {count}: {old[:120]!r}')
    path.write_text(text.replace(old, new, 1), encoding='utf-8')


# Do not expose the exporter's local filesystem helper in media/index.json.
exporter = ROOT / 'src' / 'Transfer' / 'PortableExporter.php'
replace_once(
    exporter,
    "        $media = self::mediaRecords(array_keys($mediaIds));\n\n        $site = [",
    "        $media = self::mediaRecords(array_keys($mediaIds));\n        $mediaPayload = [];\n        foreach (array_values($media) as $record) {\n            $publicRecord = $record;\n            unset($publicRecord['_filePath']);\n            $mediaPayload[] = $publicRecord;\n        }\n\n        $site = [",
)
replace_once(
    exporter,
    "            'media/index.json' => PortablePackage::json(['items' => array_values($media)]),",
    "            'media/index.json' => PortablePackage::json(['items' => $mediaPayload]),",
)

# Preserve hierarchical page paths when finding an existing target page.
importer = ROOT / 'src' / 'Transfer' / 'PortableImporter.php'
replace_once(
    importer,
    "            $path = sanitize_title_with_dashes((string) ($record['path'] ?? ''));",
    "            $rawPath = trim((string) ($record['path'] ?? ''), '/');\n            $pathParts = $rawPath === '' ? [] : array_values(array_filter(array_map('sanitize_title', explode('/', $rawPath)), static fn(string $part): bool => $part !== ''));\n            $path = implode('/', $pathParts);",
)

# PHP 8.0 compatibility: never return type was added in PHP 8.1.
transfer = ROOT / 'src' / 'Admin' / 'TransferController.php'
replace_once(
    transfer,
    "    private static function redirect(array $args): never",
    "    private static function redirect(array $args): void",
)

# Runtime version.
plugin = ROOT / 'visual-designer-manager.php'
replace_once(plugin, ' * Version: 2.0.0-beta.2', ' * Version: 2.0.0-beta.3')
replace_once(plugin, "define('VDM_VERSION', '2.0.0-beta.2');", "define('VDM_VERSION', '2.0.0-beta.3');")

# Boot transfer admin.
core = ROOT / 'src' / 'Core' / 'Plugin.php'
replace_once(
    core,
    "use VisualDesignerManager\\Admin\\TemplateDesignerController;\nuse VisualDesignerManager\\Admin\\VehicleController;",
    "use VisualDesignerManager\\Admin\\TemplateDesignerController;\nuse VisualDesignerManager\\Admin\\TransferController;\nuse VisualDesignerManager\\Admin\\VehicleController;",
)
replace_once(
    core,
    "        NavigationController::register();\n        EventController::register();",
    "        NavigationController::register();\n        TransferController::register();\n        EventController::register();",
)

# Dashboard link.
admin = ROOT / 'src' / 'Admin' / 'AdminController.php'
replace_once(
    admin,
    "        $navigationUrl = admin_url('admin.php?page=' . NavigationController::MENU_SLUG);",
    "        $navigationUrl = admin_url('admin.php?page=' . NavigationController::MENU_SLUG);\n        $transferUrl = admin_url('admin.php?page=' . TransferController::MENU_SLUG);",
)
replace_once(
    admin,
    "                echo '<a class=\"button\" href=\"' . esc_url($siteSettingsUrl) . '\">Siteindstillinger</a> ';\n            }\n            echo '<a class=\"button\" href=\"' . esc_url($navigationUrl) . '\">Navigation</a>';",
    "                echo '<a class=\"button\" href=\"' . esc_url($siteSettingsUrl) . '\">Siteindstillinger</a> ';\n                echo '<a class=\"button\" href=\"' . esc_url($transferUrl) . '\">Eksport / import</a> ';\n            }\n            echo '<a class=\"button\" href=\"' . esc_url($navigationUrl) . '\">Navigation</a>';",
)

# Historical beta.2 contract must remain valid on newer beta versions.
beta2 = ROOT / 'tests' / 'beta2_navigation_site_settings_contract.py'
replace_once(
    beta2,
    "    'runtime version beta.2 or newer': version_ok and \"define('VDM_VERSION', '2.0.0-beta.2');\" in plugin,",
    "    'runtime version beta.2 or newer': version_ok,",
)

# Permanent beta.3 QA gate.
qa = ROOT / '.github' / 'workflows' / 'qa.yml'
replace_once(
    qa,
    "      - name: Beta 2 Navigation and Site Settings contract\n        run: python3 tests/beta2_navigation_site_settings_contract.py\n",
    "      - name: Beta 2 Navigation and Site Settings contract\n        run: python3 tests/beta2_navigation_site_settings_contract.py\n\n      - name: Beta 3 Portable transfer contract\n        run: python3 tests/beta3_portable_transfer_contract.py\n",
)

readme = """# Visual Designer Manager 2

Visual Designer Manager is a model-driven visual WordPress designer.

## Current version

`2.0.0-beta.3`

## Version 2 principles

- Independent repository and release history.
- Product identity is Visual Designer Manager / VDM everywhere.
- Plugin folder: `visual-designer-manager`.
- Main plugin file: `visual-designer-manager.php`.
- PHP namespace: `VisualDesignerManager\\`.
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
"""
(ROOT / 'README.md').write_text(readme, encoding='utf-8')

changelog = ROOT / 'CHANGELOG.md'
text = changelog.read_text(encoding='utf-8')
marker = '# Changelog\n\n'
if text.count(marker) != 1:
    raise SystemExit('CHANGELOG.md: unexpected heading')
entries = """# Changelog

## 2.0.0-beta.3

- Added the native V2 portable package format with schema `2.0` and explicit required payload paths.
- Added package validation for safe ZIP paths, case-insensitive duplicate paths, symbolic links, unlisted entries and bounded uncompressed sizes.
- Added per-file SHA-256 and size verification plus a deterministic whole-package content digest.
- Added complete V2 export for pages/layouts, Header/Footer, Site Design, Siteindstillinger, Events, Vehicles, Gallery albums, WordPress menus and referenced original media.
- Removed exporter-only local filesystem paths from the public media index.
- Added media import with extraction-time SHA-256 validation, WordPress sideloading and SHA-based reuse of previously imported media.
- Added source-to-target remapping for pages, content records, featured images, Gallery image lists, Navigation menu IDs and Designer image attachment IDs.
- Added WordPress menu and menu-item import with post-object and parent-item remapping.
- Added remapping of internal content URLs, image class IDs, Button URLs, site identity logo/icon IDs, reading settings and front/posts page IDs.
- Added a two-step administrator workflow: upload/preflight followed by explicit import confirmation.
- Preflight state is tied to the current administrator and a staged-file SHA-256; the package is fully revalidated immediately before import.
- Added guarded cleanup of newly created objects when import fails.
- Beta.3 intentionally accepts native schema `2.0`; controlled schema `1.0` migration is deferred to RC.
- Expanded the permanent QA chain with the beta.3 portable transfer contract.

## 2.0.0-beta.2

- Added a V2 Navigation Designer node backed by canonical WordPress menus.
- Added Navigation to both Page Designer and Header/Footer Designer with menu, orientation, alignment, spacing, typography, color and mobile-toggle controls.
- Added a shared frontend runtime for responsive mobile navigation across pages and V2 detail templates.
- Added a VDM Navigation administration page linking to the canonical WordPress menu editor.
- Added Siteindstillinger for WordPress site title, tagline and site icon plus VDM organization, contact and logo values.
- Added WordPress Media Library selection for VDM logo and site icon with image validation.
- Kept the VDM logo independent of theme-specific custom-logo storage.
- Expanded the permanent QA chain with the beta.2 Navigation and Site Settings contract.

"""
changelog.write_text(text.replace(marker, entries, 1), encoding='utf-8')

print('VDM 2.0.0-beta.3 finalization applied.')
