from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]


def replace_once(path: Path, old: str, new: str) -> None:
    text = path.read_text(encoding='utf-8')
    count = text.count(old)
    if count != 1:
        raise SystemExit(f'{path}: expected exactly one match, found {count}: {old[:140]!r}')
    path.write_text(text.replace(old, new, 1), encoding='utf-8')


schema = ROOT / 'src' / 'Model' / 'NodeSchema.php'
replace_once(schema, "    public const MEMBERSHIP_FORM = 'membership-form';\n", "    public const MEMBERSHIP_FORM = 'membership-form';\n    public const NAVIGATION = 'navigation';\n")
replace_once(schema, "            self::MEMBERSHIP_FORM,\n        ];", "            self::MEMBERSHIP_FORM,\n            self::NAVIGATION,\n        ];")
replace_once(schema, "            self::MEMBERSHIP_FORM => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 128],\n", "            self::MEMBERSHIP_FORM => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 128],\n            self::NAVIGATION => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 8],\n")

form_defaults_end = """            self::MEMBERSHIP_FORM => [
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
nav_defaults = form_defaults_end + """            self::NAVIGATION => [
                'menuId' => 0,
                'orientation' => 'horizontal',
                'align' => 'left',
                'gap' => 24,
                'fontSize' => 16,
                'fontWeight' => 600,
                'textColor' => '#222222',
                'hoverColor' => '#2271b1',
                'background' => 'transparent',
                'submenuBackground' => '#ffffff',
                'submenuTextColor' => '#222222',
                'toggleLabel' => 'Menu',
            ],
