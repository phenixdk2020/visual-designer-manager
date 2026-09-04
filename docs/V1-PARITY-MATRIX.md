# Visual Designer Manager 2 — V1 parity baseline

**Status:** canonical parity audit before real schema 1.0 import  
**V1 reference:** Visual Designer Manager `0.1.93` in `phenixdk2020/hangar18-manager`  
**V2 baseline:** Visual Designer Manager `2.0.0-rc.4`  
**Environment reference:** `test3.hangar18.dk` = V1/reference, `test4.hangar18.dk` = clean V2 acceptance target

## Goal

V2 must not merely be able to import V1 data. It must preserve the established V1 user workflows, visual behavior and safety contracts while keeping the new V2 storage/runtime architecture.

V1 is the behavioral reference. V2 implementation details may differ, but an administrator/editor should not lose a shipped V1 capability unless it is explicitly documented and accepted as intentionally removed.

Production `2.0.0` is blocked until this parity matrix and the environment acceptance are complete.

## Status legend

- ✅ **Parity** — capability exists in V2 and is covered by automated regression.
- 🟡 **Partial** — foundation exists, but V1 workflow/UI/behavior is incomplete.
- 🔴 **Missing** — shipped V1 capability is not present in V2.
- 🔵 **Environment** — code exists but must be verified on test4 against test3.

## 1. Product/admin shell

| V1 capability | V2 status | Acceptance requirement |
|---|---|---|
| Dashboard and established Manager menu order | ✅ | Menu labels/order remain stable. |
| Køretøjer / Køretøjsfelter | ✅ | CRUD, field IDs and values survive import/reload. |
| Events / Eventfelter | ✅ | CRUD, dates, fields and values survive import/reload. |
| Billedgalleri | ✅ | Album/media order and cover survive import/reload. |
| Sider overview | 🟡 | V2 lists pages and can set Hjem, but V1 workflow also includes create/copy/version operations. |
| Backup | ✅ | Portable backup must preflight successfully before restore. |
| Opdateringer | ✅ | GitHub manifest, SHA-256 and program backup remain regression-gated. |
| Log | 🟡 | Log exists; V1 diagnostic-link/event workflow must be restored. |
| Brugermanual | 🟡 | Admin manual exists; V1 web-manual + Word download parity remains. |
| Eksport | 🟡 | V2 portable site export exists; V1 `Eksporter alt` also packaged plugin/theme/site and double-verified the nested portable package. |
| Menu | ✅ | WordPress menu data remains canonical. |
| Tema / Siteindstillinger | ✅ | Site shell, identity, logo/icon and tokens must match live output. |

## 2. Page lifecycle and versions

| V1 capability | V2 status | Acceptance requirement |
|---|---|---|
| Create new WordPress page from Manager and open Designer | 🔴 | Restore V1 create-page workflow including title/slug/parent/status. |
| Save as new version | 🟡 | V2 increments version history internally, but UI/workflow is reduced to `Gem`. |
| Optional version note | 🔴 | Add V1-compatible meaningful save note/automatic note. |
| `Gem & vis` | 🔴 | Save and open canonical live page in one action. |
| Unsaved frontend preview | 🔴 | Preview current working model through canonical renderer without publishing. |
| Version history list | 🔴 | Show saved versions in Designer. |
| Preview historical version | 🔴 | Read-only preview without changing active page. |
| Restore historical version as a new version | 🔴 | Non-destructive restore. |
| Create page copy from historical version | 🔴 | New draft page with independent V2 history. |
| Duplicate/copy page workflow | 🔴 | Provide safe page-level duplicate workflow. |

## 3. Designer productivity

