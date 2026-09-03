from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[1]
js = (ROOT / 'assets' / 'designer.js').read_text(encoding='utf-8')
controller = (ROOT / 'src' / 'Admin' / 'TemplateDesignerController.php').read_text(encoding='utf-8')

checks = {
    'template target allowed without page id': "Boolean(config.templateSlot)" in js and "Boolean(config.ready)" in js and "!config.pageId" not in js,
    'localized render endpoint honored': "const renderUrl = config.renderUrl || (config.restBase + '/render');" in js and 'fetch(renderUrl' in js,
    'localized save endpoint honored': "const saveUrl = config.saveUrl || (config.restBase + '/layouts/' + config.pageId);" in js and 'fetch(saveUrl' in js,
    'template config supplies endpoints': "'ready' => true" in controller and "'saveUrl' =>" in controller and "'renderUrl' =>" in controller and "'templateSlot' => $slot" in controller,
}

failed = [name for name, ok in checks.items() if not ok]
if failed:
    print('Template Designer runtime contract: FAIL')
    for name in failed:
        print(' - ' + name)
    sys.exit(1)

print('Template Designer runtime contract: PASS')
