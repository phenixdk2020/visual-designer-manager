from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
path = ROOT / 'assets' / 'designer.js'
text = path.read_text(encoding='utf-8')

replacements = [
    (
        "    if (!canvas || !inspector || !saveButton || !config.pageId) return;\n",
        "    const hasDesignerTarget = Number.parseInt(config.pageId || '0', 10) > 0 || Boolean(config.templateSlot) || Boolean(config.ready);\n    if (!canvas || !inspector || !saveButton || !hasDesignerTarget) return;\n",
    ),
    (
        "            const response = await fetch(config.restBase + '/render', {\n",
        "            const renderUrl = config.renderUrl || (config.restBase + '/render');\n            const response = await fetch(renderUrl, {\n",
    ),
    (
        "            const response = await fetch(config.restBase + '/layouts/' + config.pageId, {\n",
        "            const saveUrl = config.saveUrl || (config.restBase + '/layouts/' + config.pageId);\n            const response = await fetch(saveUrl, {\n",
    ),
]

for old, new in replacements:
    count = text.count(old)
    if count != 1:
        raise SystemExit(f'Expected exactly one match, found {count}: {old!r}')
    text = text.replace(old, new, 1)

path.write_text(text, encoding='utf-8')
print('Template Designer runtime endpoints fixed')
