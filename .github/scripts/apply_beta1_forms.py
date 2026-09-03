from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]


def replace_once(path: Path, old: str, new: str) -> None:
    text = path.read_text(encoding='utf-8')
    count = text.count(old)
    if count != 1:
        raise SystemExit(f'{path}: expected exactly one match, found {count}: {old[:120]!r}')
    path.write_text(text.replace(old, new, 1), encoding='utf-8')


schema = ROOT / 'src' / 'Model' / 'NodeSchema.php'
replace_once(
    schema,
    "    public const GALLERIES = 'galleries';\n",
    "    public const GALLERIES = 'galleries';\n    public const CONTACT_FORM = 'contact-form';\n    public const MEMBERSHIP_FORM = 'membership-form';\n",
)
replace_once(
    schema,
    "            self::GALLERIES,\n        ];",
    "            self::GALLERIES,\n            self::CONTACT_FORM,\n            self::MEMBERSHIP_FORM,\n        ];",
)
replace_once(
    schema,
    "            self::GALLERIES => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 60],\n",
    "            self::GALLERIES => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 60],\n            self::CONTACT_FORM => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 100],\n            self::MEMBERSHIP_FORM => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 128],\n",
)

gallery_defaults = """            self::GALLERIES => [
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
form_defaults = gallery_defaults + """            self::CONTACT_FORM => [
                'columns' => 2,
                'gap' => 16,
                'padding' => 20,
                'radius' => 6,
                'background' => '#ffffff',
                'fieldBackground' => '#ffffff',
                'textColor' => '#222222',
                'labelColor' => '#222222',
                'borderColor' => '#d0d0d0',
                'accentColor' => '#2f4858',
                'buttonTextColor' => '#ffffff',
                'submitLabel' => 'Send besked',
                'successMessage' => 'Tak. Din henvendelse er sendt.',
                'showPhone' => true,
                'showSubject' => true,
                'showAddress' => false,
                'showMessage' => true,
                'messageRows' => 6,
                'requireConsent' => true,
                'consentText' => 'Jeg accepterer, at oplysningerne bruges til at besvare min henvendelse.',
            ],
            self::MEMBERSHIP_FORM => [
                'columns' => 2,
                'gap' => 16,
                'padding' => 20,
                'radius' => 6,
                'background' => '#ffffff',
                'fieldBackground' => '#ffffff',
                'textColor' => '#222222',
                'labelColor' => '#222222',
                'borderColor' => '#d0d0d0',
                'accentColor' => '#2f4858',
                'buttonTextColor' => '#ffffff',
                'submitLabel' => 'Send indmeldelse',
                'successMessage' => 'Tak. Din indmeldelse er sendt.',
                'showPhone' => true,
                'showSubject' => false,
                'showAddress' => true,
                'showMessage' => true,
                'messageRows' => 5,
                'requireConsent' => true,
                'consentText' => 'Jeg accepterer, at oplysningerne bruges til at behandle min indmeldelse.',
            ],
"""
replace_once(schema, gallery_defaults, form_defaults)

marker = """        if ($type === self::DIVIDER) {
            return [
                'color' => self::color((string) ($props['color'] ?? $defaults['color']), (string) $defaults['color']),
                'thickness' => max(1, min(20, (int) ($props['thickness'] ?? $defaults['thickness']))),
            ];
        }
