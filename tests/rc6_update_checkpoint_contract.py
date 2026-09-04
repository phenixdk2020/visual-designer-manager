from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    target = ROOT / path
    if not target.is_file():
        raise SystemExit(f'RC.6 contract: missing file {path}')
    return target.read_text(encoding='utf-8')


plugin = read('visual-designer-manager.php')
core = read('src/Core/Plugin.php')
manager = read('src/Update/UpdateCheckpointManager.php')
controller = read('src/Admin/UpdateCheckpointController.php')
updater = read('src/Update/GitHubUpdater.php')

checks = [
    ('RC.6 version header', 'Version: 2.0.0-rc.6' in plugin and "define('VDM_VERSION', '2.0.0-rc.6')" in plugin),
    ('checkpoint manager registered', 'UpdateCheckpointManager::register();' in core),
    ('checkpoint admin registered', 'UpdateCheckpointController::register();' in core),
    ('runs after program backup', "add_filter('upgrader_pre_install', [self::class, 'checkpointBeforeInstall'], 30, 2);" in manager),
    ('stops on previous updater error', 'if (is_wp_error($response)' in manager),
    ('full portable VDM export', 'PortableExporter::build()' in manager),
    ('portable package validation', 'PortablePackage::inspect($dataTarget)' in manager),
    ('program SHA-256 retained', "'programSha256' => $programSha" in manager),
    ('data SHA-256 retained', "'dataSha256' => $dataSha" in manager),
    ('twelve checkpoint retention', 'private const MAX_CHECKPOINTS = 12;' in manager),
    ('program and data files pruned together', "foreach (['programFile', 'dataFile'] as $field)" in manager),
    ('legacy program backups visible', 'legacyProgramBackups()' in manager and "'storage' => 'legacy'" in manager),
    ('download action protected', "current_user_can('update_plugins')" in manager and 'check_admin_referer(self::DOWNLOAD_NONCE)' in manager),
    ('updates page replacement', 'replaceUpdatesPage' in controller and 'remove_action($hook, [ParityController::class, \'updates\'])' in controller),
    ('V1-style checkpoint columns', '<th>Fra</th><th>Til</th><th>Dato</th><th>Program</th><th>VDM-data</th><th>Handlinger</th>' in controller),
    ('download controls', 'Download program' in controller and 'Download VDM-data' in controller),
    ('updater still verifies package SHA-256', "hash_equals($expected, $actual)" in updater),
    ('updater still stops if program backup fails', 'vdm_program_backup_failed' in updater),
]

failed = [name for name, ok in checks if not ok]
if failed:
    print('RC.6 update checkpoint contract: FAIL')
    for name in failed:
        print(f' - {name}')
    sys.exit(1)

print('RC.6 update checkpoint contract: PASS')
