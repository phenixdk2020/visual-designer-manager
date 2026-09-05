from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]

plugin = (ROOT / 'visual-designer-manager.php').read_text(encoding='utf-8')
migrator = (ROOT / 'src' / 'Transfer' / 'SchemaOneMigrator.php').read_text(encoding='utf-8')
controller = (ROOT / 'src' / 'Admin' / 'TransferController.php').read_text(encoding='utf-8')
package = (ROOT / 'src' / 'Transfer' / 'PortablePackage.php').read_text(encoding='utf-8')
importer = (ROOT / 'src' / 'Transfer' / 'PortableImporter.php').read_text(encoding='utf-8')
schema = (ROOT / 'src' / 'Model' / 'NodeSchema.php').read_text(encoding='utf-8')
renderer = (ROOT / 'src' / 'Frontend' / 'Renderer.php').read_text(encoding='utf-8')
designer = (ROOT / 'assets' / 'designer.js').read_text(encoding='utf-8')
css = (ROOT / 'assets' / 'frontend.css').read_text(encoding='utf-8')
compact_schema = re.sub(r'\s+', '', schema)

version_match = re.search(r'Version:\s*2\.0\.0(?:-(alpha|beta|rc)\.(\d+))?', plugin)
version_ok = False
if version_match:
    phase = version_match.group(1)
    number = int(version_match.group(2) or 0)
    version_ok = phase is None or (phase == 'rc' and number >= 1)

