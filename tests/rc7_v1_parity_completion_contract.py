from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')


def has(path: str, *tokens: str) -> bool:
    value = read(path)
    return all(token in value for token in tokens)

plugin = read('visual-designer-manager.php')
header_match = re.search(r'Version:\s*2\.0\.0-rc\.(\d+)', plugin)
runtime_match = re.search(r"define\('VDM_VERSION',\s*'2\.0\.0-rc\.(\d+)'\);", plugin)
version_ok = bool(
    header_match
    and runtime_match
    and int(header_match.group(1)) >= 7
    and header_match.group(1) == runtime_match.group(1)
)

schema = read('src/Model/NodeSchema.php')
hierarchy = read('src/Model/Hierarchy.php')
core = read('src/Core/Plugin.php')
nav = read('src/Admin/NavigationController.php')
parity_js = read('assets/designer-parity.js')
form = read('src/Forms/FormSubmissionController.php')

checks = {
    'runtime version rc.7 or newer': version_ok,
    'named Header Footer templates': has(
        'src/Storage/TemplateRepository.php',
        'REGISTRY_OPTION', 'defaultId(', 'create(', 'duplicate(', 'rename(',
        'setActive(', 'setDefault(', 'saveTemplate(', 'historyTemplate('
    ),
    'per-page Header Footer resolver': has(
        'src/Storage/TemplateAssignmentRepository.php',
        "return 'auto';", "return 'none';", 'resolveId(', 'resolveDocument(',
        'TemplateRepository::defaultId($slot)'
    ),
    'template assignment UI': has(
        'src/Admin/TemplateAssignmentController.php',
        'Automatisk / standard', 'value="none"', 'TemplateAssignmentRepository::saveChoice',
        'Resolver: sidevalg → website-standard → tom fallback.'
    ),
    'full VDM navigation manager': all(token in nav for token in (
        'wp_get_nav_menus', 'wp_get_nav_menu_items', 'wp_update_nav_menu_item',
        'MAX_HISTORY = 30', 'restoreSnapshot', 'saveLocations', 'Eksternt link', 'Overskrift'
    )),
    'generic V1 parity nodes': all(token in schema for token in (
        "public const LINK = 'link';", "public const ICON = 'icon';",
        "public const BADGE = 'badge';", "public const DATA_LIST = 'data-list';",
        "public const TABLE = 'table';"
    )),
    'detail parity nodes': all(token in schema for token in (
        "public const EVENT_VALUE = 'event-value';", "public const EVENT_IMAGE = 'event-image';",
        "public const EVENT_FIELD = 'event-field';", "public const EVENT_FACTS = 'event-facts';",
        "public const VEHICLE_DETAIL = 'vehicle-detail';", "public const GALLERY_DETAIL = 'gallery-detail';"
    )),
    'floating button model': all(token in schema for token in (
        "'mode'=>'normal'", "'zIndex'=>10", "['normal','floating']", "'zIndex'=>self::int"
    )) and 'Only sections and floating buttons may exist at document root.' in hierarchy,
    'V1 placement commands': all(token in parity_js for token in (
        'Over', 'Under', 'Venstre', 'Højre', 'Ind i'
    )),
    'page lifecycle parity': has(
        'src/Admin/PageLifecycleController.php',
        'duplicate', 'publish', 'draft', 'trash', 'LayoutRepository'
    ),
    'manual parity': has(
        'src/Admin/ManualController.php',
        'docx', 'wp_insert_post', 'Brugermanual'
    ),
    'form recipient and receipt parity': all(token in form for token in (
        "$props['recipient']", 'vdm_form_recipient', 'sendReceipt', 'receiptSubject', "wp_mail($fields['email']"
    )),
    'diagnostics parity': has(
        'src/Admin/DiagnosticsController.php',
        'post_id', 'DiagnosticStore::supportUrl', 'Kopiér diagnose-link', 'clearForPost'
    ) and has(
        'src/Diagnostics/DiagnosticStore.php',
        'clearForPost', 'supportUrl', "'post_id'"
    ),
    'parity renderer and CSS': has(
        'src/Frontend/Renderer.php',
        'NodeSchema::LINK', 'NodeSchema::ICON', 'NodeSchema::BADGE', 'NodeSchema::DATA_LIST', 'NodeSchema::TABLE',
        'NodeSchema::EVENT_VALUE', 'NodeSchema::VEHICLE_DETAIL', 'NodeSchema::GALLERY_DETAIL'
    ) and has('assets/parity.css', '.vdm-node--link', '.vdm-table', '.vdm-node--event-value'),
    'new controllers are booted': all(token in core for token in (
        'PageLifecycleController::register();', 'DesignerParityController::register();',
        'DiagnosticsController::register();', 'TemplateAssignmentController::register();', 'ManualController::register();'
    )),
}

failed = [name for name, ok in checks.items() if not ok]
if failed:
    print('RC.7 V1 parity completion contract: FAIL')
    for name in failed:
        print(' - ' + name)
    sys.exit(1)

print('RC.7 V1 parity completion contract: PASS')
