from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]


def replace_once(path: Path, old: str, new: str) -> None:
    text = path.read_text(encoding='utf-8')
    count = text.count(old)
    if count != 1:
        raise SystemExit(f'{path}: expected exactly one match, found {count}: {old[:100]!r}')
    path.write_text(text.replace(old, new, 1), encoding='utf-8')


schema = ROOT / 'src' / 'Model' / 'NodeSchema.php'
replace_once(schema, "    public const VEHICLES = 'vehicles';\n", "    public const VEHICLES = 'vehicles';\n    public const GALLERIES = 'galleries';\n")
replace_once(schema, "            self::VEHICLES,\n        ];", "            self::VEHICLES,\n            self::GALLERIES,\n        ];")
replace_once(schema, "            self::VEHICLES => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 60],\n", "            self::VEHICLES => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 60],\n            self::GALLERIES => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 60],\n")

vehicle_defaults = """            self::VEHICLES => [
                'count' => 12,
                'columns' => 3,
                'gap' => 20,
                'padding' => 18,
                'radius' => 6,
                'cardBackground' => '#ffffff',
                'textColor' => '#222222',
                'headingColor' => '#222222',
                'accentColor' => '#2f4858',
                'showImage' => true,
                'showSummary' => true,
                'showFacts' => true,
            ],
"""
gallery_defaults = vehicle_defaults + """            self::GALLERIES => [
                'count' => 12,
                'columns' => 3,
                'gap' => 20,
                'padding' => 16,
                'radius' => 6,
                'cardBackground' => '#ffffff',
                'textColor' => '#222222',
                'headingColor' => '#222222',
                'accentColor' => '#2f4858',
                'showCover' => true,
                'showSummary' => true,
            ],
"""
replace_once(schema, vehicle_defaults, gallery_defaults)

vehicle_normalize = """        if ($type === self::VEHICLES) {
            return [
                'count' => max(1, min(50, (int) ($props['count'] ?? $defaults['count']))),
                'columns' => max(1, min(4, (int) ($props['columns'] ?? $defaults['columns']))),
                'gap' => max(0, min(80, (int) ($props['gap'] ?? $defaults['gap']))),
                'padding' => max(0, min(80, (int) ($props['padding'] ?? $defaults['padding']))),
                'radius' => max(0, min(60, (int) ($props['radius'] ?? $defaults['radius']))),
                'cardBackground' => self::color((string) ($props['cardBackground'] ?? $defaults['cardBackground']), (string) $defaults['cardBackground']),
                'textColor' => self::color((string) ($props['textColor'] ?? $defaults['textColor']), (string) $defaults['textColor']),
                'headingColor' => self::color((string) ($props['headingColor'] ?? $defaults['headingColor']), (string) $defaults['headingColor']),
                'accentColor' => self::color((string) ($props['accentColor'] ?? $defaults['accentColor']), (string) $defaults['accentColor']),
                'showImage' => !array_key_exists('showImage', $props) || !empty($props['showImage']),
                'showSummary' => !array_key_exists('showSummary', $props) || !empty($props['showSummary']),
                'showFacts' => !array_key_exists('showFacts', $props) || !empty($props['showFacts']),
            ];
        }

"""
gallery_normalize = vehicle_normalize + """        if ($type === self::GALLERIES) {
            return [
                'count' => max(1, min(50, (int) ($props['count'] ?? $defaults['count']))),
                'columns' => max(1, min(4, (int) ($props['columns'] ?? $defaults['columns']))),
                'gap' => max(0, min(80, (int) ($props['gap'] ?? $defaults['gap']))),
                'padding' => max(0, min(80, (int) ($props['padding'] ?? $defaults['padding']))),
                'radius' => max(0, min(60, (int) ($props['radius'] ?? $defaults['radius']))),
                'cardBackground' => self::color((string) ($props['cardBackground'] ?? $defaults['cardBackground']), (string) $defaults['cardBackground']),
                'textColor' => self::color((string) ($props['textColor'] ?? $defaults['textColor']), (string) $defaults['textColor']),
                'headingColor' => self::color((string) ($props['headingColor'] ?? $defaults['headingColor']), (string) $defaults['headingColor']),
                'accentColor' => self::color((string) ($props['accentColor'] ?? $defaults['accentColor']), (string) $defaults['accentColor']),
                'showCover' => !array_key_exists('showCover', $props) || !empty($props['showCover']),
                'showSummary' => !array_key_exists('showSummary', $props) || !empty($props['showSummary']),
            ];
        }

"""
replace_once(schema, vehicle_normalize, gallery_normalize)

