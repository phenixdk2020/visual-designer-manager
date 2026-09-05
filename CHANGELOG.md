# Changelog

## 2.0.0-rc.7

- Restored named Header and Footer templates with stable IDs, independent histories, active/default state and template settings.
- Added per-page Header/Footer assignment with automatic/default, explicit-template and no-region choices plus canonical resolver fallback.
- Restored the in-manager WordPress Menu workflow for pages, custom links, headings, hierarchy, ordering, theme locations and 30 recoverable snapshots.
- Added V1-parity Designer nodes for Link, Icon, Badge, Data List and Table.
- Added composable Event Value, Event Image, Event Field and Event Facts nodes plus Vehicle Detail and Gallery Detail nodes.
- Added floating Button mode with z-index/layer control and the controlled root-level overlay hierarchy exception.
- Added V1-style Over, Under, Venstre, Højre and Ind i placement helpers.
- Extended Text and Image presentation controls used by migrated/reference layouts.
- Added page duplicate, publish/draft and trash lifecycle actions with independent VDM history on duplicates.
- Restored public/admin user-manual parity and on-demand Word `.docx` export from one canonical manual source.
- Added per-form recipient selection, controlled VDM recipient filtering and optional sender receipt mail.
- Added page-filtered diagnostics, per-page clearing and copyable support/diagnostic links.
- Registered the new page-lifecycle, diagnostics, Designer parity, template-assignment and manual controllers in the canonical V2 boot path.
- Extended all earlier regression contracts for the deliberate named-template/root-floating schema evolution without weakening their behavioral invariants.
- Added a permanent RC.7 V1 parity completion regression gate and JavaScript syntax checking for the parity runtime.
- Production `2.0.0` remains blocked on the remaining module/export/theme parity work and real test3-to-test4 responsive visual acceptance.

## 2.0.0-rc.6

- Restored V1-style update checkpoint history on the native VDM2 update channel.
- Added a complete portable VDM-data checkpoint after the existing verified program backup and before plugin replacement.
- The VDM-data checkpoint includes pages/layouts, Header/Footer, Site Design, Site settings, reusable fields, Events, Vehicles, Gallery, menus and referenced media.
- Revalidates the portable package after moving it into permanent checkpoint storage and records SHA-256 plus file size for both program and VDM-data files.
- Stops the WordPress plugin update if the VDM-data checkpoint cannot be created or validated.
- Keeps up to 12 complete checkpoint pairs and removes both files together when retention is exceeded.
- Added an `Update-checkpoints` table with Fra, Til, Dato, Program, VDM-data and authenticated download actions.
- Surfaces still-existing earlier V2 program-only backups as legacy checkpoint rows.
- Keeps the RC.3 SHA-256 package verification, WordPress Plugins integration and GitHub build-status links.
- Added permanent RC.6 regression coverage while retaining every earlier Alpha/Beta/RC gate.
- RC.5 to RC.6 is the bootstrap transition: the RC.5 updater can only create its existing program backup before RC.6 is installed; later updates create both checkpoint files automatically.

## 2.0.0-rc.5

- Restored the V1-style `Sider` workflow for creating a page with title, optional slug, parent and draft/published status and opening it directly in Visual Designer.
- Renamed the canonical Designer save action to `Gem som ny version` and added `Gem & vis`.
- Added user-bound, short-lived unsaved frontend preview through the real VDM page/frontend or site-shell rendering path without changing the saved page.
- Added saved version history below the Designer with historical preview, non-destructive restore-as-new-version and copy-to-new-draft actions.
- Added session clipboard controls for Copy, Paste and Duplicate of a selected Designer subtree, including Ctrl+C, Ctrl+V and Ctrl+D.
- Exposed a minimal VDM2 Designer runtime bridge for controlled page-workflow extensions without introducing previous-generation runtime aliases.
- Added diagnostic records for page creation, historical restore and historical page copy.
- Added permanent RC.5 page-workflow regression coverage while retaining every earlier Alpha/Beta/RC gate.
- Production `2.0.0` remains blocked on the remaining V1 element, Header/Footer and real reference-to-V2 visual parity workstreams.

## 2.0.0-rc.4

