# Visual Designer Manager 2.0.0-rc.2 acceptance

RC.2 is the V1 functional and visual parity candidate.

## Product rule

Version 2 is a clean technical reimplementation of the established Visual Designer Manager product. User-facing names, administration flow and migrated output are preserved unless a later product change is explicitly approved.

The migration adapter may understand the previous package schema. Runtime code, storage keys, namespaces, routes and file names remain native VDM2 after conversion.

## Reference environments

- `test3.hangar18.dk`: reference installation.
- `test4.hangar18.dk`: RC.2 acceptance installation.

## RC.2 changes

- Restores the established administration menu order and terminology.
- Adds native VDM2 pages for Dashboard-adjacent workflows: vehicle fields, event fields, pages, backup, updates, diagnostics log and user manual.
- Adds native central reusable field registries for vehicles and events.
- Includes those field definitions in native portable export/import and schema conversion.
- Preserves imported field values in event and vehicle records.
- Preserves 120-step horizontal placement through VDM2-native fine geometry while retaining the 12-column editor geometry.
- Uses neutral zero outer content padding for converted layouts and carries supported template content width into Site Design.
- Converts existing event, vehicle and gallery list nodes directly into their VDM2 equivalents so card count, columns, gap, padding, radius and core colors are retained instead of being replaced by generic injected defaults.
- Keeps a permanent RC.2 QA contract on top of the existing regression chain.

## Environment acceptance

RC.2 is not production-approved until test4 has been compared against test3 for:

- administration menu and workflows;
- page structure;
- Header and Footer;
- Menu;
- Tema and Siteindstillinger;
- Hjem, Om, Kontakt and Bliv medlem;
- Events list and detail;
- Køretøjer list and detail;
- Billedgalleri list and detail;
- images and referenced media;
- desktop, laptop, tablet and mobile;
- Designer -> Preview -> Live parity.

Any difference found during this acceptance is treated as a release-candidate defect, not as an intentional redesign.