"""
form_normalize = """        if ($type === self::CONTACT_FORM || $type === self::MEMBERSHIP_FORM) {
            return [
                'columns' => max(1, min(2, (int) ($props['columns'] ?? $defaults['columns']))),
                'gap' => max(0, min(60, (int) ($props['gap'] ?? $defaults['gap']))),
                'padding' => max(0, min(80, (int) ($props['padding'] ?? $defaults['padding']))),
                'radius' => max(0, min(30, (int) ($props['radius'] ?? $defaults['radius']))),
                'background' => self::color((string) ($props['background'] ?? $defaults['background']), (string) $defaults['background']),
                'fieldBackground' => self::color((string) ($props['fieldBackground'] ?? $defaults['fieldBackground']), (string) $defaults['fieldBackground']),
                'textColor' => self::color((string) ($props['textColor'] ?? $defaults['textColor']), (string) $defaults['textColor']),
                'labelColor' => self::color((string) ($props['labelColor'] ?? $defaults['labelColor']), (string) $defaults['labelColor']),
                'borderColor' => self::color((string) ($props['borderColor'] ?? $defaults['borderColor']), (string) $defaults['borderColor']),
                'accentColor' => self::color((string) ($props['accentColor'] ?? $defaults['accentColor']), (string) $defaults['accentColor']),
                'buttonTextColor' => self::color((string) ($props['buttonTextColor'] ?? $defaults['buttonTextColor']), (string) $defaults['buttonTextColor']),
                'submitLabel' => sanitize_text_field((string) ($props['submitLabel'] ?? $defaults['submitLabel'])),
                'successMessage' => sanitize_text_field((string) ($props['successMessage'] ?? $defaults['successMessage'])),
                'showPhone' => !array_key_exists('showPhone', $props) || !empty($props['showPhone']),
                'showSubject' => !empty($props['showSubject']),
                'showAddress' => !empty($props['showAddress']),
                'showMessage' => !array_key_exists('showMessage', $props) || !empty($props['showMessage']),
                'messageRows' => max(3, min(12, (int) ($props['messageRows'] ?? $defaults['messageRows']))),
                'requireConsent' => !array_key_exists('requireConsent', $props) || !empty($props['requireConsent']),
                'consentText' => sanitize_text_field((string) ($props['consentText'] ?? $defaults['consentText'])),
            ];
        }

