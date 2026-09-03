from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]


def replace_once(path: Path, old: str, new: str) -> None:
    text = path.read_text(encoding='utf-8')
    count = text.count(old)
    if count != 1:
        raise SystemExit(f'{path}: expected one match, found {count}: {old[:110]!r}')
    path.write_text(text.replace(old, new, 1), encoding='utf-8')


schema = ROOT / 'src' / 'Model' / 'NodeSchema.php'
replace_once(schema,
"""            self::SECTION => [
                'background' => '#ffffff',
                'padding' => 0,
                'autoHeight' => true,
                'minHeightRows' => 36,
            ],
            self::CONTAINER => [
                'background' => 'transparent',
                'padding' => 16,
                'autoHeight' => true,
                'minHeightRows' => 24,
            ],
            self::TEXT => ['content' => '<p>Tekst</p>', 'color' => '#222222', 'fontSize' => 18],
""",
"""            self::SECTION => [
                'background' => '#ffffff',
                'padding' => 0,
                'radius' => 0,
                'borderWidth' => 0,
                'borderColor' => '#d0d0d0',
                'autoHeight' => true,
                'minHeightRows' => 36,
            ],
            self::CONTAINER => [
                'background' => 'transparent',
                'padding' => 16,
                'radius' => 0,
                'borderWidth' => 0,
                'borderColor' => '#d0d0d0',
                'autoHeight' => true,
                'minHeightRows' => 24,
            ],
            self::TEXT => [
                'content' => '<p>Tekst</p>',
                'color' => '#222222',
                'fontSize' => 18,
                'fontWeight' => 400,
                'lineHeight' => 1.5,
                'align' => 'left',
                'verticalAlign' => 'top',
                'background' => 'transparent',
                'padding' => 0,
                'radius' => 0,
            ],
""")
replace_once(schema,
"""                'fontSize' => 16,
                'fontWeight' => 600,
            ],
""",
"""                'fontSize' => 16,
                'fontWeight' => 600,
                'borderWidth' => 0,
                'borderColor' => '#2f4858',
            ],
""")
replace_once(schema,
"""                'padding' => max(0, min(120, (int) ($props['padding'] ?? $defaults['padding']))),
                'autoHeight' => !array_key_exists('autoHeight', $props) || (bool) $props['autoHeight'],
                'minHeightRows' => max(1, min(2000, (int) ($props['minHeightRows'] ?? $defaults['minHeightRows']))),
""",
"""                'padding' => max(0, min(120, (int) ($props['padding'] ?? $defaults['padding']))),
                'radius' => max(0, min(80, (int) ($props['radius'] ?? $defaults['radius']))),
                'borderWidth' => max(0, min(20, (int) ($props['borderWidth'] ?? $defaults['borderWidth']))),
                'borderColor' => self::color((string) ($props['borderColor'] ?? $defaults['borderColor']), (string) $defaults['borderColor']),
                'autoHeight' => !array_key_exists('autoHeight', $props) || (bool) $props['autoHeight'],
                'minHeightRows' => max(1, min(2000, (int) ($props['minHeightRows'] ?? $defaults['minHeightRows']))),
""")
replace_once(schema,
"""        if ($type === self::TEXT) {
            return [
                'content' => wp_kses_post((string) ($props['content'] ?? $defaults['content'])),
                'color' => self::color((string) ($props['color'] ?? $defaults['color']), (string) $defaults['color']),
                'fontSize' => max(8, min(120, (int) ($props['fontSize'] ?? $defaults['fontSize']))),
            ];
        }
""",
"""        if ($type === self::TEXT) {
            $align = (string) ($props['align'] ?? $defaults['align']);
            if (!in_array($align, ['left', 'center', 'right'], true)) {
                $align = 'left';
            }
            $verticalAlign = (string) ($props['verticalAlign'] ?? $defaults['verticalAlign']);
            if (!in_array($verticalAlign, ['top', 'center', 'bottom'], true)) {
                $verticalAlign = 'top';
            }
            $fontWeight = (int) ($props['fontWeight'] ?? $defaults['fontWeight']);
            if (!in_array($fontWeight, [400, 500, 600, 700], true)) {
                $fontWeight = 400;
            }
            return [
                'content' => wp_kses_post((string) ($props['content'] ?? $defaults['content'])),
                'color' => self::color((string) ($props['color'] ?? $defaults['color']), (string) $defaults['color']),
                'fontSize' => max(8, min(120, (int) ($props['fontSize'] ?? $defaults['fontSize']))),
                'fontWeight' => $fontWeight,
                'lineHeight' => max(0.8, min(3.0, (float) ($props['lineHeight'] ?? $defaults['lineHeight']))),
                'align' => $align,
                'verticalAlign' => $verticalAlign,
                'background' => self::color((string) ($props['background'] ?? $defaults['background']), (string) $defaults['background']),
                'padding' => max(0, min(120, (int) ($props['padding'] ?? $defaults['padding']))),
                'radius' => max(0, min(80, (int) ($props['radius'] ?? $defaults['radius']))),
            ];
        }
""")
replace_once(schema,
"""                'fontSize' => max(8, min(80, (int) ($props['fontSize'] ?? $defaults['fontSize']))),
                'fontWeight' => $fontWeight,
            ];
""",
"""                'fontSize' => max(8, min(80, (int) ($props['fontSize'] ?? $defaults['fontSize']))),
                'fontWeight' => $fontWeight,
                'borderWidth' => max(0, min(20, (int) ($props['borderWidth'] ?? $defaults['borderWidth']))),
                'borderColor' => self::color((string) ($props['borderColor'] ?? $defaults['borderColor']), (string) $defaults['borderColor']),
            ];
""")

