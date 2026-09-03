from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
main = (ROOT / 'visual-designer-manager.php').read_text(encoding='utf-8')
admin = (ROOT / 'src/Admin/AdminController.php').read_text(encoding='utf-8')
rest = (ROOT / 'src/Http/RestController.php').read_text(encoding='utf-8')
layout = (ROOT / 'src/Model/LayoutDocument.php').read_text(encoding='utf-8')
breakpoints = (ROOT / 'src/Model/Breakpoint.php').read_text(encoding='utf-8')

assert 'Plugin Name: Visual Designer Manager' in main
assert re.search(r'Version:\s*2\.0\.0-alpha\.1', main)
for constant in ('VDM_VERSION', 'VDM_FILE', 'VDM_DIR', 'VDM_URL'):
    assert constant in main, constant
assert "public const MENU_SLUG = 'vdm-manager';" in admin
assert "public const NAMESPACE = 'visual-designer-manager/v2';" in rest
assert 'public const SCHEMA_VERSION = 2;' in layout
for name in ('DESKTOP', 'LAPTOP', 'TABLET', 'MOBILE'):
    assert f'public const {name}' in breakpoints, name
for width in ('1440', '1180', '980', '782'):
    assert width in breakpoints, width

print('V2 bootstrap contract: PASS')
