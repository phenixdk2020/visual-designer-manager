from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]

plugin = (ROOT / 'visual-designer-manager.php').read_text(encoding='utf-8')
core = (ROOT / 'src' / 'Core' / 'Plugin.php').read_text(encoding='utf-8')
schema = (ROOT / 'src' / 'Model' / 'NodeSchema.php').read_text(encoding='utf-8')
renderer = (ROOT / 'src' / 'Frontend' / 'Renderer.php').read_text(encoding='utf-8')
nav_renderer = (ROOT / 'src' / 'Frontend' / 'NavigationRenderer.php').read_text(encoding='utf-8')
nav_repo = (ROOT / 'src' / 'Navigation' / 'NavigationRepository.php').read_text(encoding='utf-8')
frontend = (ROOT / 'src' / 'Frontend' / 'FrontendController.php').read_text(encoding='utf-8')
event_frontend = (ROOT / 'src' / 'Frontend' / 'EventFrontendController.php').read_text(encoding='utf-8')
vehicle_frontend = (ROOT / 'src' / 'Frontend' / 'VehicleFrontendController.php').read_text(encoding='utf-8')
gallery_frontend = (ROOT / 'src' / 'Frontend' / 'GalleryFrontendController.php').read_text(encoding='utf-8')
designer_controller = (ROOT / 'src' / 'Admin' / 'DesignerController.php').read_text(encoding='utf-8')
template_controller = (ROOT / 'src' / 'Admin' / 'TemplateDesignerController.php').read_text(encoding='utf-8')
nav_controller = (ROOT / 'src' / 'Admin' / 'NavigationController.php').read_text(encoding='utf-8')
settings_controller = (ROOT / 'src' / 'Admin' / 'SiteSettingsController.php').read_text(encoding='utf-8')
settings_repo = (ROOT / 'src' / 'Storage' / 'SiteSettingsRepository.php').read_text(encoding='utf-8')
admin = (ROOT / 'src' / 'Admin' / 'AdminController.php').read_text(encoding='utf-8')
designer = (ROOT / 'assets' / 'designer.js').read_text(encoding='utf-8')
frontend_js = (ROOT / 'assets' / 'frontend.js').read_text(encoding='utf-8')
settings_js = (ROOT / 'assets' / 'site-settings.js').read_text(encoding='utf-8')
css = (ROOT / 'assets' / 'frontend.css').read_text(encoding='utf-8')

version_match = re.search(r'Version:\s*2\.0\.0(?:-(alpha|beta|rc)\.(\d+))?', plugin)
version_ok = False
if version_match:
    phase = version_match.group(1)
    number = int(version_match.group(2) or 0)
    if phase is None or phase == 'rc':
        version_ok = True
    elif phase == 'beta':
        version_ok = number >= 2

compact_schema = re.sub(r'\s+', '', schema)

