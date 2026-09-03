from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]

plugin = (ROOT / 'visual-designer-manager.php').read_text(encoding='utf-8')
core = (ROOT / 'src' / 'Core' / 'Plugin.php').read_text(encoding='utf-8')
repository = (ROOT / 'src' / 'Vehicles' / 'VehicleRepository.php').read_text(encoding='utf-8')
admin = (ROOT / 'src' / 'Admin' / 'VehicleController.php').read_text(encoding='utf-8')
schema = (ROOT / 'src' / 'Model' / 'NodeSchema.php').read_text(encoding='utf-8')
renderer = (ROOT / 'src' / 'Frontend' / 'Renderer.php').read_text(encoding='utf-8')
vehicle_renderer = (ROOT / 'src' / 'Frontend' / 'VehicleRenderer.php').read_text(encoding='utf-8')
vehicle_frontend = (ROOT / 'src' / 'Frontend' / 'VehicleFrontendController.php').read_text(encoding='utf-8')
designer = (ROOT / 'assets' / 'designer.js').read_text(encoding='utf-8')
page_designer = (ROOT / 'src' / 'Admin' / 'DesignerController.php').read_text(encoding='utf-8')
template_designer = (ROOT / 'src' / 'Admin' / 'TemplateDesignerController.php').read_text(encoding='utf-8')
vehicle_shell = (ROOT / 'templates' / 'single-vehicle.php').read_text(encoding='utf-8')
vehicle_admin_js = (ROOT / 'assets' / 'vehicle-admin.js').read_text(encoding='utf-8')
css = (ROOT / 'assets' / 'frontend.css').read_text(encoding='utf-8')

version_match = re.search(r'Version:\s*2\.0\.0(?:-(alpha|beta|rc)\.(\d+))?', plugin)
version_ok = bool(version_match)
if version_match and version_match.group(1) == 'alpha':
    version_ok = int(version_match.group(2) or 0) >= 7

checks = {
    'runtime version alpha.7 or newer': version_ok,
    'vehicle post type and VDM meta': "public const POST_TYPE = 'vdm_vehicle';" in repository and "'_vdm_vehicle_manufacturer'" in repository and "'_vdm_vehicle_specs'" in repository,
    'vehicle admin registration': 'register_post_type(VehicleRepository::POST_TYPE' in admin and 'vdm_save_vehicle' in admin and 'Køretøjsoplysninger' in admin,
    'vehicle core fields': all(token in admin for token in ('Type', 'Producent', 'Model', 'Årgang', 'Oprindelsesland', 'Status', 'Motor', 'Motorydelse', 'Vægt', 'Længde', 'Bredde', 'Højde', 'Besætning')),
    'flexible technical fields': 'Ekstra tekniske felter' in admin and 'vdm_vehicle[specs]' in admin and 'vdm-add-vehicle-spec' in admin and 'normalizeSpecs' in repository and 'vdm-remove-vehicle-spec' in vehicle_admin_js,
    'vehicle node schema': "public const VEHICLES = 'vehicles';" in schema and "self::VEHICLES => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 60]" in schema and "'showFacts' => true" in schema,
    'canonical vehicle renderer': 'NodeSchema::VEHICLES' in renderer and 'VehicleRenderer::renderList' in renderer,
    'vehicle list rendering': 'VehicleRepository::query' in vehicle_renderer and 'vdm-vehicles-grid' in vehicle_renderer and 'Læs mere' in vehicle_renderer,
    'vehicle detail geometry': 'vdm-vehicle-detail-grid' in vehicle_renderer and 'vdm-vehicle-detail-media' in vehicle_renderer and 'vdm-vehicle-detail-specs' in vehicle_renderer,
    'vehicle technical facts': all(token in vehicle_renderer for token in ('Producent', 'Model', 'Årgang', 'Oprindelsesland', 'Motor', 'Motorydelse', 'Vægt', 'Længde', 'Bredde', 'Højde', 'Besætning')),
    'designer vehicle defaults': 'vehicles: 60' in designer and "vehicles: {x: 0, y: 0, w: 12, h: 60}" in designer and "node.type === 'vehicles'" in designer,
    'designer vehicle inspector': all(token in designer for token in ('Antal køretøjer', 'Kortbaggrund', 'Overskriftsfarve', 'Accentfarve', 'Vis tekniske data')),
    'vehicle palettes': "'vehicles' => 'Køretøjer'" in page_designer and "'vehicles' => 'Køretøjer'" in template_designer,
    'vehicle shell routing': "is_singular(VehicleRepository::POST_TYPE)" in vehicle_frontend and "templates/single-vehicle.php" in vehicle_frontend,
    'vehicle shell canonical': 'VehicleRenderer::renderDetail($postId)' in vehicle_shell and 'TemplateRepository::HEADER' in vehicle_shell and 'TemplateRepository::FOOTER' in vehicle_shell and 'wp_head();' in vehicle_shell and 'wp_footer();' in vehicle_shell,
    'vehicle responsive CSS': all(token in css for token in ('.vdm-vehicles-grid', '.vdm-vehicle-card', '.vdm-vehicle-detail-grid', 'grid-template-columns:1fr')),
    'vehicles booted': 'VehicleController::register();' in core and 'VehicleFrontendController::register();' in core and 'VehicleController::postType();' in core,
}

failed = [name for name, ok in checks.items() if not ok]
if failed:
    print('Alpha 7 Vehicles contract: FAIL')
    for name in failed:
        print(' - ' + name)
    sys.exit(1)

print('Alpha 7 Vehicles contract: PASS')