- Added controlled schema 1.0 recovery for Image nodes that have an uploads URL but no media ID.
- Recovery first retries the same `/wp-content/uploads/` path against the package source site, allowing stale source-host references to resolve without changing the source package.
- Original remote URLs are only fetched through WordPress' safe HTTP client and only for validated upload-image paths.
- Added strict 25 MiB recovery limit, non-SVG image validation, temporary-file validation and SHA-256 integrity checks.
- Recovered media is embedded into the temporary native V2 package and receives a normal source ID so the canonical importer remaps it like packaged media.
- Preflight remains non-mutating for WordPress content: recovered files exist only in temporary package storage until explicit import confirmation.
- Added explicit preflight counts for recovered and unresolved legacy images.
- Added the permanent RC.4 legacy-media recovery contract while retaining all earlier regression gates.
- RC.4 is the next environment-acceptance candidate before production `2.0.0`.

## 2.0.0-rc.3

- Carried all RC.2 V1 functional and visual parity work forward.
- Added a native VDM2 GitHub updater integrated with WordPress plugin update transients and plugin information.
- Added direct update status, manual GitHub check and install actions to the `Opdateringer` administration page.
- Restricted the updater to the Visual Designer Manager repository manifest and versioned `dist/` package path.
- Added SHA-256 verification of downloaded update packages before WordPress can install it.
- Added an automatic program backup of the installed VDM plugin before replacement, with bounded backup retention.
- Extended the package workflow to publish the verified versioned ZIP under `dist/` and generate the canonical `update.json` manifest with version, package path and SHA-256.
- Added a recursion guard so the updater publication commit cannot trigger another package publication loop.
- Added the permanent RC.3 GitHub updater contract on top of the existing regression chain.
- RC.3 is the one-time bootstrap for the VDM2 update channel; older VDM2 candidates require one final manual RC.3 ZIP installation.
- Production `2.0.0` remains gated on environment parity and update-channel acceptance.

## 2.0.0-rc.2

- Restored the established Visual Designer Manager administration menu order and user-facing terminology.
- Added native VDM2 administration pages for reusable vehicle fields, reusable event fields, pages, complete backup, update status, diagnostics log and user manual.
- Added native reusable vehicle/event field registries and integrated them with record editors and frontend rendering.
- Added portable transfer of reusable field definitions and record values while keeping older native schema 2.0 packages importable.
- Added VDM2-native fine horizontal geometry so converted 120-step X/width values can render precisely while the editor continues to expose the canonical 12-column geometry.
- Changed converted site-shell padding to a neutral zero value and carries supported active template content width into Site Design.
- Converts previous event, vehicle and gallery list nodes directly to their native VDM2 list nodes, retaining count, columns, spacing, card padding/radius and core colors instead of injecting generic defaults.
- Improved badge migration fallback styling.
- Added the permanent RC.2 functional/visual parity contract on top of all existing regression gates.
- RC.2 remains environment-gated: production 2.0.0 requires successful comparison of the designated V2 test installation against the established V1 reference.

## 2.0.0-rc.1

- Added an isolated schema `1.0` site-package migrator without introducing runtime compatibility storage or aliases into V2.
- Added strict schema `1.0` package validation for safe paths, case-insensitive duplicates, symbolic links, unlisted files, size limits, per-file SHA-256 and the original deterministic package content digest.
- Added a convert-then-import architecture: a validated schema `1.0` upload is converted to a temporary native schema `2.0` package, that native package is fully validated again, and the existing canonical V2 importer performs the actual WordPress changes.
- Added preflight migration metadata and warnings while preserving the rule that preflight itself does not mutate WordPress content.
- Added 120-unit to 12-column responsive geometry conversion while preserving the 8 px vertical row grid.
- Added conversion for previous-generation Section, Container, Text, Image, Button, Spacer, Divider, Contact Form, Membership Form and Navigation nodes.
- Added visible fallback conversion for table, data-list, icon and badge content into canonical V2 Text nodes.
- Added conversion of previous event, vehicle and gallery records into V2-native content types plus list-node injection on their corresponding pages.
- Previous generated Event, Vehicle and Gallery detail-layout pages are omitted during migration because V2 uses native detail renderers; the migration reports this in preflight warnings.
- Added media conversion using only originals present in the validated package; remote media URLs that have no packaged original are not downloaded implicitly.
- Extended the general V2 Section/Container contract with radius, border width and border color.
- Extended the general V2 Text contract with font weight, line height, horizontal alignment, vertical alignment, background, padding and radius.
- Extended the general V2 Button contract with border width and border color.
- Added matching Designer Inspector controls and canonical renderer/CSS variables for those presentation properties so migrated values remain editable and render identically through the normal V2 pipeline.
- Added the permanent RC.1 schema migration/parity contract on top of the full alpha.1 through beta.3 regression chain.
- Automated RC.1 code QA is complete; production release remains gated on importing the intended package into an actual WordPress target and completing Designer → Preview → Live acceptance QA.

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

