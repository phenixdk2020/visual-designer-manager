# Visual Designer Manager 2 delivery roadmap

## 2.0.0-alpha.1 — Foundation

- VDM-only plugin identity.
- Namespace autoloader and plugin kernel.
- Admin dashboard and health endpoint.
- Schema version 2 layout document.
- Four canonical responsive breakpoints.
- Syntax and identity QA.

## 2.0.0-alpha.2 — Designer and renderer

- Page layout repository with V2-only storage keys.
- Canonical node schema and hierarchy validation.
- Shared PHP renderer for editor preview and frontend.
- Designer workspace with palette, canvas, selection and inspector.
- Save/reload and version counter.
- Initial Section, Container, Text, Image, Button, Spacer and Divider nodes.

## 2.0.0-alpha.3 — Site shell

- Header/Footer templates.
- Site-wide defaults and per-page overrides.
- Responsive inheritance for Desktop, Laptop, Tablet and Mobile.
- Unified preview of Header + Page + Footer.

## 2.0.0-alpha.4 — Dynamic modules

- Vehicles.
- Events.
- Gallery.
- Flexible module fields.
- List/detail bindings and media references.

## 2.0.0-beta.1 — Site tools

- Contact and membership forms.
- Navigation editor.
- Site settings, logo and site icon.
- Compact color popup with theme colors and recent colors.
- Form geometry parity between Designer and frontend.

## 2.0.0-beta.2 — Portable transfer

- Complete site export.
- Manifest, file counts and SHA-256 verification.
- Read-only import preflight.
- Explicit source-to-target ID remapping.
- V2 import package contains V2 identifiers only.

## 2.0.0-rc.1 — Site acceptance

- Import the verified site backup through the V2 transfer format.
- Desktop/Laptop/Tablet/Mobile visual acceptance.
- Editor → Preview → Live comparison for every main page and module detail view.
- Recovery package generated after acceptance.

## 2.0.0 — Production

- Production updater and installable ZIP.
- Full release gate required before publication.
