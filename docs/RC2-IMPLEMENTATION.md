# RC.2 implementation scope

This candidate focuses on parity, not product redesign.

## Administration parity

The administration menu is normalized to the established order and labels. Native VDM2 implementations are present for central vehicle fields, central event fields, page overview/home-page selection, complete backup, update status, diagnostics log and a user-manual entry point.

## Migration parity

The schema conversion now carries reusable field definitions and record values, preserves list-node styling for Events, Vehicles and Gallery, derives supported shell width settings, removes default outer content padding from converted layouts, and preserves precise horizontal placement with VDM2 fine geometry.

## Compatibility boundary

Previous package parsing remains isolated in the transfer layer. Imported content is persisted through normal VDM2 repositories and keys.

## Remaining acceptance rule

Automated contracts prove structure and regression safety; they do not replace the required visual comparison between test3 and test4. The candidate must still pass the environment acceptance checklist before a final 2.0.0 release.
