from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
plugin = (ROOT / 'visual-designer-manager.php').read_text(encoding='utf-8')
workflow = (ROOT / '.github' / 'workflows' / 'package.yml').read_text(encoding='utf-8')

header = re.search(r'^\s*\*\s*Version:\s*([^\s]+)\s*$', plugin, re.MULTILINE)
runtime = re.search(r"define\('VDM_VERSION',\s*'([^']+)'\);", plugin)
version_ok = bool(
    header
    and runtime
    and header.group(1) == runtime.group(1)
    and re.fullmatch(r'\d+\.\d+\.\d+(?:-(?:alpha|beta|rc)\.\d+)?', header.group(1))
)

checks = {
    'plugin version metadata agrees': version_ok,
    'package workflow has push and manual entry points': 'push:' in workflow and 'workflow_dispatch:' in workflow,
    'package root is canonical': "mkdir -p build/visual-designer-manager" in workflow and "name.startswith('visual-designer-manager/')" in workflow,
    'runtime allowlist is explicit': all(token in workflow for token in (
        'cp visual-designer-manager.php README.md CHANGELOG.md build/visual-designer-manager/',
        'cp -R src assets templates build/visual-designer-manager/',
    )),
    'development paths are rejected': all(token in workflow for token in (
        "{'.git', '.github', 'tests', 'docs'}",
        'Development-only path leaked into package',
    )),
    'packaged php and javascript are syntax checked': "find \"$root\" -type f -name '*.php'" in workflow and "node --check \"$file\"" in workflow,
    'zip creation is stable and sorted': all(token in workflow for token in (
        'touch -t 202001010000.00',
        'LC_ALL=C find visual-designer-manager -type f -print | sort | zip -X -q',
    )),
    'finished zip is path checked': all(token in workflow for token in (
        "len(names) != len({name.casefold() for name in names})",
        "if '..' in parts or name.startswith('/') or '\\\\' in name",
        'Unexpected ZIP root',
    )),
    'package sha is generated and verified': 'sha256sum "visual-designer-manager-v${VERSION}.zip"' in workflow and 'Package SHA-256 verification failed.' in workflow,
    'artifact includes version and source commit': 'steps.meta.outputs.version' in workflow and 'steps.meta.outputs.short_sha' in workflow,
    'artifact retention is bounded': 'retention-days: 30' in workflow,
}

failed = [name for name, ok in checks.items() if not ok]
if failed:
    print('Install package contract: FAIL')
    for name in failed:
        print(' - ' + name)
    sys.exit(1)

print('Install package contract: PASS')