| V1 capability | V2 status | Acceptance requirement |
|---|---|---|
| Desktop/Laptop/Tablet/Mobile | ✅ | Breakpoint geometry and live rendering match. |
| 120-step fine horizontal geometry | ✅ | Migrated placements retain precision. |
| 8 px vertical grid | ✅ | Editor and renderer use the same rows. |
| Undo/Redo | ✅ | One logical user action = one history step. |
| Keyboard nudge | ✅ | Selected node moves predictably and remains selected. |
| Copy / paste / duplicate selected element | 🔴 | Restore V1 productivity workflow and keyboard shortcuts. |
| Four-way placement/drop behavior | 🟡 | V2 has pointer drag/resize but V1's explicit Over/Under/Venstre/Højre/Ind i workflow must be reproduced or proven behaviorally equivalent. |
| Parent/container hierarchy | ✅ foundation | Must be manually stress-tested with nested Kasser/Sektioner. |
| Auto-height / minimum height | ✅ | No editor/live mismatch or child overflow. |
| Selection overlay must not change frontend geometry | ✅ foundation | Verify editor → preview → live pixel parity. |
| Overlap warning / intentional floating model separation | 🔴/🟡 | V1 behavior must be evaluated and restored where still shipped. |

## 4. General element parity

### Present in V2

- ✅ Sektion
- ✅ Kasse
- ✅ Tekst
- ✅ Billede
- ✅ Knap
- ✅ Mellemrum
- ✅ Skillelinje
- ✅ Navigation/Menu
- ✅ Kontaktformular
- ✅ Bliv medlem
- ✅ Events-list node
- ✅ Køretøjer-list node
- ✅ Billedgalleri-list node

### Shipped in V1 but missing from the V2 node schema

- 🔴 Link
- 🔴 Ikon
- 🔴 Badge
- 🔴 Data List
- 🔴 Tabel

### V1 element behavior to restore/verify

- 🔴/🟡 Floating Button mode and layer ordering.
- 🔴 Common link model for internal page, external URL, anchor, e-mail and telephone.
- 🟡 Image presentation/crop/focus must be compared against V1.
- 🟡 Complete text typography/background/border/spacing controls must be compared field-by-field.
- 🟡 Interaction states (Normal/Hover/Focus/Active/Disabled) must be audited field-by-field.
- 🟡 Shared color picker behavior: popup, theme colors, recent colors, Apply/Cancel/Escape semantics.

## 5. Header/Footer parity

V1 shipped named Header/Footer templates and an assignment resolver. V2 currently stores one global Header document and one global Footer document.

| V1 capability | V2 status |
|---|---|
| Multiple named Header templates | 🔴 |
| Multiple named Footer templates | 🔴 |
| Stable template IDs | 🔴 |
| Independent template histories | 🟡 single-slot history only |
| Website default Header/Footer | 🟡 single global document acts as implicit default |
| Per-page explicit Header selection | 🔴 |
| Per-page explicit Footer selection | 🔴 |
| `Ingen Header` / `Ingen Footer` | 🔴 |
| Assignment resolver | 🔴 |
| Duplicate/rename template without broken references | 🔴 |
| Usage overview/count | 🔴 |
| Shared Designer toolchain | ✅ |

This is a release-blocking parity area because Header/Footer differences can make every imported page visually different even when page layout itself is correct.

## 6. Dynamic module parity

V1 matured beyond simple collection nodes. Acceptance must cover the shipped V1 module workflows, not only record import.

| V1 capability | V2 status |
|---|---|
| Vehicle CRUD + flexible fields | ✅ foundation |
| Event CRUD + flexible fields | ✅ foundation |
| Gallery album CRUD/media order | ✅ foundation |
| Collection list rendering | ✅ foundation |
| Editable module/card design | 🟡 V2 has list-node styling, but field-by-field parity is not proven |
| Hybrid module pages with ordinary Designer content before/between/after module flow | 🔴/🟡 audit required |
| Designer-controlled detail pages | 🔴/🟡 V2 currently uses native detail renderers |
| Eventværdi elements | 🔴 |
| Eventbillede element | 🔴 |
| Eventfelt elements | 🔴 |
| Eventfaktabånd element | 🔴 |
| Event → album / archive workflow | 🔴/🟡 audit required |
| Search/sort/archive behavior | 🔴/🟡 audit required |

