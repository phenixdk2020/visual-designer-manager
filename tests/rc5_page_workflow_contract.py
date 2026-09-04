from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')


plugin = read('visual-designer-manager.php')
designer_php = read('src/Admin/DesignerController.php')
parity_php = read('src/Admin/ParityController.php')
plugin_boot = read('src/Core/Plugin.php')
frontend = read('src/Frontend/FrontendController.php')
shell = read('templates/page-shell.php')
preview_repo = read('src/Storage/PreviewRepository.php')
workflow_admin = read('src/Admin/PageWorkflowController.php')
workflow_rest = read('src/Http/PageWorkflowRestController.php')
designer_js = read('assets/designer.js')
workflow_js = read('assets/page-workflow.js')

header_version = re.search(r'Version: 2\.0\.0-rc\.(\d+)', plugin)
constant_version = re.search(r"define\('VDM_VERSION', '2\.0\.0-rc\.(\d+)'\);", plugin)
assert header_version is not None and int(header_version.group(1)) >= 5
assert constant_version is not None and int(constant_version.group(1)) >= 5

assert 'PageWorkflowController::register();' in plugin_boot
assert 'PageWorkflowRestController::register();' in plugin_boot
assert 'PageWorkflowController::renderCreateForm($pages);' in parity_php
assert 'vdm_create_designer_page' in workflow_admin
assert 'Opret og åbn Visual Designer' in workflow_admin
assert "'post_type' => 'page'" in workflow_admin
assert "'post_status' => $status" in workflow_admin

for route in [
    "/pages/(?P<id>\\d+)/preview",
    "/pages/(?P<id>\\d+)/history",
    "/pages/(?P<id>\\d+)/versions/(?P<version>\\d+)/preview",
    "/pages/(?P<id>\\d+)/versions/(?P<version>\\d+)/restore",
    "/pages/(?P<id>\\d+)/versions/(?P<version>\\d+)/copy",
]:
    assert route in workflow_rest

assert 'PreviewRepository::stage' in workflow_rest
assert 'LayoutRepository::save($postId, $entry[\'document\']' in workflow_rest
assert "'post_status' => 'draft'" in workflow_rest
assert "'designerUrl'" in workflow_rest

assert "private const PREFIX = 'vdm_preview_'" in preview_repo
assert "'userId' => $userId" in preview_repo
assert "get_current_user_id()" in preview_repo
assert 'LayoutDocument::normalize' in preview_repo
assert 'set_transient' in preview_repo
assert 'nocache_headers' in preview_repo

assert 'PreviewRepository' in frontend
assert 'PreviewRepository' in shell
assert 'Ikke-gemt forhåndsvisning' in frontend or 'Ikke-gemt forhåndsvisning' in shell

assert "VDM_URL . 'assets/page-workflow.js'" in designer_php
assert "VDM_URL . 'assets/page-workflow.css'" in designer_php
assert "'viewUrl' =>" in designer_php
assert 'Gem som ny version' in designer_php

assert 'window.VDMDesignerRuntime' in designer_js
for api_member in ['getDocument', 'getSelectedId', 'replaceDocument', 'save']:
    assert api_member in designer_js

for label in ['Forhåndsvis', 'Gem & vis', 'Kopiér', 'Indsæt', 'Duplikér', 'Gemte versioner', 'Gendan original', 'Opret kopi']:
    assert label in workflow_js
for key in ["key === 'c'", "key === 'v'", "key === 'd'"]:
    assert key in workflow_js
assert 'sessionStorage' in workflow_js
assert '/history' in workflow_js
assert '/preview' in workflow_js

qa = read('.github/workflows/qa.yml')
assert 'RC.5 V1 page workflow contract' in qa
assert 'python3 tests/rc5_page_workflow_contract.py' in qa
assert 'node --check assets/page-workflow.js' in qa

print('RC.5 V1 page workflow contract: PASS')