## 2.0.0-beta.2

- Added a V2 Navigation Designer node backed by canonical WordPress menus.
- Added Navigation to both Page Designer and Header/Footer Designer with menu, orientation, alignment, spacing, typography, color and mobile-toggle controls.
- Added a shared frontend runtime for responsive mobile navigation across pages and V2 detail templates.
- Added a VDM Navigation administration page linking to the canonical WordPress menu editor.
- Added Siteindstillinger for WordPress site title, tagline and site icon plus VDM organization, contact and logo values.
- Added WordPress Media Library selection for VDM logo and site icon with image validation.
- Kept the VDM logo independent of theme-specific custom-logo storage.
- Expanded the permanent QA chain with the beta.2 Navigation and Site Settings contract.

## 2.0.0-beta.1

- Added V2-native `Kontaktformular` and `Bliv medlem` page Designer nodes.
- Both presets use one canonical `FormRenderer`, so Designer preview and frontend share the same field, textarea, consent and submit-button markup.
- Added Inspector controls for columns, spacing, padding, radius, form/field/text/label/border/button colors, submit label, success message and optional fields.
- Added configurable phone, subject/address, message rows and consent text/requirement.
- Added a secure WordPress `admin-post.php` submission handler for logged-in and anonymous visitors.
- Form submissions are validated against the stored V2 page and exact form node ID/type before mail is sent.
- Recipient addresses are never accepted from browser form data; VDM uses `vdm_contact_email` when configured and otherwise the WordPress administration e-mail address.
- Added nonce verification, a honeypot field, field sanitization, safe redirect validation and 303 redirects after submission.
- Form submissions are delivered through `wp_mail()` and are not copied into a separate VDM submission database.
- Added canonical responsive form CSS and blocked actual submission inside the Designer canvas.
- Forms are intentionally excluded from the Header/Footer palette.
- Hardened Header/Footer Designer runtime endpoint handling and added a permanent runtime regression contract.
- Expanded QA with the beta.1 Forms contract.

## 2.0.0-alpha.8

- Added the V2-native `vdm_gallery` album content type with title, editor, featured cover and explicit image ordering.
- Added WordPress Media Library multi-selection, remove-all, individual removal and drag-based image ordering in the album editor.
- Added a Gallery Designer node for album count, columns, spacing, padding, radius, colors, cover visibility and summary visibility.
- Added canonical album-card rendering with cover fallback to the first album image, image count, summary and detail link.
- Added canonical album detail rendering with responsive image grid, captions and full-image links.
- Added optional VDM site-shell routing for album details using the same Header/Footer/Site Design stack.
- Added responsive Gallery CSS for desktop, laptop/tablet and mobile.
- Expanded QA with the alpha.8 Gallery contract.

## 2.0.0-alpha.7

- Added the V2-native `vdm_vehicle` content type for vehicles and material.
- Added fixed technical fields for type, manufacturer, model, year, origin, status, engine, power, weight, dimensions and crew.
- Added flexible per-vehicle technical field rows so specialized material is not limited to one fixed schema.
- Added a Vehicles Designer node with count, columns, spacing, card padding, radius, colors and display toggles.
- Added canonical Vehicle card rendering and detail rendering.
- Vehicle detail uses image-left / technical-data-right geometry on desktop and stacks responsively on smaller screens.
- Added optional VDM site-shell routing for Vehicle details.
- Expanded QA with the alpha.7 Vehicles contract.

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
- Added responsive geometry for Desktop/Laptop/Tablet/Mobile.
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
