from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]

plugin = (ROOT / 'visual-designer-manager.php').read_text(encoding='utf-8')
designer = (ROOT / 'assets' / 'designer.js').read_text(encoding='utf-8')
css = (ROOT / 'assets' / 'designer.css').read_text(encoding='utf-8')
schema = (ROOT / 'src' / 'Model' / 'NodeSchema.php').read_text(encoding='utf-8')
controller = (ROOT / 'src' / 'Admin' / 'DesignerController.php').read_text(encoding='utf-8')

version_match = re.search(r'Version:\s*2\.0\.0(?:-(alpha|beta|rc)\.(\d+))?', plugin)
version_ok = bool(version_match)
if version_match and version_match.group(1) == 'alpha':
    version_ok = int(version_match.group(2) or 0) >= 3

compact_schema = re.sub(r'\s+', '', schema)

checks = {
    'runtime version alpha.3 or newer': version_ok,
    'drag pointer interaction': "function startDrag(" in designer and "handlePointerMove" in designer and "'pointermove'" in designer,
    'resize handles': "function startResize(" in designer and "vdm-resize-handle--se" in css,
    'grid snap': "Math.round((event.clientX - interaction.startClientX) / interaction.metrics.columnWidth)" in designer
        and "Math.round((event.clientY - interaction.startClientY) / interaction.metrics.rowHeight)" in designer,
    'undo redo state': "function undo()" in designer and "function redo()" in designer
        and "id=\"vdm-undo\"" in controller and "id=\"vdm-redo\"" in controller,
    'keyboard shortcuts': "event.key.toLowerCase() === 'z'" in designer and "ArrowLeft" in designer and "Ctrl+S" in controller,
    'auto height runtime': "function applyAutoHeight(" in designer and "minHeightRows" in designer and "autoHeight" in designer,
    'auto height schema': "'autoHeight'=>true" in compact_schema and "'minHeightRows'=>36" in compact_schema
        and "array_key_exists('autoHeight',$props)" in compact_schema,
    'bounded history': "HISTORY_LIMIT = 80" in designer,
}

failed = [name for name, ok in checks.items() if not ok]

if failed:
    print('Alpha 3 interaction contract: FAIL')
    for name in failed:
        print(' - ' + name)
    sys.exit(1)

print('Alpha 3 interaction contract: PASS')
