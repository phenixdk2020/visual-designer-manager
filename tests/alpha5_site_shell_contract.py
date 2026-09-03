from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]

plugin = (ROOT / 'visual-designer-manager.php').read_text(encoding='utf-8')
core = (ROOT / 'src' / 'Core' / 'Plugin.php').read_text(encoding='utf-8')
templates = (ROOT / 'src' / 'Storage' / 'TemplateRepository.php').read_text(encoding='utf-8')
site_design = (ROOT / 'src' / 'Storage' / 'SiteDesignRepository.php').read_text(encoding='utf-8')
template_controller = (ROOT / 'src' / 'Admin' / 'TemplateDesignerController.php').read_text(encoding='utf-8')
site_controller = (ROOT / 'src' / 'Admin' / 'SiteDesignController.php').read_text(encoding='utf-8')
rest = (ROOT / 'src' / 'Http' / 'RestController.php').read_text(encoding='utf-8')
frontend = (ROOT / 'src' / 'Frontend' / 'FrontendController.php').read_text(encoding='utf-8')
shell = (ROOT / 'templates' / 'page-shell.php').read_text(encoding='utf-8')
css = (ROOT / 'assets' / 'frontend.css').read_text(encoding='utf-8')

version_match = re.search(r'Version:\s*2\.0\.0(?:-(alpha|beta|rc)\.(\d+))?', plugin)
version_ok = bool(version_match)
if version_match and version_match.group(1) == 'alpha':
    version_ok = int(version_match.group(2) or 0) >= 5

checks = {
    'runtime version alpha.5 or newer': version_ok,
    'template repository': "public const HEADER = 'header';" in templates and "public const FOOTER = 'footer';" in templates and "vdm_template_" in templates and "_history_v2" in templates,
    'header footer designer': "public const MENU_SLUG = 'vdm-header-footer';" in template_controller and "'pageId' => 'global-' . $slot" in template_controller and 'TemplateRepository::version($slot)' in template_controller,
    'template REST adapter': "'/layouts/(?P<id>[a-z0-9-]+)'" in rest and "'global-header'" in rest and "'global-footer'" in rest and 'TemplateRepository::save' in rest,
    'site design repository': "public const OPTION = 'vdm_site_design_v2';" in site_design and "'shellEnabled' => false" in site_design and "'headingColor'" in site_design and 'cssVariables' in site_design,
    'site design admin': "public const MENU_SLUG = 'vdm-site-design';" in site_controller and 'vdm_save_site_design' in site_controller and 'Gem Site Design' in site_controller,
    'controllers booted': 'TemplateDesignerController::register();' in core and 'SiteDesignController::register();' in core,
    'frontend site shell routing': "add_filter('template_include'" in frontend and "templates/page-shell.php" in frontend and "shellEnabled" in frontend,
    'canonical shell template': 'wp_head();' in shell and 'wp_footer();' in shell and 'TemplateRepository::HEADER' in shell and 'TemplateRepository::FOOTER' in shell and 'Renderer::render($vdmPageDocument)' in shell,
    'global CSS variables': '--vdm-site-max-width' in css and '--vdm-site-heading' in css and '.vdm-site-region' in css and '.vdm-site-shell-active' in css,
}

failed = [name for name, ok in checks.items() if not ok]
if failed:
    print('Alpha 5 site shell contract: FAIL')
    for name in failed:
        print(' - ' + name)
    sys.exit(1)

print('Alpha 5 site shell contract: PASS')