"""
replace_once(schema, form_defaults_end, nav_defaults)

form_normalize_end = """        if ($type === self::CONTACT_FORM || $type === self::MEMBERSHIP_FORM) {
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

"""
nav_normalize = form_normalize_end + """        if ($type === self::NAVIGATION) {
            $orientation = (string) ($props['orientation'] ?? $defaults['orientation']);
            if (!in_array($orientation, ['horizontal', 'vertical'], true)) {
                $orientation = 'horizontal';
            }
            $align = (string) ($props['align'] ?? $defaults['align']);
            if (!in_array($align, ['left', 'center', 'right'], true)) {
                $align = 'left';
            }
            $fontWeight = (int) ($props['fontWeight'] ?? $defaults['fontWeight']);
            if (!in_array($fontWeight, [400, 500, 600, 700], true)) {
                $fontWeight = 600;
            }
            $toggleLabel = sanitize_text_field((string) ($props['toggleLabel'] ?? $defaults['toggleLabel']));
            if ($toggleLabel === '') {
                $toggleLabel = 'Menu';
            }
            return [
                'menuId' => absint($props['menuId'] ?? 0),
                'orientation' => $orientation,
                'align' => $align,
                'gap' => max(0, min(80, (int) ($props['gap'] ?? $defaults['gap']))),
                'fontSize' => max(10, min(40, (int) ($props['fontSize'] ?? $defaults['fontSize']))),
                'fontWeight' => $fontWeight,
                'textColor' => self::color((string) ($props['textColor'] ?? $defaults['textColor']), (string) $defaults['textColor']),
                'hoverColor' => self::color((string) ($props['hoverColor'] ?? $defaults['hoverColor']), (string) $defaults['hoverColor']),
                'background' => self::color((string) ($props['background'] ?? $defaults['background']), (string) $defaults['background']),
                'submenuBackground' => self::color((string) ($props['submenuBackground'] ?? $defaults['submenuBackground']), (string) $defaults['submenuBackground']),
                'submenuTextColor' => self::color((string) ($props['submenuTextColor'] ?? $defaults['submenuTextColor']), (string) $defaults['submenuTextColor']),
                'toggleLabel' => $toggleLabel,
            ];
        }

"""
replace_once(schema, form_normalize_end, nav_normalize)

renderer = ROOT / 'src' / 'Frontend' / 'Renderer.php'
replace_once(
    renderer,
    "        } elseif (in_array($type, [NodeSchema::CONTACT_FORM, NodeSchema::MEMBERSHIP_FORM], true)) {\n            echo FormRenderer::render($type, is_array($node['props'] ?? null) ? $node['props'] : [], $id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped\n        } elseif ($type === NodeSchema::DIVIDER) {",
    "        } elseif (in_array($type, [NodeSchema::CONTACT_FORM, NodeSchema::MEMBERSHIP_FORM], true)) {\n            echo FormRenderer::render($type, is_array($node['props'] ?? null) ? $node['props'] : [], $id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped\n        } elseif ($type === NodeSchema::NAVIGATION) {\n            echo NavigationRenderer::render(is_array($node['props'] ?? null) ? $node['props'] : [], $id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped\n        } elseif ($type === NodeSchema::DIVIDER) {",
)
replace_once(
    renderer,
    "        } elseif ($type === NodeSchema::DIVIDER) {\n            $parts[] = '--vdm-divider-color:' . (string) ($props['color'] ?? '#d0d0d0');",
    "        } elseif ($type === NodeSchema::NAVIGATION) {\n            $align = (string) ($props['align'] ?? 'left');\n            $justify = match ($align) {\n                'center' => 'center',\n                'right' => 'flex-end',\n                default => 'flex-start',\n            };\n            $parts[] = '--vdm-navigation-gap:' . (int) ($props['gap'] ?? 24) . 'px';\n            $parts[] = '--vdm-navigation-font-size:' . (int) ($props['fontSize'] ?? 16) . 'px';\n            $parts[] = '--vdm-navigation-font-weight:' . (int) ($props['fontWeight'] ?? 600);\n            $parts[] = '--vdm-navigation-text:' . (string) ($props['textColor'] ?? '#222222');\n            $parts[] = '--vdm-navigation-hover:' . (string) ($props['hoverColor'] ?? '#2271b1');\n            $parts[] = '--vdm-navigation-background:' . (string) ($props['background'] ?? 'transparent');\n            $parts[] = '--vdm-navigation-submenu-background:' . (string) ($props['submenuBackground'] ?? '#ffffff');\n            $parts[] = '--vdm-navigation-submenu-text:' . (string) ($props['submenuTextColor'] ?? '#222222');\n            $parts[] = '--vdm-navigation-justify:' . $justify;\n        } elseif ($type === NodeSchema::DIVIDER) {\n            $parts[] = '--vdm-divider-color:' . (string) ($props['color'] ?? '#d0d0d0');",
)

for relative in ['src/Admin/DesignerController.php', 'src/Admin/TemplateDesignerController.php']:
    path = ROOT / relative
    replace_once(path, "use VisualDesignerManager\\Http\\RestController;\n", "use VisualDesignerManager\\Http\\RestController;\nuse VisualDesignerManager\\Navigation\\NavigationRepository;\n")
    replace_once(
        path,
        "        wp_enqueue_style('vdm-designer', VDM_URL . 'assets/designer.css', ['vdm-frontend'], VDM_VERSION);\n        wp_enqueue_script('vdm-designer', VDM_URL . 'assets/designer.js', ['media-editor'], VDM_VERSION, true);",
        "        wp_enqueue_style('vdm-designer', VDM_URL . 'assets/designer.css', ['vdm-frontend'], VDM_VERSION);\n        wp_enqueue_script('vdm-frontend-runtime', VDM_URL . 'assets/frontend.js', [], VDM_VERSION, true);\n        wp_enqueue_script('vdm-designer', VDM_URL . 'assets/designer.js', ['media-editor', 'vdm-frontend-runtime'], VDM_VERSION, true);",
    )
    replace_once(
        path,
        "            'themeColors' => DesignerController::themeColors(),\n",
        "            'themeColors' => DesignerController::themeColors(),\n            'navigationMenus' => NavigationRepository::choices(),\n",
    )
    replace_once(
        path,
        "            'galleries' => 'Billedgalleri',\n",
        "            'galleries' => 'Billedgalleri',\n            'navigation' => 'Navigation',\n",
    )

js = ROOT / 'assets' / 'designer.js'
replace_once(
    js,
    "return {section: 36, container: 24, text: 6, image: 18, button: 6, spacer: 4, divider: 2, events: 60, vehicles: 60, galleries: 60, 'contact-form': 100, 'membership-form': 128}[type] || 4;",
    "return {section: 36, container: 24, text: 6, image: 18, button: 6, spacer: 4, divider: 2, events: 60, vehicles: 60, galleries: 60, 'contact-form': 100, 'membership-form': 128, navigation: 8}[type] || 4;",
)
replace_once(
    js,
    "            'membership-form': {x: 0, y: 0, w: 12, h: 128}\n        }[type];",
    "            'membership-form': {x: 0, y: 0, w: 12, h: 128},\n            navigation: {x: 0, y: 0, w: 12, h: 8}\n        }[type];",
)
replace_once(
    js,
    "            'membership-form': {columns: 2, gap: 16, padding: 20, radius: 6, background: '#ffffff', fieldBackground: '#ffffff', textColor: '#222222', labelColor: '#222222', borderColor: '#d0d0d0', accentColor: '#2f4858', buttonTextColor: '#ffffff', submitLabel: 'Send indmeldelse', successMessage: 'Tak. Din indmeldelse er sendt.', showPhone: true, showSubject: false, showAddress: true, showMessage: true, messageRows: 5, requireConsent: true, consentText: 'Jeg accepterer, at oplysningerne bruges til at behandle min indmeldelse.'}\n        }[type] || {};",
    "            'membership-form': {columns: 2, gap: 16, padding: 20, radius: 6, background: '#ffffff', fieldBackground: '#ffffff', textColor: '#222222', labelColor: '#222222', borderColor: '#d0d0d0', accentColor: '#2f4858', buttonTextColor: '#ffffff', submitLabel: 'Send indmeldelse', successMessage: 'Tak. Din indmeldelse er sendt.', showPhone: true, showSubject: false, showAddress: true, showMessage: true, messageRows: 5, requireConsent: true, consentText: 'Jeg accepterer, at oplysningerne bruges til at behandle min indmeldelse.'},\n            navigation: {menuId: 0, orientation: 'horizontal', align: 'left', gap: 24, fontSize: 16, fontWeight: 600, textColor: '#222222', hoverColor: '#2271b1', background: 'transparent', submenuBackground: '#ffffff', submenuTextColor: '#222222', toggleLabel: 'Menu'}\n        }[type] || {};",
)

inspector_marker = """        if (node.type === 'divider') {
            inspector.append(field('Farve', colorControl(node.props.color, value => commitMutation(() => { node.props.color = value; }))));
            inspector.append(field('Tykkelse', numberInput(node.props.thickness || 1, 1, 20, value => commitMutation(() => { node.props.thickness = value; }))));
        }"""
nav_inspector = """        if (node.type === 'navigation') {
            const menuValues = [['0', 'Vælg menu']];
            if (Array.isArray(config.navigationMenus)) {
                config.navigationMenus.forEach(menu => {
                    const count = Number.parseInt(menu.count || 0, 10) || 0;
                    menuValues.push([String(menu.id || 0), String(menu.name || 'Menu') + ' (' + count + ')']);
                });
            }
            inspector.append(field('WordPress-menu', selectInput(menuValues, String(node.props.menuId || 0), value => commitMutation(() => { node.props.menuId = Number.parseInt(value, 10) || 0; }))));
            inspector.append(field('Retning', selectInput([
                ['horizontal', 'Vandret'],
                ['vertical', 'Lodret']
            ], node.props.orientation || 'horizontal', value => commitMutation(() => { node.props.orientation = value; }))));
            inspector.append(field('Justering', selectInput([
                ['left', 'Venstre'],
                ['center', 'Centreret'],
                ['right', 'Højre']
            ], node.props.align || 'left', value => commitMutation(() => { node.props.align = value; }))));
            inspector.append(field('Afstand', numberInput(node.props.gap ?? 24, 0, 80, value => commitMutation(() => { node.props.gap = value; }))));
            inspector.append(field('Skriftstørrelse', numberInput(node.props.fontSize || 16, 10, 40, value => commitMutation(() => { node.props.fontSize = value; }))));
            inspector.append(field('Skriftvægt', selectInput([
                ['400', 'Normal'],
                ['500', 'Medium'],
                ['600', 'Semibold'],
                ['700', 'Fed']
            ], String(node.props.fontWeight || 600), value => commitMutation(() => { node.props.fontWeight = Number.parseInt(value, 10); }))));
            inspector.append(field('Tekstfarve', colorControl(node.props.textColor || '#222222', value => commitMutation(() => { node.props.textColor = value; }))));
            inspector.append(field('Hoverfarve', colorControl(node.props.hoverColor || '#2271b1', value => commitMutation(() => { node.props.hoverColor = value; }))));
            inspector.append(field('Baggrund', colorControl(node.props.background === 'transparent' ? '#ffffff' : (node.props.background || '#ffffff'), value => commitMutation(() => { node.props.background = value; }))));
            inspector.append(field('Undermenu-baggrund', colorControl(node.props.submenuBackground || '#ffffff', value => commitMutation(() => { node.props.submenuBackground = value; }))));
            inspector.append(field('Undermenu-tekst', colorControl(node.props.submenuTextColor || '#222222', value => commitMutation(() => { node.props.submenuTextColor = value; }))));
            inspector.append(field('Mobilknap tekst', textInput(node.props.toggleLabel || 'Menu', value => commitMutation(() => { node.props.toggleLabel = value; }))));
        }

""" + inspector_marker
replace_once(js, inspector_marker, nav_inspector)

core = ROOT / 'src' / 'Core' / 'Plugin.php'
replace_once(core, "use VisualDesignerManager\\Admin\\GalleryController;\n", "use VisualDesignerManager\\Admin\\GalleryController;\nuse VisualDesignerManager\\Admin\\NavigationController;\n")
replace_once(core, "use VisualDesignerManager\\Admin\\SiteDesignController;\n", "use VisualDesignerManager\\Admin\\SiteDesignController;\nuse VisualDesignerManager\\Admin\\SiteSettingsController;\n")
replace_once(core, "        SiteDesignController::register();\n", "        SiteDesignController::register();\n        SiteSettingsController::register();\n        NavigationController::register();\n")

admin = ROOT / 'src' / 'Admin' / 'AdminController.php'
replace_once(
    admin,
    "        $siteDesignUrl = admin_url('admin.php?page=' . SiteDesignController::MENU_SLUG);\n",
    "        $siteDesignUrl = admin_url('admin.php?page=' . SiteDesignController::MENU_SLUG);\n        $siteSettingsUrl = admin_url('admin.php?page=' . SiteSettingsController::MENU_SLUG);\n        $navigationUrl = admin_url('admin.php?page=' . NavigationController::MENU_SLUG);\n",
)
replace_once(
    admin,
    "            echo '<a class=\"button\" href=\"' . esc_url($siteDesignUrl) . '\">Site Design</a>';\n",
    "            echo '<a class=\"button\" href=\"' . esc_url($siteDesignUrl) . '\">Site Design</a> ';\n            if (current_user_can('manage_options')) {\n                echo '<a class=\"button\" href=\"' . esc_url($siteSettingsUrl) . '\">Siteindstillinger</a> ';\n            }\n            echo '<a class=\"button\" href=\"' . esc_url($navigationUrl) . '\">Navigation</a>';\n",
)

main = ROOT / 'visual-designer-manager.php'
replace_once(main, ' * Version: 2.0.0-beta.1\n', ' * Version: 2.0.0-beta.2\n')
replace_once(main, "define('VDM_VERSION', '2.0.0-beta.1');", "define('VDM_VERSION', '2.0.0-beta.2');")

css_path = ROOT / 'assets' / 'frontend.css'
css = css_path.read_text(encoding='utf-8')
if '.vdm-navigation{' not in css:
    css += r'''
.vdm-node--navigation{overflow:visible;z-index:5}
.vdm-navigation{position:relative;width:100%;min-width:0;background:var(--vdm-navigation-background,transparent);color:var(--vdm-navigation-text,#222);font-size:var(--vdm-navigation-font-size,16px);font-weight:var(--vdm-navigation-font-weight,600)}
.vdm-navigation-panel{width:100%;min-width:0}
.vdm-navigation-menu,.vdm-navigation-menu .sub-menu{list-style:none;margin:0;padding:0}
.vdm-navigation-menu{display:flex;align-items:center;justify-content:var(--vdm-navigation-justify,flex-start);gap:var(--vdm-navigation-gap,24px);min-width:0}
.vdm-navigation--vertical .vdm-navigation-menu{flex-direction:column;align-items:stretch}
.vdm-navigation-menu li{position:relative;margin:0;padding:0}
.vdm-navigation-menu a{display:flex;align-items:center;min-height:40px;color:var(--vdm-navigation-text,#222)!important;text-decoration:none;line-height:1.25;white-space:nowrap}
.vdm-navigation-menu a:hover,.vdm-navigation-menu a:focus,.vdm-navigation-menu .current-menu-item>a,.vdm-navigation-menu .current-menu-ancestor>a{color:var(--vdm-navigation-hover,#2271b1)!important}
.vdm-navigation-menu .sub-menu{display:none;position:absolute;z-index:100;left:0;top:100%;min-width:210px;padding:8px 0;background:var(--vdm-navigation-submenu-background,#fff);box-shadow:0 6px 20px rgba(0,0,0,.14)}
.vdm-navigation-menu .sub-menu .sub-menu{left:100%;top:0}
.vdm-navigation--vertical .vdm-navigation-menu>.menu-item>.sub-menu{left:100%;top:0}
.vdm-navigation-menu li:hover>.sub-menu,.vdm-navigation-menu li:focus-within>.sub-menu{display:block}
.vdm-navigation-menu .sub-menu a{min-height:36px;padding:6px 14px;color:var(--vdm-navigation-submenu-text,#222)!important}
.vdm-navigation-menu .sub-menu a:hover,.vdm-navigation-menu .sub-menu a:focus,.vdm-navigation-menu .sub-menu .current-menu-item>a{color:var(--vdm-navigation-hover,#2271b1)!important}
.vdm-navigation-toggle{display:none;align-items:center;gap:9px;min-height:42px;padding:8px 12px;border:1px solid currentColor;border-radius:4px;background:transparent;color:var(--vdm-navigation-text,#222);font:inherit;font-weight:inherit;cursor:pointer}
.vdm-navigation-toggle-icon{display:grid;gap:3px;width:18px}
.vdm-navigation-toggle-icon span{display:block;height:2px;background:currentColor}
.vdm-navigation-placeholder{display:flex;align-items:center;min-height:48px;padding:10px 12px;border:1px dashed #a7aaad;background:#f6f7f7;color:#50575e;font-size:14px;font-weight:400;box-sizing:border-box}
@media (max-width:782px){
    .vdm-navigation-toggle{display:inline-flex}
    .vdm-navigation-panel{display:none;margin-top:8px}
    .vdm-navigation.is-open .vdm-navigation-panel{display:block}
    .vdm-navigation-menu,.vdm-navigation--vertical .vdm-navigation-menu{flex-direction:column;align-items:stretch;justify-content:flex-start;gap:0}
    .vdm-navigation-menu a{min-height:42px;padding:4px 8px;white-space:normal}
    .vdm-navigation-menu .sub-menu,.vdm-navigation-menu .sub-menu .sub-menu,.vdm-navigation--vertical .vdm-navigation-menu>.menu-item>.sub-menu{display:block;position:static;min-width:0;padding:0 0 0 16px;background:transparent;box-shadow:none}
    .vdm-navigation-menu .sub-menu a{padding:4px 8px;color:var(--vdm-navigation-text,#222)!important}
}
#vdm-canvas[data-vdm-breakpoint="mobile"] .vdm-navigation-toggle{display:inline-flex}
#vdm-canvas[data-vdm-breakpoint="mobile"] .vdm-navigation-panel{display:none;margin-top:8px}
#vdm-canvas[data-vdm-breakpoint="mobile"] .vdm-navigation.is-open .vdm-navigation-panel{display:block}
#vdm-canvas[data-vdm-breakpoint="mobile"] .vdm-navigation-menu{flex-direction:column;align-items:stretch;justify-content:flex-start;gap:0}
#vdm-canvas[data-vdm-breakpoint="mobile"] .vdm-navigation-menu a{min-height:42px;padding:4px 8px;white-space:normal}
#vdm-canvas[data-vdm-breakpoint="mobile"] .vdm-navigation-menu .sub-menu{display:block;position:static;min-width:0;padding:0 0 0 16px;background:transparent;box-shadow:none}
'''
    css_path.write_text(css, encoding='utf-8')

print('Applied beta.2 navigation and Site Settings migration')
