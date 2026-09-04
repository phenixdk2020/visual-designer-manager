from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def replace_once(path: str, old: str, new: str) -> None:
    file = ROOT / path
    value = file.read_text(encoding='utf-8')
    count = value.count(old)
    if count != 1:
        raise SystemExit(f'{path}: expected one match, got {count}')
    file.write_text(value.replace(old, new, 1), encoding='utf-8')


replace_once(
    'README.md',
    '`2.0.0-rc.1`',
    '`2.0.0-rc.2`',
)

replace_once(
    'README.md',
    """## RC.1 acceptance status\n\nThe automated RC.1 contract is complete and covers the entire alpha.1 → beta.3 regression chain plus schema `1.0` migration validation and migrated visual-property retention.\n\nA production `2.0.0` release still requires environment acceptance on an actual WordPress target after importing the intended site package: Designer → Preview → Live parity, Header/Footer, navigation, forms, Events, Vehicles, Gallery, responsive breakpoints, images, Siteindstillinger and site-shell routing.\n""",
    """## RC.2 acceptance status\n\nRC.2 is the V1 functional and visual parity candidate. It restores the established administration structure, carries reusable field definitions through transfer, preserves module-list styling and adds VDM2-native fine horizontal geometry so converted 120-step positions are not rounded away.\n\nThe automated RC.2 contract extends the full alpha.1 → beta.3 and RC.1 regression chain. A production `2.0.0` release still requires environment acceptance on the designated WordPress test target after importing the intended site package: administration parity, Designer → Preview → Live parity, Header/Footer, Menu, Tema, forms, Events, Vehicles, Gallery, responsive breakpoints, images, Siteindstillinger and site-shell routing.\n""",
)

replace_once(
    'README.md',
    """12. `2.0.0-rc.1` — controlled schema 1.0 migration and automated migration/parity QA. **Code complete; environment acceptance pending.**\n13. `2.0.0` — production release after successful WordPress acceptance QA.\n""",
    """12. `2.0.0-rc.1` — controlled schema 1.0 migration and automated migration/parity QA. **Technical candidate completed; environment parity failed.**\n13. `2.0.0-rc.2` — V1 functional/admin/visual parity, fine geometry and migration hardening. **Candidate for environment acceptance.**\n14. `2.0.0` — production release after successful WordPress acceptance QA.\n""",
)

changelog = ROOT / 'CHANGELOG.md'
value = changelog.read_text(encoding='utf-8')
marker = '# Changelog\n\n'
if value.count(marker) != 1:
    raise SystemExit('CHANGELOG.md: header marker mismatch')
section = """## 2.0.0-rc.2\n\n- Restored the established Visual Designer Manager administration menu order and user-facing terminology.\n- Added native VDM2 administration pages for reusable vehicle fields, reusable event fields, pages, complete backup, update status, diagnostics log and user manual.\n- Added native reusable vehicle/event field registries and integrated them with record editors and frontend rendering.\n- Added portable transfer of reusable field definitions and record values while keeping older native schema 2.0 packages importable.\n- Added VDM2-native fine horizontal geometry so converted 120-step X/width values can render precisely while the editor continues to expose the canonical 12-column geometry.\n- Changed converted site-shell padding to a neutral zero value and carries supported active template content width into Site Design.\n- Converts previous event, vehicle and gallery list nodes directly to their native VDM2 list nodes, retaining count, columns, spacing, card padding/radius and core colors instead of injecting generic defaults.\n- Improved badge migration fallback styling.\n- Added the permanent RC.2 functional/visual parity contract on top of all existing regression gates.\n- RC.2 remains environment-gated: production 2.0.0 requires successful comparison of the designated V2 test installation against the established V1 reference.\n\n"""
changelog.write_text(value.replace(marker, marker + section, 1), encoding='utf-8')

print('RC.2 release documentation applied')
