from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]

migrator = (ROOT / 'src' / 'Transfer' / 'SchemaOneMigrator.php').read_text(encoding='utf-8')
schema = (ROOT / 'src' / 'Model' / 'NodeSchema.php').read_text(encoding='utf-8')
renderer = (ROOT / 'src' / 'Frontend' / 'Renderer.php').read_text(encoding='utf-8')
form_renderer = (ROOT / 'src' / 'Frontend' / 'FormRenderer.php').read_text(encoding='utf-8')
designer = (ROOT / 'assets' / 'designer.js').read_text(encoding='utf-8')
css = (ROOT / 'assets' / 'frontend.css').read_text(encoding='utf-8')
compact_schema = re.sub(r'\s+', '', schema)

checks = {
    'legacy responsive inheritDesktop uses desktop geometry': all(token in migrator for token in (
        "$desktop = self::convertDeviceGeometry($desktopRaw, $units",
        "foreach (['laptop','tablet','mobile'] as $breakpoint)",
        "if ($raw === [] || !empty($raw['inheritDesktop']))",
        "$result[$breakpoint] = $desktop;",
    )),
    'legacy forms get safe V2 minimum heights': all(token in migrator for token in (
        "$type === NodeSchema::CONTACT_FORM || $type === NodeSchema::MEMBERSHIP_FORM",
        "$minimumRows = $type === NodeSchema::MEMBERSHIP_FORM ? 128 : 100;",
        "$geometry['h'] = max($minimumRows",
        'self::reconcileContainerHeights($nodes);',
    )),
    'legacy form copy survives migration': all(token in migrator for token in (
        "'heading' => sanitize_text_field",
        "'intro' => sanitize_textarea_field",
        "$props['buttonText'] ??",
    )),
    'V2 form model owns heading and intro': (
        "'heading'=>'Kontaktos'" in compact_schema
        and "'heading'=>'Blivmedlem'" in compact_schema
        and "'intro'=>sanitize_textarea_field" in compact_schema
    ),
    'canonical form renderer outputs heading and intro': all(token in form_renderer for token in (
        'vdm-form-heading', 'vdm-form-intro', "$props['heading']", "$props['intro']",
    )),
    'canonical form styling includes heading and intro': '.vdm-form-heading{' in css and '.vdm-form-intro{' in css,
    'Designer exposes form heading and intro': "field('Overskrift'" in designer and "field('Introduktion'" in designer,
    'legacy image fit original avoids crop': all(token in migrator for token in (
        "$props['fit'] ?? $props['objectFit']", "['contain', 'original']", '$objectFit =',
    )),
    'legacy image alignment survives migration': all(token in migrator for token in (
        "$props['imageAlignX']", "$props['imageAlignY']", "'positionX' => $positionX", "'positionY' => $positionY",
    )),
    'V2 image position normalized': (
        "'positionX'=>'center'" in compact_schema
        and "'positionY'=>'center'" in compact_schema
        and "['left','center','right']" in compact_schema
        and "['top','center','bottom']" in compact_schema
    ),
    'V2 image position rendered': all(token in renderer for token in (
        '--vdm-object-position-x:', '--vdm-object-position-y:',
    )) and 'object-position:var(--vdm-object-position-x,center) var(--vdm-object-position-y,center)' in css,
    'Designer exposes image position': "field('Vandret placering'" in designer and "field('Lodret placering'" in designer,
    'legacy menu gap key is preserved': "$props['menuGap'] ?? $props['gap'] ?? 24" in migrator,
    'legacy buttons preserve box width': "'align' => 'stretch'" in migrator,
    'stale internal page hosts are canonicalized by exported path': all(token in migrator for token in (
        '$pageLinks = self::pageLinkMap($pageRecords, $site);',
        'self::canonicalizeLegacyLinks($model, $pageLinks)',
        'private static function normalizedPagePath',
        'isset($byPath[$key])', "$target = (string) $byPath[$key];",
    )),
    'page-id links are canonicalized without broad external rewriting': all(token in migrator for token in (
        "sanitize_key((string) ($props['linkType'] ?? '')) === 'page'",
        "$pageId = absint($props['pageId'] ?? 0);", 'isset($byId[$pageId])',
    )),
}

failed = [name for name, ok in checks.items() if not ok]
if failed:
    print('RC.1 acceptance preflight contract: FAIL')
    for name in failed:
        print(' - ' + name)
    sys.exit(1)

print('RC.1 acceptance preflight contract: PASS')
