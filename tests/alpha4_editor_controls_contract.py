from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]

plugin = (ROOT / 'visual-designer-manager.php').read_text(encoding='utf-8')
designer = (ROOT / 'assets' / 'designer.js').read_text(encoding='utf-8')
css = (ROOT / 'assets' / 'designer.css').read_text(encoding='utf-8')
schema = (ROOT / 'src' / 'Model' / 'NodeSchema.php').read_text(encoding='utf-8')
renderer = (ROOT / 'src' / 'Frontend' / 'Renderer.php').read_text(encoding='utf-8')
controller = (ROOT / 'src' / 'Admin' / 'DesignerController.php').read_text(encoding='utf-8')

version_match = re.search(r'Version:\s*2\.0\.0(?:-(alpha|beta|rc)\.(\d+))?', plugin)
version_ok = bool(version_match)
if version_match and version_match.group(1) == 'alpha':
    version_ok = int(version_match.group(2) or 0) >= 4

theme_config_ok = (
    "'themeColors' => self::themeColors()" in controller
    or "'themeColors' => DesignerController::themeColors()" in controller
)
compact_schema = re.sub(r'\s+', '', schema)

checks = {
    'runtime version alpha.4 or newer': version_ok,
    'wordpress media library': 'wp_enqueue_media();' in controller and "function openMediaLibrary(" in designer and "wp.media({" in designer,
    'theme color discovery': theme_config_ok and 'wp_get_global_settings' in controller and 'collectColors' in controller,
    'compact color trigger': 'function colorControl(' in designer and 'function openColorPopover(' in designer and 'vdm-color-trigger' in css and 'vdm-color-popover' in css,
    'picker modes': "toggle.textContent = mode === 'theme' ? 'Farvevælger' : 'Tema';" in designer and "cancel.textContent = 'Annuller';" in designer and "apply.textContent = 'Anvend';" in designer,
    'theme and recent colors': "themeTitle.textContent = 'Temafarver';" in designer and "recentTitle.textContent = 'Senest brugt';" in designer and 'vdm_recent_colors_v2' in designer,
    'no native color controls': "input.type = 'color'" not in designer,
    'rich text editor': 'function richTextControl(' in designer and "editor.contentEditable = 'true';" in designer and "document.execCommand('createLink'" in designer,
    'button schema controls': "'target'=>'_self'" in compact_schema and "'align'=>'left'" in compact_schema and "'paddingX'=>18" in compact_schema and "'fontWeight'=>600" in compact_schema,
    'button renderer parity': "--vdm-button-padding-x:" in renderer and "--vdm-button-font-size:" in renderer and "--vdm-button-justify:" in renderer and 'noopener noreferrer' in renderer,
}

failed = [name for name, ok in checks.items() if not ok]

if failed:
    print('Alpha 4 editor controls contract: FAIL')
    for name in failed:
        print(' - ' + name)
    sys.exit(1)

print('Alpha 4 editor controls contract: PASS')
