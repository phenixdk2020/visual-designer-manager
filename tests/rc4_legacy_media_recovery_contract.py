from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]

plugin = (ROOT / 'visual-designer-manager.php').read_text(encoding='utf-8')
migrator = (ROOT / 'src/Transfer/SchemaOneMigrator.php').read_text(encoding='utf-8')
recovery = (ROOT / 'src/Transfer/LegacyMediaRecovery.php').read_text(encoding='utf-8')
controller = (ROOT / 'src/Admin/TransferController.php').read_text(encoding='utf-8')

failures = []


def require(condition: bool, message: str) -> None:
    if not condition:
        failures.append(message)


match = re.search(r'Version:\s*2\.0\.0-rc\.(\d+)', plugin)
require(bool(match and int(match.group(1)) >= 4), 'plugin version is not RC.4 or newer')
require("define('VDM_VERSION', '2.0.0-rc.4');" in plugin or bool(re.search(r"define\('VDM_VERSION', '2\.0\.0-rc\.([4-9]|[1-9][0-9]+)'\);", plugin)), 'runtime version is not RC.4 or newer')

for token in (
    'final class LegacyMediaRecovery',
    "'/wp-content/uploads/'",
    'wp_http_validate_url',
    'wp_safe_remote_get',
    "'limit_response_size' => self::MAX_REMOTE_BYTES + 1",
    'wp_check_filetype_and_ext',
    "hash_file('sha256'",
    "'media/files/recovered/'",
    "'mediaId'",
    'sourceBases',
):
    require(token in recovery, f'legacy media recovery missing marker: {token}')

require('wp_insert_attachment' not in recovery, 'preflight recovery must not mutate WordPress media')
require('LegacyMediaRecovery::recover(' in migrator, 'schema one migrator does not invoke media recovery')
require("'recoveredMedia'" in migrator, 'migration metadata does not report recovered media')
require("'unresolvedMedia'" in migrator, 'migration metadata does not report unresolved media')
require("$recoveredTempFiles[$archive]" in migrator, 'recovered media files are not copied into the converted V2 ZIP')
require("self::row('Genfundne legacy-billeder'" in controller, 'preflight does not display recovered media count')
require("self::row('Uafklarede legacy-billeder'" in controller, 'preflight does not display unresolved media count')

if failures:
    print('RC.4 legacy media recovery contract: FAIL')
    for failure in failures:
        print(' - ' + failure)
    sys.exit(1)

print('RC.4 legacy media recovery contract: PASS')
