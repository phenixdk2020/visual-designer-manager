# Changelog

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