""" + marker
replace_once(schema, marker, form_normalize)

renderer = ROOT / 'src' / 'Frontend' / 'Renderer.php'
replace_once(
    renderer,
    "        } elseif ($type === NodeSchema::GALLERIES) {\n            echo GalleryRenderer::renderList(is_array($node['props'] ?? null) ? $node['props'] : []); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped\n        } elseif ($type === NodeSchema::DIVIDER) {",
    "        } elseif ($type === NodeSchema::GALLERIES) {\n            echo GalleryRenderer::renderList(is_array($node['props'] ?? null) ? $node['props'] : []); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped\n        } elseif (in_array($type, [NodeSchema::CONTACT_FORM, NodeSchema::MEMBERSHIP_FORM], true)) {\n            echo FormRenderer::render($type, is_array($node['props'] ?? null) ? $node['props'] : [], $id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped\n        } elseif ($type === NodeSchema::DIVIDER) {",
)
replace_once(
    renderer,
    "        } elseif ($type === NodeSchema::DIVIDER) {\n            $parts[] = '--vdm-divider-color:' . (string) ($props['color'] ?? '#d0d0d0');",
    "        } elseif (in_array($type, [NodeSchema::CONTACT_FORM, NodeSchema::MEMBERSHIP_FORM], true)) {\n            $parts[] = '--vdm-form-columns:' . (int) ($props['columns'] ?? 2);\n            $parts[] = '--vdm-form-gap:' . (int) ($props['gap'] ?? 16) . 'px';\n            $parts[] = '--vdm-form-padding:' . (int) ($props['padding'] ?? 20) . 'px';\n            $parts[] = '--vdm-form-radius:' . (int) ($props['radius'] ?? 6) . 'px';\n            $parts[] = '--vdm-form-background:' . (string) ($props['background'] ?? '#ffffff');\n            $parts[] = '--vdm-form-field-background:' . (string) ($props['fieldBackground'] ?? '#ffffff');\n            $parts[] = '--vdm-form-text:' . (string) ($props['textColor'] ?? '#222222');\n            $parts[] = '--vdm-form-label:' . (string) ($props['labelColor'] ?? '#222222');\n            $parts[] = '--vdm-form-border:' . (string) ($props['borderColor'] ?? '#d0d0d0');\n            $parts[] = '--vdm-form-accent:' . (string) ($props['accentColor'] ?? '#2f4858');\n            $parts[] = '--vdm-form-button-text:' . (string) ($props['buttonTextColor'] ?? '#ffffff');\n        } elseif ($type === NodeSchema::DIVIDER) {\n            $parts[] = '--vdm-divider-color:' . (string) ($props['color'] ?? '#d0d0d0');",
)

js = ROOT / 'assets' / 'designer.js'
replace_once(
    js,
    "return {section: 36, container: 24, text: 6, image: 18, button: 6, spacer: 4, divider: 2, events: 60, vehicles: 60, galleries: 60}[type] || 4;",
    "return {section: 36, container: 24, text: 6, image: 18, button: 6, spacer: 4, divider: 2, events: 60, vehicles: 60, galleries: 60, 'contact-form': 100, 'membership-form': 128}[type] || 4;",
)
replace_once(
    js,
    "            galleries: {x: 0, y: 0, w: 12, h: 60}\n        }[type];",
    "            galleries: {x: 0, y: 0, w: 12, h: 60},\n            'contact-form': {x: 0, y: 0, w: 12, h: 100},\n            'membership-form': {x: 0, y: 0, w: 12, h: 128}\n        }[type];",
)
replace_once(
    js,
    "            galleries: {count: 12, columns: 3, gap: 20, padding: 16, radius: 6, cardBackground: '#ffffff', textColor: '#222222', headingColor: '#222222', accentColor: '#2f4858', showCover: true, showSummary: true}\n        }[type] || {};",
    "            galleries: {count: 12, columns: 3, gap: 20, padding: 16, radius: 6, cardBackground: '#ffffff', textColor: '#222222', headingColor: '#222222', accentColor: '#2f4858', showCover: true, showSummary: true},\n            'contact-form': {columns: 2, gap: 16, padding: 20, radius: 6, background: '#ffffff', fieldBackground: '#ffffff', textColor: '#222222', labelColor: '#222222', borderColor: '#d0d0d0', accentColor: '#2f4858', buttonTextColor: '#ffffff', submitLabel: 'Send besked', successMessage: 'Tak. Din henvendelse er sendt.', showPhone: true, showSubject: true, showAddress: false, showMessage: true, messageRows: 6, requireConsent: true, consentText: 'Jeg accepterer, at oplysningerne bruges til at besvare min henvendelse.'},\n            'membership-form': {columns: 2, gap: 16, padding: 20, radius: 6, background: '#ffffff', fieldBackground: '#ffffff', textColor: '#222222', labelColor: '#222222', borderColor: '#d0d0d0', accentColor: '#2f4858', buttonTextColor: '#ffffff', submitLabel: 'Send indmeldelse', successMessage: 'Tak. Din indmeldelse er sendt.', showPhone: true, showSubject: false, showAddress: true, showMessage: true, messageRows: 5, requireConsent: true, consentText: 'Jeg accepterer, at oplysningerne bruges til at behandle min indmeldelse.'}\n        }[type] || {};",
)

inspector_marker = """        if (node.type === 'divider') {
            inspector.append(field('Farve', colorControl(node.props.color, value => commitMutation(() => { node.props.color = value; }))));
            inspector.append(field('Tykkelse', numberInput(node.props.thickness || 1, 1, 20, value => commitMutation(() => { node.props.thickness = value; }))));
        }"""
form_inspector = """        if (['contact-form', 'membership-form'].includes(node.type)) {
            const isMembershipForm = node.type === 'membership-form';
            inspector.append(field('Kolonner', selectInput([
                ['1', '1 kolonne'],
                ['2', '2 kolonner']
            ], String(node.props.columns || 2), value => commitMutation(() => { node.props.columns = Number.parseInt(value, 10); }))));
            inspector.append(field('Afstand', numberInput(node.props.gap ?? 16, 0, 60, value => commitMutation(() => { node.props.gap = value; }))));
            inspector.append(field('Formular-padding', numberInput(node.props.padding ?? 20, 0, 80, value => commitMutation(() => { node.props.padding = value; }))));
            inspector.append(field('Hjørneradius', numberInput(node.props.radius ?? 6, 0, 30, value => commitMutation(() => { node.props.radius = value; }))));
            inspector.append(field('Baggrund', colorControl(node.props.background || '#ffffff', value => commitMutation(() => { node.props.background = value; }))));
            inspector.append(field('Feltbaggrund', colorControl(node.props.fieldBackground || '#ffffff', value => commitMutation(() => { node.props.fieldBackground = value; }))));
            inspector.append(field('Tekstfarve', colorControl(node.props.textColor || '#222222', value => commitMutation(() => { node.props.textColor = value; }))));
            inspector.append(field('Labelfarve', colorControl(node.props.labelColor || '#222222', value => commitMutation(() => { node.props.labelColor = value; }))));
            inspector.append(field('Kantfarve', colorControl(node.props.borderColor || '#d0d0d0', value => commitMutation(() => { node.props.borderColor = value; }))));
            inspector.append(field('Knapfarve', colorControl(node.props.accentColor || '#2f4858', value => commitMutation(() => { node.props.accentColor = value; }))));
            inspector.append(field('Knaptekstfarve', colorControl(node.props.buttonTextColor || '#ffffff', value => commitMutation(() => { node.props.buttonTextColor = value; }))));
            inspector.append(field('Knaptekst', textInput(node.props.submitLabel || (isMembershipForm ? 'Send indmeldelse' : 'Send besked'), value => commitMutation(() => { node.props.submitLabel = value; }))));
            inspector.append(field('Tak-besked', textInput(node.props.successMessage || '', value => commitMutation(() => { node.props.successMessage = value; }))));
            inspector.append(field('Vis telefon', checkboxInput(node.props.showPhone !== false, value => commitMutation(() => { node.props.showPhone = value; }))));
            if (isMembershipForm) {
                inspector.append(field('Vis adresse', checkboxInput(node.props.showAddress !== false, value => commitMutation(() => { node.props.showAddress = value; }))));
            } else {
                inspector.append(field('Vis emne', checkboxInput(node.props.showSubject !== false, value => commitMutation(() => { node.props.showSubject = value; }))));
            }
            inspector.append(field('Vis besked', checkboxInput(node.props.showMessage !== false, value => commitMutation(() => { node.props.showMessage = value; }))));
            inspector.append(field('Tekstlinjer i besked', numberInput(node.props.messageRows || (isMembershipForm ? 5 : 6), 3, 12, value => commitMutation(() => { node.props.messageRows = value; }))));
            inspector.append(field('Kræv samtykke', checkboxInput(node.props.requireConsent !== false, value => commitMutation(() => { node.props.requireConsent = value; }))));
            inspector.append(field('Samtykketekst', textInput(node.props.consentText || '', value => commitMutation(() => { node.props.consentText = value; }))));
        }

