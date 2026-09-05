from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    target = ROOT / path
    if not target.is_file():
        raise SystemExit(f'RC.8 contract: missing file {path}')
    return target.read_text(encoding='utf-8')

plugin = read('visual-designer-manager.php')
controller = read('src/Admin/DesignerParityController.php')
dragdrop = read('assets/designer-dragdrop.js')
style = read('assets/designer-dragdrop.css')
base_designer = read('assets/designer.js')
page_designer = read('src/Admin/DesignerController.php')
template_designer = read('src/Admin/TemplateDesignerController.php')

header = re.search(r'Version:\s*2\.0\.0-rc\.(\d+)', plugin)
runtime = re.search(r"define\('VDM_VERSION',\s*'2\.0\.0-rc\.(\d+)'\);", plugin)
version_ok = bool(header and runtime and header.group(1) == runtime.group(1) and int(header.group(1)) >= 8)

checks = {
    'runtime version rc.8 or newer': version_ok,
    'dragdrop runtime is enqueued after parity runtime': (
        "wp_enqueue_script('vdm-designer-dragdrop'" in controller
        and "['vdm-designer-parity']" in controller
        and "assets/designer-dragdrop.js" in controller
    ),
    'dragdrop stylesheet is enqueued': "assets/designer-dragdrop.css" in controller,
    'base Designer still supports click add': ".vdm-palette-item" in base_designer and "addEventListener('click'" in base_designer,
    'page Designer exposes palette node types': 'data-node-type=' in page_designer and 'vdm-palette-item' in page_designer,
    'template Designer exposes palette node types': 'data-node-type=' in template_designer and 'vdm-palette-item' in template_designer,
    'palette items are made draggable': "button.draggable = true" in dragdrop and "aria-grabbed" in dragdrop,
    'native drag start is implemented': "addEventListener('dragstart'" in dragdrop and "effectAllowed = 'copy'" in dragdrop,
    'drag payload contains node type': "application/x-vdm-node-type" in dragdrop and "setData('text/plain', type)" in dragdrop,
    'canvas dragover permits dropping': "canvas.addEventListener('dragover'" in dragdrop and 'event.preventDefault()' in dragdrop and "dropEffect = 'copy'" in dragdrop,
    'canvas drop inserts through canonical runtime': "canvas.addEventListener('drop'" in dragdrop and 'addDropped(type, event)' in dragdrop and 'api.replaceDocument(doc, node.id)' in dragdrop,
    'drop resolves section or container parent': 'parentFromPointer' in dragdrop and "['section','container'].includes" in dragdrop,
    'empty document auto creates a section': 'sectionNode(doc, nextRootY(doc))' in dragdrop,
    'drop geometry uses 12-unit and 8px grid': '* 12' in dragdrop and '/ ROW_PX' in dragdrop and 'fineX:x*10' in dragdrop,
    'all RC.7 parity palette labels can be dragged': all(label in dragdrop for label in (
        'Link', 'Ikon', 'Badge', 'Data List', 'Tabel', 'Eventværdi', 'Eventbillede',
        'Eventfelt', 'Eventfaktabånd', 'Køretøjsdetalje', 'Albumvisning'
    )),
    'drag lifecycle cleanup exists': "addEventListener('dragend'" in dragdrop and 'cleanup()' in dragdrop,
    'visual drop feedback exists': all(token in style for token in (
        'is-palette-drop-active', 'is-palette-drop-target', 'vdm-palette-drop-hint', 'cursor:grab'
    )),
}

failed = [name for name, ok in checks.items() if not ok]
if failed:
    print('RC.8 Designer drag/drop contract: FAIL')
    for name in failed:
        print(' - ' + name)
    sys.exit(1)

print('RC.8 Designer drag/drop contract: PASS')