checks = {
    'runtime version rc.1 or newer': version_ok,
    'isolated schema one migrator': 'final class SchemaOneMigrator' in migrator and "private const SCHEMA = '1.0';" in migrator,
    'required previous package records': all(token in migrator for token in (
        "'pages/pages.json'", "'templates/templates.json'", "'modules/modules.json'",
        "'modules/custom-fields.json'", "'navigation/navigation.json'", "'media/media-index.json'", "'migration/legacy-map.json'",
    )),
    'path duplicate and symlink defense': all(token in migrator for token in (
        'PortablePackage::safePath($name)', 'Duplicate schema 1.0 ZIP path rejected',
        'getExternalAttributesIndex', 'Symbolic links are not allowed', 'Unlisted schema 1.0 ZIP entry rejected',
    )),
    'per file size and sha validation': all(token in migrator for token in (
        'Schema 1.0 size mismatch', 'self::hashEntry($zip, $path, $size)', 'Schema 1.0 SHA-256 mismatch',
    )),
    'previous package content digest': all(token in migrator for token in (
        'wp_json_encode($sorted, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)',
        "hash('sha256', $digestJson)", 'Schema 1.0 content digest is invalid',
    )),
    'convert then native validate': 'PortablePackage::inspect($target)' in migrator and 'PortablePackage::SCHEMA_VERSION' in migrator,
    'native import path remains canonical': 'PortableImporter::import($path, get_current_user_id())' in controller and 'SchemaOneMigrator' not in importer,
    'preflight conversion isolation': all(token in controller for token in (
        'SchemaOneMigrator::convert($stagedPath)', '@unlink($stagedPath);', '$stagedPath = $convertedPath;',
        "'migration' => $migration", 'PortablePackage::inspect($path);',
    )),
    '120 to 12 geometry conversion': all(token in migrator for token in (
        '$factor = $units / 12;', "round(((int) ($raw['x'] ?? 0)) / $factor)",
        "round(((int) ($raw['w'] ?? $units)) / $factor)", 'min(12 - $x, $w)',
    )),
    'canonical node mappings': all(token in migrator for token in (
        "'contactform' => NodeSchema::CONTACT_FORM", "'membershipform' => NodeSchema::MEMBERSHIP_FORM",
        "'menu' => NodeSchema::NAVIGATION", "'table', 'datalist', 'icon', 'badge' => NodeSchema::TEXT",
    )),
    'native module conversions': all(token in migrator for token in (
        "$kind === 'events'", "$kind === 'vehicles'", "$kind === 'galleries'",
        'self::injectModuleNode($layout, $module)', "[NodeSchema::EVENTS, NodeSchema::VEHICLES, NodeSchema::GALLERIES]",
    )),
    'native detail routes replace old detail pages': all(token in migrator for token in (
        "['eventfacts', 'eventfield', 'eventimage', 'eventvalue', 'gallerydetail', 'vehicledetail']",
        'V2 renders detail views natively',
    )),
    'media copied with manifest hash': all(token in migrator for token in (
        "'archivePath' => $archive", "'size' => (int) $manifest['size']", "'sha256' => (string) $manifest['sha256']",
        'self::copyEntryToTemp(', 'Schema 1.0 media changed during conversion',
    )),
    'migration warnings visible': 'Migrationsbemærkninger' in controller and '$migrationWarnings' in controller,
    'native validator stays schema two': "public const SCHEMA_VERSION = '2.0';" in package,
    'text migration preserves presentation': all(token in migrator for token in (
        "'fontWeight' => self::fontWeight($props['fontWeight'] ?? 400)", "'lineHeight' => max(0.8, min(3.0",
        "'align' => in_array((string) ($props['align']", "'verticalAlign' => in_array((string) ($props['verticalAlign']",
        "'background' => !empty($props['backgroundTransparent']) ? 'transparent'", "'padding' => max(0, min(120", "'radius' => max(0, min(80",
    )),
    'text schema retains migrated presentation': all(token in compact_schema for token in (
        "'fontWeight'=>400", "'lineHeight'=>1.5", "'align'=>'left'", "'verticalAlign'=>'top'", "'background'=>'transparent'",
        "'fontWeight'=>self::weight", "'lineHeight'=>self::float($props,'lineHeight'",
    )),
    'text canonical renderer uses presentation': all(token in renderer for token in (
        '--vdm-text-font-weight:', '--vdm-text-line-height:', '--vdm-text-align:', '--vdm-text-vertical-align:',
        '--vdm-text-background:', '--vdm-text-padding:', '--vdm-text-radius:',
    )),
    'text canonical css uses presentation': all(token in css for token in (
        'font-weight:var(--vdm-text-font-weight,400)', 'line-height:var(--vdm-text-line-height,1.5)',
        'text-align:var(--vdm-text-align,left)', 'align-items:var(--vdm-text-vertical-align,flex-start)',
        'background:var(--vdm-text-background,transparent)',
    )),
    'text inspector exposes presentation': all(token in designer for token in (
        "field('Skriftvægt'", "field('Linjehøjde ×100'", "field('Justering'", "field('Lodret placering'",
    )),
    'container migration preserves border and radius': all(token in migrator for token in (
        "'borderWidth' => max(0, min(20", "'borderColor' => self::color",
    )) and all(token in compact_schema for token in (
        "'borderWidth'=>0", "'borderColor'=>'#d0d0d0'",
    )) and all(token in renderer for token in (
        '--vdm-border-width:', '--vdm-border-color:', '--vdm-radius:',
    )) and 'border:var(--vdm-border-width,0) solid var(--vdm-border-color,#d0d0d0)' in css,
    'button migration preserves border': all(token in migrator for token in (
        "'borderWidth' => max(0, min(20", "'borderColor' => self::color((string) ($props['borderColor']",
    )) and all(token in renderer for token in (
        '--vdm-button-border-width:', '--vdm-button-border-color:',
    )) and 'border:var(--vdm-button-border-width,0) solid var(--vdm-button-border-color,#2f4858)' in css,
}

failed = [name for name, ok in checks.items() if not ok]
if failed:
    print('RC.1 schema 1.0 migration contract: FAIL')
    for name in failed:
        print(' - ' + name)
    sys.exit(1)

print('RC.1 schema 1.0 migration contract: PASS')
