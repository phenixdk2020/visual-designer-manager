from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def text(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')


def require(path: str, *needles: str) -> None:
    value = text(path)
    missing = [needle for needle in needles if needle not in value]
    if missing:
        raise SystemExit(f'{path}: missing contract markers: {missing}')


require(
    'visual-designer-manager.php',
    'Version: 2.0.0-rc.2',
    "define('VDM_VERSION', '2.0.0-rc.2')",
)

require(
    'src/Admin/ParityController.php',
    "self::VEHICLE_FIELDS_SLUG => 'Køretøjsfelter'",
    "self::EVENT_FIELDS_SLUG => 'Eventfelter'",
    "self::PAGES_SLUG => 'Sider'",
    "self::BACKUP_SLUG => 'Backup'",
    "self::UPDATES_SLUG => 'Opdateringer'",
    "self::LOG_SLUG => 'Log'",
    "self::MANUAL_SLUG => 'Brugermanual'",
    "DesignerController::MENU_SLUG => 'Visual Designer'",
    "TransferController::MENU_SLUG => 'Eksport'",
    "NavigationController::MENU_SLUG => 'Menu'",
    "SiteDesignController::MENU_SLUG => 'Tema'",
)

parity = text('src/Admin/ParityController.php')
if ': never' in parity:
    raise SystemExit('RC.2 admin parity must remain compatible with PHP 8.0.')

require(
    'src/Fields/VehicleFieldRegistry.php',
    "public const OPTION = 'vdm_vehicle_fields_v2'",
    "'manufacturer'",
    "'model'",
    "'year'",
)
require(
    'src/Fields/EventFieldRegistry.php',
    "public const OPTION = 'vdm_event_fields_v2'",
    "'about'",
    "'program'",
    "'practical'",
)

for path in [
    'src/Transfer/PortableExporter.php',
    'src/Transfer/PortableImporter.php',
    'src/Transfer/SchemaOneMigrator.php',
]:
    require(path, 'settings/custom-fields.json')

require(
    'src/Model/NodeSchema.php',
    "'fineX' => $fineX",
    "'fineW' => $fineW",
)
require(
    'src/Transfer/SchemaOneMigrator.php',
    "'fineX' => $fineX",
    "'fineW' => $fineW",
    "'eventlist' => NodeSchema::EVENTS",
    "'vehiclelist' => NodeSchema::VEHICLES",
    "'gallerylist' => NodeSchema::GALLERIES",
    "$design['contentPadding'] = 0",
)
require(
    'src/Frontend/Renderer.php',
    "'-fx:'",
    "'-fw:'",
)
require(
    'assets/frontend.css',
    'margin-left:calc(var(--vdm-fx) * 100% / 120)',
    'width:calc(var(--vdm-fw) * 100% / 120)',
)
require(
    'assets/designer.js',
    "geometry.fineX = geometry.x * 10",
    "geometry.fineW = geometry.w * 10",
)

require(
    'src/Core/Plugin.php',
    'ParityController::register();',
)
require(
    'src/Events/EventRepository.php',
    "META_CUSTOM_FIELDS = '_vdm_event_custom_fields_v2'",
)
require(
    'src/Vehicles/VehicleRepository.php',
    "META_CUSTOM_FIELDS = '_vdm_vehicle_custom_fields_v2'",
)

print('RC.2 parity contract OK')
