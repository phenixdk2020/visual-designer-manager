from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]


def replace_once(path: Path, old: str, new: str) -> None:
    text = path.read_text(encoding='utf-8')
    count = text.count(old)
    if count != 1:
        raise SystemExit(f'{path}: expected exactly one match, found {count}: {old[:80]!r}')
    path.write_text(text.replace(old, new, 1), encoding='utf-8')


js = ROOT / 'assets' / 'designer.js'
replace_once(
    js,
    "return {section: 36, container: 24, text: 6, image: 18, button: 6, spacer: 4, divider: 2}[type] || 4;",
    "return {section: 36, container: 24, text: 6, image: 18, button: 6, spacer: 4, divider: 2, events: 60}[type] || 4;",
)
replace_once(
    js,
    "            divider: {x: 0, y: 0, w: 12, h: 2}\n        }[type];",
    "            divider: {x: 0, y: 0, w: 12, h: 2},\n            events: {x: 0, y: 0, w: 12, h: 60}\n        }[type];",
)
replace_once(
    js,
    "            spacer: {},\n            divider: {color: '#d0d0d0', thickness: 1}\n        }[type] || {};",
    "            spacer: {},\n            divider: {color: '#d0d0d0', thickness: 1},\n            events: {count: 6, showPast: false, columns: 3, gap: 20, padding: 18, radius: 6, cardBackground: '#ffffff', textColor: '#222222', headingColor: '#222222', accentColor: '#2f4858', showImage: true, showSummary: true, showFacts: true}\n        }[type] || {};",
)

marker = "        if (node.type === 'divider') {\n            inspector.append(field('Farve', colorControl(node.props.color, value => commitMutation(() => { node.props.color = value; }))));\n            inspector.append(field('Tykkelse', numberInput(node.props.thickness || 1, 1, 20, value => commitMutation(() => { node.props.thickness = value; }))));\n        }"
events_inspector = """        if (node.type === 'events') {
            inspector.append(field('Antal events', numberInput(node.props.count || 6, 1, 24, value => commitMutation(() => { node.props.count = value; }))));
            inspector.append(field('Vis tidligere events', checkboxInput(Boolean(node.props.showPast), value => commitMutation(() => { node.props.showPast = value; }))));
            inspector.append(field('Kolonner', selectInput([
                ['1', '1 kolonne'],
                ['2', '2 kolonner'],
                ['3', '3 kolonner'],
                ['4', '4 kolonner']
            ], String(node.props.columns || 3), value => commitMutation(() => { node.props.columns = Number.parseInt(value, 10); }))));
            inspector.append(field('Afstand', numberInput(node.props.gap ?? 20, 0, 80, value => commitMutation(() => { node.props.gap = value; }))));
            inspector.append(field('Kort-padding', numberInput(node.props.padding ?? 18, 0, 80, value => commitMutation(() => { node.props.padding = value; }))));
            inspector.append(field('Hjørneradius', numberInput(node.props.radius ?? 6, 0, 60, value => commitMutation(() => { node.props.radius = value; }))));
            inspector.append(field('Kortbaggrund', colorControl(node.props.cardBackground || '#ffffff', value => commitMutation(() => { node.props.cardBackground = value; }))));
            inspector.append(field('Tekstfarve', colorControl(node.props.textColor || '#222222', value => commitMutation(() => { node.props.textColor = value; }))));
            inspector.append(field('Overskriftsfarve', colorControl(node.props.headingColor || '#222222', value => commitMutation(() => { node.props.headingColor = value; }))));
            inspector.append(field('Accentfarve', colorControl(node.props.accentColor || '#2f4858', value => commitMutation(() => { node.props.accentColor = value; }))));
            inspector.append(field('Vis billede', checkboxInput(node.props.showImage !== false, value => commitMutation(() => { node.props.showImage = value; }))));
            inspector.append(field('Vis kort beskrivelse', checkboxInput(node.props.showSummary !== false, value => commitMutation(() => { node.props.showSummary = value; }))));
            inspector.append(field('Vis eventfakta', checkboxInput(node.props.showFacts !== false, value => commitMutation(() => { node.props.showFacts = value; }))));
        }

""" + marker
replace_once(js, marker, events_inspector)

for relative in ['src/Admin/DesignerController.php', 'src/Admin/TemplateDesignerController.php']:
    path = ROOT / relative
    replace_once(
        path,
        "            'divider' => 'Skillelinje',\n",
        "            'divider' => 'Skillelinje',\n            'events' => 'Events',\n",
    )

print('Applied alpha.6 Events Designer migration')
