<?php

declare(strict_types=1);

namespace VisualDesignerManager\Update;

use VisualDesignerManager\Diagnostics\DiagnosticStore;

final class GitHubUpdater
{
    private const MANIFEST_URL = 'https://raw.githubusercontent.com/phenixdk2020/visual-designer-manager/main/update.json';
    private const CACHE_KEY = 'vdm_github_update_manifest_v1';
    private const CHECK_ACTION = 'vdm_check_update';
    private const CHECK_NONCE = 'vdm_check_update';
    private const INSTALL_ACTION = 'vdm_install_update';
    private const INSTALL_NONCE = 'vdm_install_update';
    private const SLUG = 'visual-designer-manager';
    private const PLUGIN_FILE = 'visual-designer-manager/visual-designer-manager.php';
    private const BACKUP_DIR = 'vdm-program-backups';
    private const MAX_BACKUPS = 8;

    private static bool $backupCreatedThisRequest = false;

    private function __construct()
    {
    }

    public static function register(): void
    {
        add_filter('pre_set_site_transient_update_plugins', [self::class, 'injectUpdate']);
        add_filter('plugins_api', [self::class, 'pluginInfo'], 20, 3);
        add_filter('upgrader_pre_download', [self::class, 'verifyDownload'], 20, 4);
        add_filter('upgrader_pre_install', [self::class, 'backupBeforeInstall'], 20, 2);
        add_action('upgrader_process_complete', [self::class, 'clearCache'], 10, 2);
        add_action('admin_post_' . self::CHECK_ACTION, [self::class, 'manualCheck']);
        add_action('admin_post_' . self::INSTALL_ACTION, [self::class, 'installNow']);
    }

    public static function injectUpdate(mixed $transient): mixed
    {
        if (!is_object($transient) || !isset($transient->checked) || !is_array($transient->checked)) {
            return $transient;
        }

        $manifest = self::manifest();
        if ($manifest === null) {
            return $transient;
        }

        $item = self::updateObject($manifest);
        if (version_compare((string) $manifest['version'], VDM_VERSION, '>')) {
            $transient->response[self::PLUGIN_FILE] = $item;
            if (isset($transient->no_update[self::PLUGIN_FILE])) {
                unset($transient->no_update[self::PLUGIN_FILE]);
            }
        } else {
            $transient->no_update[self::PLUGIN_FILE] = $item;
            if (isset($transient->response[self::PLUGIN_FILE])) {
                unset($transient->response[self::PLUGIN_FILE]);
            }
        }

        return $transient;
    }

    public static function pluginInfo(mixed $result, string $action, mixed $args): mixed
    {
        if ($action !== 'plugin_information' || !is_object($args) || (string) ($args->slug ?? '') !== self::SLUG) {
            return $result;
        }

        $manifest = self::manifest();
        if ($manifest === null) {
            return $result;
        }

        $info = new \stdClass();
        $info->name = (string) ($manifest['name'] ?? 'Visual Designer Manager');
        $info->slug = self::SLUG;
        $info->version = (string) $manifest['version'];
        $info->author = '<a href="https://github.com/phenixdk2020/visual-designer-manager">Visual Designer Manager</a>';
        $info->homepage = (string) ($manifest['homepage'] ?? 'https://github.com/phenixdk2020/visual-designer-manager');
        $info->requires = (string) ($manifest['requires'] ?? '6.4');
        $info->requires_php = (string) ($manifest['requires_php'] ?? '8.0');
        $info->tested = (string) ($manifest['tested'] ?? '');
        $info->download_link = (string) $manifest['package'];
        $info->sections = is_array($manifest['sections'] ?? null) ? $manifest['sections'] : [
            'description' => 'Visual Designer Manager.',
            'changelog' => '',
        ];
        return $info;
    }

