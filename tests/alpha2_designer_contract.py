from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

required = [
    'src/Model/NodeSchema.php',
    'src/Model/Hierarchy.php',
    'src/Storage/LayoutRepository.php',
    'src/Frontend/Renderer.php',
    'src/Frontend/FrontendController.php',
    'src/Admin/DesignerController.php',
    'assets/frontend.css',
    'assets/designer.css',
    'assets/designer.js',
]
for relative in required:
    assert (ROOT / relative).is_file(), relative

store = (ROOT / 'src/Storage/LayoutRepository.php').read_text(encoding='utf-8')
assert "'_vdm_layout_v2'" in store
assert "'_vdm_layout_version_v2'" in store
assert "'_vdm_layout_history_v2'" in store

schema = (ROOT / 'src/Model/NodeSchema.php').read_text(encoding='utf-8')
for node_type in ('section', 'container', 'text', 'image', 'button', 'spacer', 'divider'):
    assert f"'{node_type}'" in schema, node_type

hierarchy = (ROOT / 'src/Model/Hierarchy.php').read_text(encoding='utf-8')
assert 'Only sections may exist at document root.' in hierarchy
assert 'VDM hierarchy contains a cycle.' in hierarchy

renderer = (ROOT / 'src/Frontend/Renderer.php').read_text(encoding='utf-8')
assert 'data-vdm-node-id' in renderer
assert 'Breakpoint::ordered()' in renderer
assert 'wp_kses_post' in renderer

rest = (ROOT / 'src/Http/RestController.php').read_text(encoding='utf-8')
assert "'/layouts/(?P<id>" in rest and "getLayout" in rest and "saveLayout" in rest
assert "'/render'" in rest

js = (ROOT / 'assets/designer.js').read_text(encoding='utf-8')
for token in ('addNode(type)', 'renderPreview()', 'renderInspector()', "method: 'PUT'", 'beforeunload'):
    assert token in js, token

css = (ROOT / 'assets/frontend.css').read_text(encoding='utf-8')
for token in ('grid-auto-rows:8px', 'max-width:1180px', 'max-width:980px', 'max-width:782px'):
    assert token in css, token

print('2.0.0-alpha.2 Designer contract: PASS')
