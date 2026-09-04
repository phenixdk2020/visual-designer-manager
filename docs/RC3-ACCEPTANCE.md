# Visual Designer Manager 2.0.0-rc.3 acceptance

RC.3 carries the RC.2 functional and visual parity work forward and adds the native GitHub update channel.

## Product acceptance

Version 2 remains an independent implementation of the established Visual Designer Manager user experience. RC.3 does not approve a redesign. Functional and visual differences against the designated V1 reference remain release-candidate defects unless explicitly approved later.

## Update-channel acceptance

The RC.3 bootstrap installation is performed once with the installable WordPress ZIP. After RC.3 is installed, the `Opdateringer` page must be able to:

- read the public VDM `update.json` manifest from the Visual Designer Manager repository;
- show the installed and latest published versions;
- perform a forced manual GitHub update check;
- expose a direct update action only when the manifest version is newer;
- integrate the same update into WordPress' normal Plugins update mechanism;
- reject packages whose URL, slug, version or SHA-256 does not match the accepted manifest contract;
- create a program backup before replacing the installed plugin;
- write meaningful success or failure information to the VDM diagnostics log.

The canonical package publisher must:

- run from the repository `main` branch;
- build an installable ZIP rooted at `visual-designer-manager/`;
- validate PHP and JavaScript in the packaged runtime;
- publish the versioned package under `dist/`;
- calculate the package SHA-256;
- publish `update.json` with the exact versioned package path and checksum;
- prevent the manifest/package publication commit from recursively publishing another package.

## Parity acceptance

The existing RC.2 acceptance requirements remain active, including:

- administration menu and workflows;
- page structure and page assignment;
- Header and Footer;
- Menu;
- Tema and Siteindstillinger;
- forms;
- Events list and detail;
- Køretøjer list and detail;
- Billedgalleri list and detail;
- referenced media;
- desktop, laptop, tablet and mobile;
- Designer -> Preview -> Live parity.

## Production gate

RC.3 is not production-approved solely because automated QA passes. A production `2.0.0` release still requires successful environment acceptance against the designated V1 reference and a verified update check on the designated RC test installation.