Vehicle/Event/Gallery data must not be destructively changed while parity is being rebuilt.

## 7. Forms parity

V2 has native Contact and Membership form nodes, but V1 0.1.90–0.1.91 explicitly regression-gated Designer/live parity and detailed form spacing.

Required comparison:

- field and label typography;
- field gap;
- textarea/comment height;
- consent spacing;
- button padding;
- auto-height/overflow;
- same geometry in Designer, preview and live;
- exact required/optional field behavior;
- success/error handling and recipient rules.

Status: 🟡 **implementation present, V1 parity not yet proven**.

## 8. Export/import/recovery parity

| V1 capability | V2 status |
|---|---|
| Portable site package | ✅ |
| Read-only preflight | ✅ |
| Safe ZIP path validation | ✅ |
| Per-file size/SHA-256 | ✅ |
| Deterministic content digest | ✅ |
| ID remapping | ✅ |
| Schema 1.0 migration | ✅ |
| URL-only legacy image recovery | ✅ RC.4 |
| `Eksporter alt` plugin + active/parent theme + portable site | 🔴 |
| Outer archive verification + nested portable verification | 🔴 |
| Human-readable export summary/recovery README | 🔴 |

## 9. Diagnostics/manual parity

V1 shipped an operational support workflow, not only a log page.

Missing/partial V2 items:

- 🔴 `Kopiér diagnose-link` from Designer.
- 🟡 comparable event coverage for drag/reparent/resize/undo/save/preview/restore/media.
- 🔴 automatically provisioned web user manual page.
- 🔴 Word `.docx` manual generated from the same canonical source.

## 10. Visual parity gate

For every imported production page, compare `test4` against `test3` at:

- Desktop
- Laptop
- Tablet
- Mobile

Compare at minimum:

- Header and Footer
- page width and side padding
- section/container positions and sizes
- fonts, weights and line heights
- backgrounds, borders and radii
- image crop/focus
- buttons
- navigation/hamburger behavior
- forms
- Events
- Vehicles
- Gallery

Designer → unsaved Preview → saved Live must use one canonical rendering contract and be visually equivalent except for editor-only chrome.

## 11. Work sequence

### P0 — Freeze and baseline

- Keep `test3` read-only as V1 reference.
- Do not run the real import on test4 yet.
- Use V1 0.1.93 release/code/docs as the shipped feature baseline.
- Capture page-by-page and workflow evidence where code/docs leave ambiguity.

### P1 — Page/version/editor workflow parity

Restore create page, unsaved preview, save/version workflow, history preview/restore/copy, page copy, element copy/paste/duplicate and explicit placement behavior.

### P2 — General element parity

Add Link, Icon, Badge, Data List and Table plus the common link model, floating button behavior and remaining V1 Inspector contracts.

### P3 — Header/Footer parity

Implement named templates, stable IDs, default assignments, per-page overrides, `Ingen`, duplicate/rename and deterministic resolver.

### P4 — Dynamic module parity

Rebuild the shipped V1 hybrid collection/detail Designer capabilities and exact module design/search/archive behavior without weakening V2 data isolation.

### P5 — Forms/support/export parity

Close form geometry gaps, diagnostics workflow, web/Word manual and `Eksporter alt` double-verification.

### P6 — Migration and visual parity

Import the real schema 1.0 package into test4, then run page-by-page and breakpoint-by-breakpoint comparison against test3.

### P7 — Full acceptance

Automated regression + manual acceptance. Production `2.0.0` only after all release-blocking rows are green or explicitly waived.

## Release rule

Do not promote `2.0.0` merely because schema migration succeeds. The release gate is **behavioral V1 parity + visual test3/test4 parity + safe rollback**.
