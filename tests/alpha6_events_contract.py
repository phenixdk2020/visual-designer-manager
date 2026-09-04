from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]

plugin = (ROOT / 'visual-designer-manager.php').read_text(encoding='utf-8')
core = (ROOT / 'src' / 'Core' / 'Plugin.php').read_text(encoding='utf-8')
repository = (ROOT / 'src' / 'Events' / 'EventRepository.php').read_text(encoding='utf-8')
admin = (ROOT / 'src' / 'Admin' / 'EventController.php').read_text(encoding='utf-8')
schema = (ROOT / 'src' / 'Model' / 'NodeSchema.php').read_text(encoding='utf-8')
renderer = (ROOT / 'src' / 'Frontend' / 'Renderer.php').read_text(encoding='utf-8')
event_renderer = (ROOT / 'src' / 'Frontend' / 'EventRenderer.php').read_text(encoding='utf-8')
event_frontend = (ROOT / 'src' / 'Frontend' / 'EventFrontendController.php').read_text(encoding='utf-8')
designer = (ROOT / 'assets' / 'designer.js').read_text(encoding='utf-8')
page_designer = (ROOT / 'src' / 'Admin' / 'DesignerController.php').read_text(encoding='utf-8')
event_shell = (ROOT / 'templates' / 'single-event.php').read_text(encoding='utf-8')
css = (ROOT / 'assets' / 'frontend.css').read_text(encoding='utf-8')

version_match = re.search(r'Version:\s*2\.0\.0(?:-(alpha|beta|rc)\.(\d+))?', plugin)
version_ok = bool(version_match)
if version_match and version_match.group(1) == 'alpha':
    version_ok = int(version_match.group(2) or 0) >= 6
compact_schema = re.sub(r'\s+', '', schema)

checks = {
    'runtime version alpha.6 or newer': version_ok,
    'event post type and VDM meta': "public const POST_TYPE = 'vdm_event';" in repository and "'_vdm_event_start_date'" in repository and "'_vdm_event_contact'" in repository,
    'event admin registration': 'register_post_type(EventRepository::POST_TYPE' in admin and 'vdm_save_event' in admin and 'Eventoplysninger' in admin,
    'event fields': all(token in admin for token in ('Dato', 'Starttid', 'Sluttid', 'Sted', 'Adresse', 'Kontakt', 'Kort beskrivelse')),
    'event node schema': "publicconstEVENTS='events';" in compact_schema and "self::EVENTS,self::VEHICLES,self::GALLERIES=>['x'=>0,'y'=>0,'w'=>12,'h'=>60]" in compact_schema and "'showFacts'=>true" in compact_schema,
    'canonical event renderer': 'NodeSchema::EVENTS' in renderer and 'EventRenderer::renderList' in renderer,
    'event fact ribbon': all(token in event_renderer for token in ("$items['Dato']", "$items['Tid']", "$items['Sted']", "$items['Adresse']", "$items['Kontakt']")),
    'event list rendering': 'EventRepository::query' in event_renderer and 'vdm-events-grid' in event_renderer and 'Læs mere' in event_renderer,
    'designer event defaults': 'events: 60' in designer and "events: {x: 0, y: 0, w: 12, h: 60}" in designer and "node.type === 'events'" in designer,
    'designer event inspector': all(token in designer for token in ('Antal events', 'Vis tidligere events', 'Kortbaggrund', 'Overskriftsfarve', 'Accentfarve', 'Vis eventfakta')),
    'events palette': "'events' => 'Events'" in page_designer,
    'event shell routing': "is_singular(EventRepository::POST_TYPE)" in event_frontend and "templates/single-event.php" in event_frontend,
    'event shell canonical': 'EventRenderer::renderDetail($postId)' in event_shell and 'TemplateRepository::HEADER' in event_shell and 'TemplateRepository::FOOTER' in event_shell and 'wp_head();' in event_shell and 'wp_footer();' in event_shell,
    'event CSS': all(token in css for token in ('.vdm-events-grid', '.vdm-event-card', '.vdm-event-facts', '.vdm-event-detail')),
    'events booted': 'EventController::register();' in core and 'EventFrontendController::register();' in core,
}

failed = [name for name, ok in checks.items() if not ok]
if failed:
    print('Alpha 6 Events contract: FAIL')
    for name in failed:
        print(' - ' + name)
    sys.exit(1)

print('Alpha 6 Events contract: PASS')