    public static function verifyDownload(mixed $reply, string $package, mixed $upgrader, array $hookExtra = []): mixed
    {
        if ($reply !== false) {
            return $reply;
        }
        if (($hookExtra['plugin'] ?? '') !== self::PLUGIN_FILE) {
            return false;
        }

        $manifest = self::manifest(true);
        if ($manifest === null || $package !== (string) $manifest['package']) {
            return new \WP_Error('vdm_update_package_untrusted', 'VDM-opdateringspakken matcher ikke det publicerede GitHub-manifest.');
        }

        $expected = strtolower((string) ($manifest['sha256'] ?? ''));
        if (preg_match('/^[a-f0-9]{64}$/', $expected) !== 1) {
            return new \WP_Error('vdm_update_hash_missing', 'GitHub-manifestet mangler en gyldig SHA-256 for opdateringspakken.');
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        $file = download_url($package, 300, false);
        if (is_wp_error($file)) {
            return $file;
        }

        $actual = strtolower((string) hash_file('sha256', $file));
        if (!hash_equals($expected, $actual)) {
            @unlink($file);
            DiagnosticStore::add('error', 'GitHub-opdatering blev afvist på grund af SHA-256 mismatch.', [
                'expected' => $expected,
                'actual' => $actual,
            ]);
            return new \WP_Error('vdm_update_hash_mismatch', 'Visual Designer Manager-opdateringen blev afvist: SHA-256 matcher ikke GitHub-manifestet.');
        }

        return $file;
    }

    public static function backupBeforeInstall(mixed $response, array $hookExtra = []): mixed
    {
        if (is_wp_error($response) || self::$backupCreatedThisRequest) {
            return $response;
        }

        $plugin = (string) ($hookExtra['plugin'] ?? '');
        $plugins = is_array($hookExtra['plugins'] ?? null) ? $hookExtra['plugins'] : [];
        if ($plugin !== self::PLUGIN_FILE && !in_array(self::PLUGIN_FILE, $plugins, true)) {
            return $response;
        }

        $manifest = self::manifest();
        $target = is_array($manifest) ? (string) ($manifest['version'] ?? '') : '';
        $backup = self::createProgramBackup($target);
        if (is_wp_error($backup)) {
            DiagnosticStore::add('error', 'Programbackup før opdatering fejlede.', ['message' => $backup->get_error_message()]);
            return new \WP_Error(
                'vdm_program_backup_failed',
                'Visual Designer Manager-opdateringen blev stoppet, fordi programbackup ikke kunne oprettes: ' . $backup->get_error_message()
            );
        }

        self::$backupCreatedThisRequest = true;
        DiagnosticStore::add('info', 'Programbackup oprettet før GitHub-opdatering.', [
            'target' => $target,
            'file' => basename((string) $backup),
        ]);
        return $response;
    }

    public static function manualCheck(): void
    {
        if (!current_user_can('update_plugins')) {
            wp_die(esc_html__('Ingen adgang til plugin-opdateringer.', 'visual-designer-manager'));
        }
        check_admin_referer(self::CHECK_NONCE);

        self::clearCache();
        delete_site_transient('update_plugins');
        wp_clean_plugins_cache(true);
        require_once ABSPATH . 'wp-admin/includes/update.php';
        wp_update_plugins();

        $status = self::status(true);
        DiagnosticStore::add($status['ok'] ? 'info' : 'warning', 'Manuel GitHub-opdateringskontrol gennemført.', [
            'current' => $status['current'],
            'latest' => $status['latest'],
            'available' => $status['available'] ? 'yes' : 'no',
        ]);

        self::redirect([
            'vdm_update_check' => $status['ok'] ? ($status['available'] ? 'available' : 'current') : 'error',
            'vdm_update_version' => $status['latest'],
        ]);
    }

    public static function installNow(): void
    {
        if (!current_user_can('update_plugins')) {
            wp_die(esc_html__('Ingen adgang til plugin-opdateringer.', 'visual-designer-manager'));
        }
        check_admin_referer(self::INSTALL_NONCE);

        $status = self::status(true);
        if (!$status['ok'] || !$status['available']) {
            self::redirect([
                'vdm_update_check' => $status['ok'] ? 'current' : 'error',
                'vdm_update_version' => $status['latest'],
            ]);
        }

        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/update.php';

        delete_site_transient('update_plugins');
        wp_clean_plugins_cache(true);
        wp_update_plugins();

        $skin = new \Automatic_Upgrader_Skin();
        $upgrader = new \Plugin_Upgrader($skin);
        $result = $upgrader->upgrade(self::PLUGIN_FILE);
        if (is_wp_error($result) || $result === false) {
            $message = is_wp_error($result) ? $result->get_error_message() : 'WordPress kunne ikke gennemføre opdateringen.';
            DiagnosticStore::add('error', 'GitHub-opdatering fejlede.', ['message' => $message]);
            self::redirect(['vdm_update_check' => 'install-error', 'vdm_update_message' => $message]);
        }

        if (!is_plugin_active(self::PLUGIN_FILE)) {
            activate_plugin(self::PLUGIN_FILE, '', false, true);
        }
        self::clearCache();
        DiagnosticStore::add('info', 'Visual Designer Manager blev opdateret fra GitHub.', ['version' => $status['latest']]);
        self::redirect(['vdm_update_check' => 'installed', 'vdm_update_version' => $status['latest']]);
    }

    /** @return array{ok:bool,current:string,latest:string,available:bool,message:string} */
    public static function status(bool $force = false): array
    {
        $manifest = self::manifest($force);
        if ($manifest === null) {
            return [
                'ok' => false,
                'current' => VDM_VERSION,
                'latest' => '',
                'available' => false,
                'message' => 'GitHub-manifestet kunne ikke hentes eller valideres.',
            ];
        }

        $latest = (string) $manifest['version'];
        return [
            'ok' => true,
            'current' => VDM_VERSION,
            'latest' => $latest,
            'available' => version_compare($latest, VDM_VERSION, '>'),
            'message' => '',
        ];
    }

    public static function checkButtonHtml(): string
    {
        if (!current_user_can('update_plugins')) {
            return '';
        }
        $url = wp_nonce_url(admin_url('admin-post.php?action=' . self::CHECK_ACTION), self::CHECK_NONCE);
        return '<a class="button" href="' . esc_url($url) . '">↻ Tjek GitHub-opdatering</a>';
    }

    public static function installButtonHtml(): string
    {
        if (!current_user_can('update_plugins')) {
            return '';
        }
        $status = self::status();
        if (!$status['ok'] || !$status['available']) {
            return '';
        }
        $url = wp_nonce_url(admin_url('admin-post.php?action=' . self::INSTALL_ACTION), self::INSTALL_NONCE);
        return '<a class="button button-primary" href="' . esc_url($url) . '">Opdater til ' . esc_html($status['latest']) . '</a>';
    }

    public static function clearCache(mixed $upgrader = null, mixed $options = null): void
    {
        delete_site_transient(self::CACHE_KEY);
        delete_site_transient('update_plugins');
    }

    /** @return array<string,mixed>|null */
    private static function manifest(bool $force = false): ?array
    {
        if (!$force) {
            $cached = get_site_transient(self::CACHE_KEY);
            if (is_array($cached) && self::validateManifest($cached)) {
                return $cached;
            }
        }

        $response = wp_remote_get(self::MANIFEST_URL, [
            'timeout' => 15,
            'redirection' => 3,
            'headers' => ['Accept' => 'application/json'],
            'user-agent' => 'Visual-Designer-Manager/' . VDM_VERSION,
        ]);
        if (is_wp_error($response)) {
            return null;
        }
        if ((int) wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $decoded = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($decoded) || !self::validateManifest($decoded)) {
            return null;
        }

        set_site_transient(self::CACHE_KEY, $decoded, 30 * MINUTE_IN_SECONDS);
        return $decoded;
    }

    /** @param array<string,mixed> $manifest */
    private static function validateManifest(array $manifest): bool
    {
        $version = (string) ($manifest['version'] ?? '');
        $slug = (string) ($manifest['slug'] ?? '');
        $package = (string) ($manifest['package'] ?? '');
        $sha = strtolower((string) ($manifest['sha256'] ?? ''));
        if ($slug !== self::SLUG || $version === '' || $package === '') {
            return false;
        }
        if (preg_match('/^\d+\.\d+\.\d+(?:-(?:alpha|beta|rc)\.\d+)?$/', $version) !== 1) {
            return false;
        }
        if (preg_match('/^[a-f0-9]{64}$/', $sha) !== 1) {
            return false;
        }
        $parts = wp_parse_url($package);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || strtolower((string) ($parts['host'] ?? '')) !== 'raw.githubusercontent.com') {
            return false;
        }
        $path = (string) ($parts['path'] ?? '');
        return str_starts_with($path, '/phenixdk2020/visual-designer-manager/main/dist/visual-designer-manager-v')
            && str_ends_with($path, '.zip');
    }