checks = {
    'runtime version beta.2 or newer': version_ok,
    'navigation node schema': (
        "publicconstNAVIGATION='navigation';" in compact_schema
        and "self::NAVIGATION=>['x'=>0,'y'=>0,'w'=>12,'h'=>8]" in compact_schema
        and "'menuId'=>0" in compact_schema
        and "'orientation'=>'horizontal'" in compact_schema
        and "'toggleLabel'=>'Menu'" in compact_schema
        and "$type===self::NAVIGATION" in compact_schema
        and "'menuId'=>absint" in compact_schema
        and "['horizontal','vertical']" in compact_schema
        and "['left','center','right']" in compact_schema
    ),
    'WordPress menu repository': 'wp_get_nav_menus' in nav_repo and 'wp_get_nav_menu_items' in nav_repo and 'wp_get_nav_menu_object' in nav_repo,
    'canonical navigation renderer': all(token in nav_renderer for token in (
        'NavigationRepository::exists', 'wp_nav_menu([', "'fallback_cb' => false",
        'data-vdm-navigation-toggle', 'aria-expanded="false"', 'data-vdm-navigation-panel',
        'Vælg en WordPress-menu i Inspector.',
    )),
    'navigation renderer wired': 'NodeSchema::NAVIGATION' in renderer and 'NavigationRenderer::render' in renderer,
    'navigation style variables': all(token in renderer for token in (
        '--vdm-navigation-gap:', '--vdm-navigation-font-size:', '--vdm-navigation-font-weight:',
        '--vdm-navigation-text:', '--vdm-navigation-hover:', '--vdm-navigation-submenu-background:',
        '--vdm-navigation-justify:',
    )),
    'navigation in both Designers': "'navigation' => 'Navigation'" in designer_controller and "'navigation' => 'Navigation'" in template_controller,
    'menus localized in both Designers': "'navigationMenus' => NavigationRepository::choices()" in designer_controller and "'navigationMenus' => NavigationRepository::choices()" in template_controller,
    'frontend runtime loaded in both Designers': 'assets/frontend.js' in designer_controller and 'assets/frontend.js' in template_controller and "'vdm-frontend-runtime'" in designer_controller and "'vdm-frontend-runtime'" in template_controller,
    'Designer navigation defaults and inspector': all(token in designer for token in (
        'navigation: 8', "navigation: {x: 0, y: 0, w: 12, h: 8}", "navigation: {menuId: 0",
        "node.type === 'navigation'", "field('WordPress-menu'", "field('Retning'",
        "field('Hoverfarve'", "field('Mobilknap tekst'", 'config.navigationMenus',
    )),
    'navigation responsive CSS': all(token in css for token in (
        '.vdm-navigation{', '.vdm-navigation-menu', '.vdm-navigation-toggle',
        '.vdm-navigation.is-open .vdm-navigation-panel', '@media (max-width:782px)',
        '#vdm-canvas[data-vdm-breakpoint="mobile"] .vdm-navigation-toggle',
        '#vdm-canvas[data-vdm-breakpoint="mobile"] .vdm-navigation.is-open .vdm-navigation-panel',
    )),
    'navigation frontend runtime': all(token in frontend_js for token in (
        '[data-vdm-navigation-toggle]', 'aria-expanded', "event.key !== 'Escape'", "matchMedia('(min-width: 783px)')",
    )),
    'shared frontend asset registration': (
        "wp_register_script('vdm-frontend-runtime'" in frontend
        and 'public static function enqueueAssets()' in frontend
        and "wp_enqueue_script('vdm-frontend-runtime')" in frontend
    ),
    'detail controllers use shared assets': all('FrontendController::enqueueAssets();' in text for text in (event_frontend, vehicle_frontend, gallery_frontend)),
    'site settings repository': all(token in settings_repo for token in (
        "public const ORGANIZATION_OPTION = 'vdm_organization_name';",
        "public const CONTACT_EMAIL_OPTION = 'vdm_contact_email';",
        "public const CONTACT_PHONE_OPTION = 'vdm_contact_phone';",
        "public const LOGO_OPTION = 'vdm_site_logo_id';",
        "update_option('blogname'", "update_option('blogdescription'", "update_option('site_icon'", 'wp_attachment_is_image',
    )),
    'VDM logo independent from theme': 'set_theme_mod' not in settings_repo and 'custom_logo' not in settings_repo,
    'site settings admin security': all(token in settings_controller for token in (
        "public const MENU_SLUG = 'vdm-site-settings';", "'manage_options'", 'admin_post_vdm_save_site_settings',
        "check_admin_referer('vdm_save_site_settings')", 'SiteSettingsRepository::save($raw)',
        'wp_safe_redirect($url, 303)', 'wp_enqueue_media()',
    )),
    'site settings fields': all(token in settings_controller for token in (
        'Webstedstitel', 'Slogan', 'Virksomhed / forening', 'Kontakt-e-mail', 'Kontakttelefon',
        'VDM-logo', 'Site-ikon / favicon', 'Hjemadresse', 'WordPress-adresse'
    )),
    'site settings media JS': 'wp.media({' in settings_js and '.vdm-site-media-select' in settings_js and '.vdm-site-media-clear' in settings_js,
    'navigation admin page': (
        "public const MENU_SLUG = 'vdm-navigation';" in nav_controller
        and 'wp_get_nav_menus' in nav_controller
        and 'wp_get_nav_menu_items' in nav_controller
        and 'wp_update_nav_menu_item' in nav_controller
        and 'MAX_HISTORY = 30' in nav_controller
        and 'restoreSnapshot' in nav_controller
        and 'theme locations' in nav_controller.lower()
    ),
    'beta.2 controllers booted': 'SiteSettingsController::register();' in core and 'NavigationController::register();' in core,
    'dashboard links': 'SiteSettingsController::MENU_SLUG' in admin and 'NavigationController::MENU_SLUG' in admin,
}

failed = [name for name, ok in checks.items() if not ok]
if failed:
    print('Beta 2 Navigation and Site Settings contract: FAIL')
    for name in failed:
        print(' - ' + name)
    sys.exit(1)

print('Beta 2 Navigation and Site Settings contract: PASS')