renderer = ROOT / 'src' / 'Frontend' / 'Renderer.php'
replace_once(renderer,
"""            $parts[] = '--vdm-background:' . (string) ($props['background'] ?? 'transparent');
            $parts[] = '--vdm-padding:' . (int) ($props['padding'] ?? 0) . 'px';
        } elseif ($type === NodeSchema::TEXT) {
            $parts[] = '--vdm-color:' . (string) ($props['color'] ?? '#222222');
            $parts[] = '--vdm-font-size:' . (int) ($props['fontSize'] ?? 18) . 'px';
""",
"""            $parts[] = '--vdm-background:' . (string) ($props['background'] ?? 'transparent');
            $parts[] = '--vdm-padding:' . (int) ($props['padding'] ?? 0) . 'px';
            $parts[] = '--vdm-radius:' . (int) ($props['radius'] ?? 0) . 'px';
            $parts[] = '--vdm-border-width:' . (int) ($props['borderWidth'] ?? 0) . 'px';
            $parts[] = '--vdm-border-color:' . (string) ($props['borderColor'] ?? '#d0d0d0');
        } elseif ($type === NodeSchema::TEXT) {
            $vertical = match ((string) ($props['verticalAlign'] ?? 'top')) {
                'center' => 'center',
                'bottom' => 'flex-end',
                default => 'flex-start',
            };
            $parts[] = '--vdm-color:' . (string) ($props['color'] ?? '#222222');
            $parts[] = '--vdm-font-size:' . (int) ($props['fontSize'] ?? 18) . 'px';
            $parts[] = '--vdm-text-font-weight:' . (int) ($props['fontWeight'] ?? 400);
            $parts[] = '--vdm-text-line-height:' . (float) ($props['lineHeight'] ?? 1.5);
            $parts[] = '--vdm-text-align:' . (string) ($props['align'] ?? 'left');
            $parts[] = '--vdm-text-vertical-align:' . $vertical;
            $parts[] = '--vdm-text-background:' . (string) ($props['background'] ?? 'transparent');
            $parts[] = '--vdm-text-padding:' . (int) ($props['padding'] ?? 0) . 'px';
            $parts[] = '--vdm-text-radius:' . (int) ($props['radius'] ?? 0) . 'px';
""")
replace_once(renderer,
"""            $parts[] = '--vdm-button-font-weight:' . (int) ($props['fontWeight'] ?? 600);
            $parts[] = '--vdm-button-justify:' . $justify;
""",
"""            $parts[] = '--vdm-button-font-weight:' . (int) ($props['fontWeight'] ?? 600);
            $parts[] = '--vdm-button-border-width:' . (int) ($props['borderWidth'] ?? 0) . 'px';
            $parts[] = '--vdm-button-border-color:' . (string) ($props['borderColor'] ?? '#2f4858');
            $parts[] = '--vdm-button-justify:' . $justify;
""")