    /** @param array<string,mixed> $manifest */
    private static function updateObject(array $manifest): object
    {
        $item = new \stdClass();
        $item->id = 'https://github.com/phenixdk2020/visual-designer-manager';
        $item->slug = self::SLUG;
        $item->plugin = self::PLUGIN_FILE;
        $item->new_version = (string) $manifest['version'];
        $item->url = (string) ($manifest['homepage'] ?? 'https://github.com/phenixdk2020/visual-designer-manager');
        $item->package = (string) $manifest['package'];
        $item->requires = (string) ($manifest['requires'] ?? '6.4');
        $item->requires_php = (string) ($manifest['requires_php'] ?? '8.0');
        $item->tested = (string) ($manifest['tested'] ?? '');
        return $item;
    }

    /** @return string|\WP_Error */
    private static function createProgramBackup(string $targetVersion): string|\WP_Error
    {
        if (!class_exists(\ZipArchive::class)) {
            return new \WP_Error('vdm_backup_zip_missing', 'PHP ZipArchive er ikke tilgængelig.');
        }

        $uploads = wp_upload_dir();
        if (!empty($uploads['error'])) {
            return new \WP_Error('vdm_backup_upload_error', (string) $uploads['error']);
        }
        $base = trailingslashit((string) $uploads['basedir']) . self::BACKUP_DIR;
        if (!wp_mkdir_p($base)) {
            return new \WP_Error('vdm_backup_directory_failed', 'Backupmappen kunne ikke oprettes.');
        }

        $safeTarget = sanitize_file_name($targetVersion !== '' ? $targetVersion : 'unknown');
        $filename = 'visual-designer-manager-' . sanitize_file_name(VDM_VERSION) . '-before-' . $safeTarget . '-' . gmdate('Ymd-His') . '.zip';
        $target = trailingslashit($base) . $filename;
        $zip = new \ZipArchive();
        if ($zip->open($target, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return new \WP_Error('vdm_backup_open_failed', 'Programbackup-ZIP kunne ikke oprettes.');
        }

        try {
            $root = realpath(VDM_DIR);
            if (!is_string($root) || $root === '') {
                throw new \RuntimeException('Pluginmappen kunne ikke findes.');
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($iterator as $file) {
                if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                    continue;
                }
                $real = $file->getRealPath();
                if (!is_string($real) || !str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
                    continue;
                }
                $relative = 'visual-designer-manager/' . str_replace(DIRECTORY_SEPARATOR, '/', substr($real, strlen($root) + 1));
                if (!$zip->addFile($real, $relative)) {
                    throw new \RuntimeException('Kunne ikke tilføje ' . $relative . ' til programbackup.');
                }
            }
        } catch (\Throwable $error) {
            $zip->close();
            @unlink($target);
            return new \WP_Error('vdm_backup_build_failed', $error->getMessage());
        }
        $zip->close();

        if (!is_file($target) || (int) filesize($target) <= 0) {
            @unlink($target);
            return new \WP_Error('vdm_backup_empty', 'Programbackup blev tom.');
        }

        self::pruneBackups($base);
        return $target;
    }

    private static function pruneBackups(string $directory): void
    {
        $files = glob(trailingslashit($directory) . 'visual-designer-manager-*.zip');
        if (!is_array($files) || count($files) <= self::MAX_BACKUPS) {
            return;
        }
        usort($files, static fn(string $a, string $b): int => ((int) @filemtime($b)) <=> ((int) @filemtime($a)));
        foreach (array_slice($files, self::MAX_BACKUPS) as $file) {
            @unlink($file);
        }
    }

    /** @param array<string,string> $args */
    private static function redirect(array $args): never
    {
        $url = add_query_arg($args, admin_url('admin.php?page=vdm-updates'));
        wp_safe_redirect($url);
        exit;
    }
}
