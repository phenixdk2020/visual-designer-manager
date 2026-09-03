from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]

plugin = (ROOT / 'visual-designer-manager.php').read_text(encoding='utf-8')
core = (ROOT / 'src' / 'Core' / 'Plugin.php').read_text(encoding='utf-8')
admin = (ROOT / 'src' / 'Admin' / 'AdminController.php').read_text(encoding='utf-8')
transfer_admin = (ROOT / 'src' / 'Admin' / 'TransferController.php').read_text(encoding='utf-8')
package = (ROOT / 'src' / 'Transfer' / 'PortablePackage.php').read_text(encoding='utf-8')
exporter = (ROOT / 'src' / 'Transfer' / 'PortableExporter.php').read_text(encoding='utf-8')
importer = (ROOT / 'src' / 'Transfer' / 'PortableImporter.php').read_text(encoding='utf-8')
qa = (ROOT / '.github' / 'workflows' / 'qa.yml').read_text(encoding='utf-8')

version_match = re.search(r'Version:\s*2\.0\.0(?:-(alpha|beta|rc)\.(\d+))?', plugin)
version_ok = False
if version_match:
    phase = version_match.group(1)
    number = int(version_match.group(2) or 0)
    if phase is None or phase == 'rc':
        version_ok = True
    elif phase == 'beta':
        version_ok = number >= 3