css = ROOT / 'assets' / 'frontend.css'
replace_once(css,
"""    background:var(--vdm-background,transparent);
    padding:var(--vdm-padding,0);
    overflow:visible
}
.vdm-node-surface{height:100%;min-height:0}
.vdm-node--text{color:var(--vdm-color,#222);font-size:var(--vdm-font-size,18px);overflow:visible}
.vdm-text,.vdm-text>*:first-child{margin-top:0}
""",
"""    background:var(--vdm-background,transparent);
    padding:var(--vdm-padding,0);
    border:var(--vdm-border-width,0) solid var(--vdm-border-color,#d0d0d0);
    border-radius:var(--vdm-radius,0);
    overflow:visible
}
.vdm-node-surface{height:100%;min-height:0}
.vdm-node--text{display:flex;align-items:var(--vdm-text-vertical-align,flex-start);color:var(--vdm-color,#222);font-size:var(--vdm-font-size,18px);font-weight:var(--vdm-text-font-weight,400);line-height:var(--vdm-text-line-height,1.5);text-align:var(--vdm-text-align,left);background:var(--vdm-text-background,transparent);padding:var(--vdm-text-padding,0);border-radius:var(--vdm-text-radius,0);overflow:visible}
.vdm-text{width:100%}
.vdm-text,.vdm-text>*:first-child{margin-top:0}
""")
replace_once(css,
""".vdm-button{display:inline-flex;align-items:center;justify-content:center;width:var(--vdm-button-width,auto);min-height:100%;padding:var(--vdm-button-padding-y,10px) var(--vdm-button-padding-x,18px);box-sizing:border-box;text-decoration:none;background:var(--vdm-button-background,#2f4858);color:var(--vdm-button-color,#fff);border-radius:var(--vdm-button-radius,4px);font-size:var(--vdm-button-font-size,16px);font-weight:var(--vdm-button-font-weight,600)}
""",
""".vdm-button{display:inline-flex;align-items:center;justify-content:center;width:var(--vdm-button-width,auto);min-height:100%;padding:var(--vdm-button-padding-y,10px) var(--vdm-button-padding-x,18px);box-sizing:border-box;text-decoration:none;background:var(--vdm-button-background,#2f4858);color:var(--vdm-button-color,#fff);border:var(--vdm-button-border-width,0) solid var(--vdm-button-border-color,#2f4858);border-radius:var(--vdm-button-radius,4px);font-size:var(--vdm-button-font-size,16px);font-weight:var(--vdm-button-font-weight,600)}
""")