""" + inspector_marker
replace_once(js, inspector_marker, form_inspector)
replace_once(
    js,
    "    saveButton.addEventListener('click', save);\n",
    "    saveButton.addEventListener('click', save);\n    canvas.addEventListener('submit', event => event.preventDefault());\n",
)

designer = ROOT / 'src' / 'Admin' / 'DesignerController.php'
replace_once(
    designer,
    "            'galleries' => 'Billedgalleri',\n",
    "            'galleries' => 'Billedgalleri',\n            'contact-form' => 'Kontaktformular',\n            'membership-form' => 'Bliv medlem',\n",
)

plugin = ROOT / 'src' / 'Core' / 'Plugin.php'
replace_once(
    plugin,
    "use VisualDesignerManager\\Frontend\\VehicleFrontendController;\nuse VisualDesignerManager\\Http\\RestController;",
    "use VisualDesignerManager\\Frontend\\VehicleFrontendController;\nuse VisualDesignerManager\\Forms\\FormSubmissionController;\nuse VisualDesignerManager\\Http\\RestController;",
)
replace_once(
    plugin,
    "        GalleryFrontendController::register();\n        RestController::register();",
    "        GalleryFrontendController::register();\n        FormSubmissionController::register();\n        RestController::register();",
)

main = ROOT / 'visual-designer-manager.php'
replace_once(main, ' * Version: 2.0.0-alpha.8\n', ' * Version: 2.0.0-beta.1\n')
replace_once(main, "define('VDM_VERSION', '2.0.0-alpha.8');", "define('VDM_VERSION', '2.0.0-beta.1');")

print('Applied beta.1 forms migration')