checks = {
    'runtime version beta.3 or newer': version_ok,
    'native V2 portable identity': all(token in package for token in (
        "public const FORMAT = 'Visual Designer Manager Portable Site';",
        "public const SCHEMA_VERSION = '2.0';",
        "'site.json'",
        "'content/pages.json'",
        "'content/events.json'",
        "'content/vehicles.json'",
        "'content/galleries.json'",
        "'content/menus.json'",
        "'templates/header.json'",
        "'templates/footer.json'",
        "'settings/site-design.json'",
        "'media/index.json'",
    )),
    'package path and zip hardening': all(token in package for token in (
        'public static function safePath(',
        "str_contains($path, \"\\0\")",
        "str_contains($path, '\\\\')",
        "$part === '..'",
        'Duplicate ZIP path rejected',
        'Symbolic links are not allowed',
        'Unlisted ZIP entry rejected',
        'MAX_ENTRY_BYTES',
        'MAX_TOTAL_BYTES',
    )),
    'manifest integrity': all(token in package for token in (
        'public static function contentHash(',
        "hash_init('sha256')",
        'hash_equals($sha, $calculated)',
        "contentSha256",
        'hashEntry($zip, $path, $size)',
    )),
    'native schema gate': "if ($schema === '1.0')" in package and 'beta.3 accepts native V2 schema 2.0 packages' in package,
    'complete V2 exporter': all(token in exporter for token in (
        'LayoutRepository::get($id)',
        'TemplateRepository::get(TemplateRepository::HEADER)',
        'TemplateRepository::get(TemplateRepository::FOOTER)',
        'SiteDesignRepository::get()',
        'SiteSettingsRepository::get()',
        'EventRepository::POST_TYPE',
        'VehicleRepository::POST_TYPE',
        'GalleryRepository::POST_TYPE',
        'wp_get_nav_menus',
        'mediaRecords(array_keys($mediaIds))',
        'PortablePackage::contentHash($fileRecords)',
        'PortablePackage::inspect($zipPath)',
    )),
    'export media index does not leak local paths': all(token in exporter for token in (
        '$mediaPayload = []',
        "unset($publicRecord['_filePath']);",
        "'media/index.json' => PortablePackage::json(['items' => $mediaPayload])",
    )),
    'import prevalidates before mutation': 'PortablePackage::inspect($zipPath);' in importer and 'validatePayloads(' in importer,
    'media integrity and dedupe': all(token in importer for token in (
        "private const MEDIA_SHA_META = '_vdm_portable_sha256';",
        'findAttachmentByHash(',
        "hash_init('sha256')",
        'hash_equals($sha, hash_final($hash))',
        'wp_handle_sideload(',
        'wp_generate_attachment_metadata(',
    )),
    'idempotent source maps': all(token in importer for token in (
        "private const SOURCE_ID_META = '_vdm_portable_source_id';",
        "private const SOURCE_SITE_META = '_vdm_portable_source_site';",
        'findPostBySource(',
        'findMenuBySource(',
        'findMenuItemBySource(',
    )),
    'canonical repositories on import': all(token in importer for token in (
        'EventRepository::save($postId, $record)',
        'VehicleRepository::save($postId, $record)',
        'GalleryRepository::save($targetId, $galleryData)',
        'LayoutRepository::save(',
        'TemplateRepository::save(',
        'SiteDesignRepository::save($siteDesign)',
        'SiteSettingsRepository::save([',
    )),
    'document remapping': all(token in importer for token in (
        '$type === NodeSchema::IMAGE',
        "$props['attachmentId']",
        '$type === NodeSchema::NAVIGATION',
        "$props['menuId']",
        '$type === NodeSchema::TEXT',
        '$type === NodeSchema::BUTTON',
        'remapContent(',
        'remapUrl(',
    )),
    'menu object and hierarchy remapping': all(token in importer for token in (
        'wp_update_nav_menu_item(',
        "$postMap[$sourceObjectId]",
        "'menu-item-parent-id'",
        '$itemMap[$parentSourceId]',
    )),
    'site identity and reading remapping': all(token in importer for token in (
        "$mediaMap[(int) ($identity['logoSourceId'] ?? 0)]",
        "$mediaMap[(int) ($identity['siteIconSourceId'] ?? 0)]",
        "$pageMap[$frontSourceId]",
        "$pageMap[$postsSourceId]",
        "update_option('permalink_structure'",
    )),
    'guarded cleanup on failure': 'rollbackCreated($createdMenuItems, $createdMenus, $createdPosts)' in importer and 'wp_delete_nav_menu' in importer and 'wp_delete_post' in importer,
    'transfer admin actions and capability': all(token in transfer_admin for token in (
        "public const MENU_SLUG = 'vdm-transfer';",
        "'manage_options'",
        'admin_post_vdm_export_portable_site',
        'admin_post_vdm_import_preflight',
        'admin_post_vdm_import_portable_site',
        "check_admin_referer('vdm_export_portable_site')",
        "check_admin_referer('vdm_import_preflight')",
        "check_admin_referer('vdm_import_portable_site_' . $token)",
    )),
    'preflight user and file binding': all(token in transfer_admin for token in (
        "'userId' => get_current_user_id()",
        "'sha256' => $sha",
        'get_transient(self::PREFLIGHT_PREFIX . $token)',
        "(int) ($state['userId'] ?? 0) !== get_current_user_id()",
        'hash_file(\'sha256\', $path)',
        'hash_equals($expectedSha, strtolower($currentSha))',
    )),
    'package is revalidated before import': transfer_admin.count('PortablePackage::inspect(') >= 2 and 'PortableImporter::import($path, get_current_user_id())' in transfer_admin,
    'safe transfer redirects and staged cleanup': 'wp_safe_redirect($url, 303)' in transfer_admin and 'cleanupStagedFiles()' in transfer_admin and "@chmod($stagedPath, 0600)" in transfer_admin,
    'beta3 boot and dashboard link': 'TransferController::register();' in core and 'TransferController::MENU_SLUG' in admin,
    'beta3 permanent QA': 'Beta 3 Portable transfer contract' in qa and 'python3 tests/beta3_portable_transfer_contract.py' in qa,
}

failed = [name for name, ok in checks.items() if not ok]
if failed:
    print('Beta 3 Portable transfer contract: FAIL')
    for name in failed:
        print(' - ' + name)
    sys.exit(1)

print('Beta 3 Portable transfer contract: PASS')
