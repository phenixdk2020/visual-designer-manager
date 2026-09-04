from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]

plugin = (ROOT / 'visual-designer-manager.php').read_text(encoding='utf-8')
core = (ROOT / 'src/Core/Plugin.php').read_text(encoding='utf-8')
updater = (ROOT / 'src/Update/GitHubUpdater.php').read_text(encoding='utf-8')
admin = (ROOT / 'src/Admin/ParityController.php').read_text(encoding='utf-8')
package = (ROOT / '.github/workflows/package.yml').read_text(encoding='utf-8')

failures = []

def require(condition: bool, message: str) -> None:
    if not condition:
        failures.append(message)

require('Version: 2.0.0-rc.3' in plugin, 'plugin header is not RC.3')
require("define('VDM_VERSION', '2.0.0-rc.3');" in plugin, 'VDM_VERSION is not RC.3')
require('use VisualDesignerManager\\Update\\GitHubUpdater;' in core, 'Plugin bootstrap does not import GitHubUpdater')
require('GitHubUpdater::register();' in core, 'Plugin bootstrap does not register GitHubUpdater')

require("private const MANIFEST_URL = 'https://raw.githubusercontent.com/phenixdk2020/visual-designer-manager/main/update.json';" in updater, 'updater manifest URL is not the VDM repository')
require("private const PLUGIN_FILE = 'visual-designer-manager/visual-designer-manager.php';" in updater, 'updater plugin identity is wrong')
require("private const SLUG = 'visual-designer-manager';" in updater, 'updater slug is wrong')
require("add_filter('pre_set_site_transient_update_plugins'" in updater, 'WordPress update transient integration is missing')
require("add_filter('plugins_api'" in updater, 'WordPress plugin information integration is missing')
require("add_filter('upgrader_pre_download'" in updater, 'pre-download verification hook is missing')
require("add_filter('upgrader_pre_install'" in updater, 'pre-install backup hook is missing')
require("hash_file('sha256'" in updater and 'hash_equals($expected, $actual)' in updater, 'SHA-256 package verification is missing')
require('createProgramBackup' in updater and 'ZipArchive' in updater, 'program backup before update is missing')
require("current_user_can('update_plugins')" in updater, 'manual update capability check is missing')
require('wp_nonce_url' in updater and 'check_admin_referer' in updater, 'manual update nonce protection is missing')
require("raw.githubusercontent.com" in updater and "validateManifest" in updater, 'manifest host validation is missing')

require('use VisualDesignerManager\\Update\\GitHubUpdater;' in admin, 'Opdateringer page does not import GitHubUpdater')
require('GitHubUpdater::status(' in admin, 'Opdateringer page does not show GitHub status')
require('GitHubUpdater::checkButtonHtml()' in admin, 'Opdateringer page has no manual check button')
require('GitHubUpdater::installButtonHtml()' in admin, 'Opdateringer page has no install button')

require('permissions:\n  contents: write' in package, 'package workflow cannot publish updater payload')
require('Publish updater package and manifest' in package, 'package workflow does not publish updater payload')
require("Path('update.json').write_text" in package, 'package workflow does not create update.json')
require("dist/visual-designer-manager-v${VERSION}.zip" in package, 'package workflow does not publish the versioned ZIP')
require("sha256" in package.lower(), 'package workflow does not calculate a checksum')
require("[skip package]" in package, 'package workflow recursion guard is missing')

if failures:
    print('RC.3 GitHub updater contract: FAIL')
    for failure in failures:
        print(' - ' + failure)
    sys.exit(1)

print('RC.3 GitHub updater contract: PASS')