js = ROOT / 'assets' / 'designer.js'
replace_once(js,
"""            section: {background: '#ffffff', padding: 0, autoHeight: true, minHeightRows: 36},
            container: {background: 'transparent', padding: 16, autoHeight: true, minHeightRows: 24},
            text: {content: '<p>Tekst</p>', color: '#222222', fontSize: 18},
            image: {attachmentId: 0, alt: '', objectFit: 'cover'},
            button: {label: 'Knap', url: '#', target: '_self', align: 'left', background: '#2f4858', color: '#ffffff', radius: 4, paddingX: 18, paddingY: 10, fontSize: 16, fontWeight: 600},
""",
"""            section: {background: '#ffffff', padding: 0, radius: 0, borderWidth: 0, borderColor: '#d0d0d0', autoHeight: true, minHeightRows: 36},
            container: {background: 'transparent', padding: 16, radius: 0, borderWidth: 0, borderColor: '#d0d0d0', autoHeight: true, minHeightRows: 24},
            text: {content: '<p>Tekst</p>', color: '#222222', fontSize: 18, fontWeight: 400, lineHeight: 1.5, align: 'left', verticalAlign: 'top', background: 'transparent', padding: 0, radius: 0},
            image: {attachmentId: 0, alt: '', objectFit: 'cover'},
            button: {label: 'Knap', url: '#', target: '_self', align: 'left', background: '#2f4858', color: '#ffffff', radius: 4, paddingX: 18, paddingY: 10, fontSize: 16, fontWeight: 600, borderWidth: 0, borderColor: '#2f4858'},
""")
replace_once(js,
"""            inspector.append(field('Baggrund', colorControl(node.props.background === 'transparent' ? '#ffffff' : node.props.background, value => commitMutation(() => { node.props.background = value; }))));
            inspector.append(field('Padding', numberInput(node.props.padding || 0, 0, 120, value => commitMutation(() => { node.props.padding = value; }))));
        }

        if (node.type === 'text') {
            inspector.append(field('Tekst', richTextControl(node)));
            inspector.append(field('Tekstfarve', colorControl(node.props.color, value => commitMutation(() => { node.props.color = value; }))));
            inspector.append(field('Skriftstørrelse', numberInput(node.props.fontSize || 18, 8, 120, value => commitMutation(() => { node.props.fontSize = value; }))));
        }
""",
"""            inspector.append(field('Baggrund', colorControl(node.props.background === 'transparent' ? '#ffffff' : node.props.background, value => commitMutation(() => { node.props.background = value; }))));
            inspector.append(field('Padding', numberInput(node.props.padding || 0, 0, 120, value => commitMutation(() => { node.props.padding = value; }))));
            inspector.append(field('Radius', numberInput(node.props.radius || 0, 0, 80, value => commitMutation(() => { node.props.radius = value; }))));
            inspector.append(field('Kantbredde', numberInput(node.props.borderWidth || 0, 0, 20, value => commitMutation(() => { node.props.borderWidth = value; }))));
            inspector.append(field('Kantfarve', colorControl(node.props.borderColor || '#d0d0d0', value => commitMutation(() => { node.props.borderColor = value; }))));
        }

        if (node.type === 'text') {
            inspector.append(field('Tekst', richTextControl(node)));
            inspector.append(field('Tekstfarve', colorControl(node.props.color, value => commitMutation(() => { node.props.color = value; }))));
            inspector.append(field('Skriftstørrelse', numberInput(node.props.fontSize || 18, 8, 120, value => commitMutation(() => { node.props.fontSize = value; }))));
            inspector.append(field('Skriftvægt', selectInput([['400','Normal'],['500','Medium'],['600','Semibold'],['700','Fed']], String(node.props.fontWeight || 400), value => commitMutation(() => { node.props.fontWeight = Number.parseInt(value, 10); }))));
            inspector.append(field('Linjehøjde ×100', numberInput(Math.round((node.props.lineHeight || 1.5) * 100), 80, 300, value => commitMutation(() => { node.props.lineHeight = value / 100; }))));
            inspector.append(field('Justering', selectInput([['left','Venstre'],['center','Centreret'],['right','Højre']], node.props.align || 'left', value => commitMutation(() => { node.props.align = value; }))));
            inspector.append(field('Lodret placering', selectInput([['top','Top'],['center','Centreret'],['bottom','Bund']], node.props.verticalAlign || 'top', value => commitMutation(() => { node.props.verticalAlign = value; }))));
            inspector.append(field('Baggrund', colorControl(node.props.background === 'transparent' ? '#ffffff' : (node.props.background || '#ffffff'), value => commitMutation(() => { node.props.background = value; }))));
            inspector.append(field('Padding', numberInput(node.props.padding || 0, 0, 120, value => commitMutation(() => { node.props.padding = value; }))));
            inspector.append(field('Radius', numberInput(node.props.radius || 0, 0, 80, value => commitMutation(() => { node.props.radius = value; }))));
        }
""")
replace_once(js,
"""            inspector.append(field('Padding lodret', numberInput(node.props.paddingY || 10, 0, 80, value => commitMutation(() => { node.props.paddingY = value; }))));
            inspector.append(field('Radius', numberInput(node.props.radius || 0, 0, 80, value => commitMutation(() => { node.props.radius = value; }))));
        }
""",
"""            inspector.append(field('Padding lodret', numberInput(node.props.paddingY || 10, 0, 80, value => commitMutation(() => { node.props.paddingY = value; }))));
            inspector.append(field('Radius', numberInput(node.props.radius || 0, 0, 80, value => commitMutation(() => { node.props.radius = value; }))));
            inspector.append(field('Kantbredde', numberInput(node.props.borderWidth || 0, 0, 20, value => commitMutation(() => { node.props.borderWidth = value; }))));
            inspector.append(field('Kantfarve', colorControl(node.props.borderColor || node.props.background || '#2f4858', value => commitMutation(() => { node.props.borderColor = value; }))));
        }
""")

