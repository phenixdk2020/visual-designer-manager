from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    target = ROOT / path
    if not target.is_file():
        raise SystemExit(f'RC.9 contract: missing file {path}')
    return target.read_text(encoding='utf-8')


def function_body(source: str, name: str) -> str:
    start = source.find('function ' + name + '(')
    if start < 0:
        return ''
    end = source.find('\n    }\n', start)
    return source[start:end if end > 0 else len(source)]


plugin = read('visual-designer-manager.php')
designer = read('assets/designer.js')
dragdrop = read('assets/designer-dragdrop.js')
manual = read('src/Admin/ManualController.php')
submission = read('src/Forms/FormSubmissionController.php')
form_renderer = read('src/Frontend/FormRenderer.php')

header = re.search(r'Version:\s*2\.0\.0-rc\.(\d+)', plugin)
runtime = re.search(r"define\('VDM_VERSION',\s*'2\.0\.0-rc\.(\d+)'\);", plugin)
version_ok = bool(header and runtime and header.group(1) == runtime.group(1) and int(header.group(1)) >= 9)

render_preview = function_body(designer, 'renderPreview')
after_mutation = function_body(designer, 'afterMutation')
nudge = function_body(designer, 'nudgeSelected')
add_node = function_body(designer, 'addNode')

checks = {
    'runtime version rc.9 or newer': version_ok,
    # The Inspector holds references to node/geometry objects; the preview response must not swap them out.
    'preview response is merged in place': 'syncDocumentInPlace(data.document)' in render_preview
        and 'documentState = data.document' not in render_preview,
    'in-place sync keeps nested objects': 'function syncObjectInPlace' in designer and 'function syncDocumentInPlace' in designer,
    'stale preview responses are ignored': 'renderSequence' in render_preview and 'serialize() !== sent' in render_preview,
    'Inspector keeps focus while typing': 'isTypingInInspector()' in after_mutation and 'function isTypingInInspector' in designer,
    'focused geometry field is not overwritten': 'input === document.activeElement' in function_body(designer, 'updateInspectorGeometryValues'),
    'arrow nudge keeps fine geometry in step': 'geometry.fineX = geometry.x * 10' in nudge,
    'click-added elements stack below siblings': 'nextChildY(parentId)' in add_node and 'function nextChildY' in designer,
    'new Navigation defaults to a menu (click)': 'defaultMenuId()' in add_node,
    'new Navigation defaults to a menu (drag)': "type === 'navigation' && !node.props.menuId" in dragdrop,
    'manual docx splits lines as UTF-8': "preg_split('/\\R+/u'" in manual,
    'mail failure has its own form status': "'mail-error'" in submission and "$status === 'mail-error'" in form_renderer,
    'validation errors still use the field message': 'Kontroller de obligatoriske felter' in form_renderer,
}

failed = [name for name, ok in checks.items() if not ok]
if failed:
    print('RC.9 Designer inspector contract: FAIL')
    for name in failed:
        print(' - ' + name)
    sys.exit(1)

print('RC.9 Designer inspector contract: PASS')