renderer = ROOT / 'src' / 'Frontend' / 'Renderer.php'
replace_once(
    renderer,
    "        } elseif ($type === NodeSchema::VEHICLES) {\n            echo VehicleRenderer::renderList(is_array($node['props'] ?? null) ? $node['props'] : []); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped\n        } elseif ($type === NodeSchema::DIVIDER) {",
    "        } elseif ($type === NodeSchema::VEHICLES) {\n            echo VehicleRenderer::renderList(is_array($node['props'] ?? null) ? $node['props'] : []); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped\n        } elseif ($type === NodeSchema::GALLERIES) {\n            echo GalleryRenderer::renderList(is_array($node['props'] ?? null) ? $node['props'] : []); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped\n        } elseif ($type === NodeSchema::DIVIDER) {",
)

js = ROOT / 'assets' / 'designer.js'
replace_once(
    js,
    "return {section: 36, container: 24, text: 6, image: 18, button: 6, spacer: 4, divider: 2, events: 60, vehicles: 60}[type] || 4;",
    "return {section: 36, container: 24, text: 6, image: 18, button: 6, spacer: 4, divider: 2, events: 60, vehicles: 60, galleries: 60}[type] || 4;",
)
replace_once(
    js,
    "            vehicles: {x: 0, y: 0, w: 12, h: 60}\n        }[type];",
    "            vehicles: {x: 0, y: 0, w: 12, h: 60},\n            galleries: {x: 0, y: 0, w: 12, h: 60}\n        }[type];",
)
replace_once(
    js,
    "            vehicles: {count: 12, columns: 3, gap: 20, padding: 18, radius: 6, cardBackground: '#ffffff', textColor: '#222222', headingColor: '#222222', accentColor: '#2f4858', showImage: true, showSummary: true, showFacts: true}\n        }[type] || {};",
    "            vehicles: {count: 12, columns: 3, gap: 20, padding: 18, radius: 6, cardBackground: '#ffffff', textColor: '#222222', headingColor: '#222222', accentColor: '#2f4858', showImage: true, showSummary: true, showFacts: true},\n            galleries: {count: 12, columns: 3, gap: 20, padding: 16, radius: 6, cardBackground: '#ffffff', textColor: '#222222', headingColor: '#222222', accentColor: '#2f4858', showCover: true, showSummary: true}\n        }[type] || {};",
)

marker = """        if (node.type === 'divider') {
            inspector.append(field('Farve', colorControl(node.props.color, value => commitMutation(() => { node.props.color = value; }))));
            inspector.append(field('Tykkelse', numberInput(node.props.thickness || 1, 1, 20, value => commitMutation(() => { node.props.thickness = value; }))));
        }"""
gallery_inspector = """        if (node.type === 'galleries') {
            inspector.append(field('Antal albummer', numberInput(node.props.count || 12, 1, 50, value => commitMutation(() => { node.props.count = value; }))));
            inspector.append(field('Kolonner', selectInput([
                ['1', '1 kolonne'],
                ['2', '2 kolonner'],
                ['3', '3 kolonner'],
                ['4', '4 kolonner']
            ], String(node.props.columns || 3), value => commitMutation(() => { node.props.columns = Number.parseInt(value, 10); }))));
            inspector.append(field('Afstand', numberInput(node.props.gap ?? 20, 0, 80, value => commitMutation(() => { node.props.gap = value; }))));
            inspector.append(field('Kort-padding', numberInput(node.props.padding ?? 16, 0, 80, value => commitMutation(() => { node.props.padding = value; }))));
            inspector.append(field('Hjørneradius', numberInput(node.props.radius ?? 6, 0, 60, value => commitMutation(() => { node.props.radius = value; }))));
            inspector.append(field('Kortbaggrund', colorControl(node.props.cardBackground || '#ffffff', value => commitMutation(() => { node.props.cardBackground = value; }))));
            inspector.append(field('Tekstfarve', colorControl(node.props.textColor || '#222222', value => commitMutation(() => { node.props.textColor = value; }))));
            inspector.append(field('Overskriftsfarve', colorControl(node.props.headingColor || '#222222', value => commitMutation(() => { node.props.headingColor = value; }))));
            inspector.append(field('Accentfarve', colorControl(node.props.accentColor || '#2f4858', value => commitMutation(() => { node.props.accentColor = value; }))));
            inspector.append(field('Vis cover', checkboxInput(node.props.showCover !== false, value => commitMutation(() => { node.props.showCover = value; }))));
            inspector.append(field('Vis kort beskrivelse', checkboxInput(node.props.showSummary !== false, value => commitMutation(() => { node.props.showSummary = value; }))));
        }

""" + marker
replace_once(js, marker, gallery_inspector)

for relative in ['src/Admin/DesignerController.php', 'src/Admin/TemplateDesignerController.php']:
    path = ROOT / relative
    replace_once(
        path,
        "            'vehicles' => 'Køretøjer',\n",
        "            'vehicles' => 'Køretøjer',\n            'galleries' => 'Billedgalleri',\n",
    )

print('Applied alpha.8 Gallery Designer migration')