migrator = ROOT / 'src' / 'Transfer' / 'SchemaOneMigrator.php'
replace_once(migrator,
"""                'padding' => max(0, min(120, (int) ($props['padding'] ?? 0))),
                'autoHeight' => true,
                'minHeightRows' => max(1, (int) ($props['minHeightRows'] ?? 1)),
""",
"""                'padding' => max(0, min(120, (int) ($props['padding'] ?? 0))),
                'radius' => max(0, min(80, (int) ($props['radius'] ?? 0))),
                'borderWidth' => max(0, min(20, (int) ($props['borderWidth'] ?? 0))),
                'borderColor' => self::color((string) ($props['borderColor'] ?? ''), '#d0d0d0'),
                'autoHeight' => true,
                'minHeightRows' => max(1, (int) ($props['minHeightRows'] ?? 1)),
""")
replace_once(migrator,
"""            return ['content' => $content !== '' ? $content : '<p></p>', 'color' => self::color((string) ($props['textColor'] ?? ''), '#222222'), 'fontSize' => max(8, min(120, (int) ($props['fontSize'] ?? 18)))];
""",
"""            return [
                'content' => $content !== '' ? $content : '<p></p>',
                'color' => self::color((string) ($props['textColor'] ?? ''), '#222222'),
                'fontSize' => max(8, min(120, (int) ($props['fontSize'] ?? 18))),
                'fontWeight' => self::fontWeight($props['fontWeight'] ?? 400),
                'lineHeight' => max(0.8, min(3.0, (float) ($props['lineHeight'] ?? 1.5))),
                'align' => in_array((string) ($props['align'] ?? ''), ['left','center','right'], true) ? (string) $props['align'] : 'left',
                'verticalAlign' => in_array((string) ($props['verticalAlign'] ?? ''), ['top','center','bottom'], true) ? (string) $props['verticalAlign'] : 'top',
                'background' => !empty($props['backgroundTransparent']) ? 'transparent' : self::colorOrTransparent((string) ($props['background'] ?? ''), 'transparent'),
                'padding' => max(0, min(120, (int) ($props['padding'] ?? 0))),
                'radius' => max(0, min(80, (int) ($props['radius'] ?? 0))),
            ];
""")
replace_once(migrator,
"""                'fontSize' => max(8, min(80, (int) ($props['fontSize'] ?? 16))),
                'fontWeight' => self::fontWeight($props['fontWeight'] ?? 600),
            ];
""",
"""                'fontSize' => max(8, min(80, (int) ($props['fontSize'] ?? 16))),
                'fontWeight' => self::fontWeight($props['fontWeight'] ?? 600),
                'borderWidth' => max(0, min(20, (int) ($props['borderWidth'] ?? 0))),
                'borderColor' => self::color((string) ($props['borderColor'] ?? $props['background'] ?? ''), '#2f4858'),
            ];
""")

print('RC.1 visual parity properties applied')
