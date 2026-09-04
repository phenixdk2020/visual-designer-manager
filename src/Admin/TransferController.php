<?php

declare(strict_types=1);

namespace VisualDesignerManager\Admin;

use VisualDesignerManager\Transfer\PortableExporter;
use VisualDesignerManager\Transfer\PortableImporter;
use VisualDesignerManager\Transfer\PortablePackage;
use VisualDesignerManager\Transfer\SchemaOneMigrator;

final class TransferController
{
    public const MENU_SLUG = 'vdm-transfer';
    private const PREFLIGHT_PREFIX = 'vdm_transfer_preflight_';
    private const RESULT_PREFIX = 'vdm_transfer_result_';
    private const PREFLIGHT_TTL = 1800;

    private function __construct()
    {
    }

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'menu'], 25);
        add_action('admin_post_vdm_export_portable_site', [self::class, 'export']);
        add_action('admin_post_vdm_import_preflight', [self::class, 'preflight']);
        add_action('admin_post_vdm_import_portable_site', [self::class, 'import']);
    }

    public static function menu(): void
    {
        add_submenu_page(
            AdminController::MENU_SLUG,
            'Eksport / import',
            'Eksport / import',
            'manage_options',
            self::MENU_SLUG,
            [self::class, 'render']
        );
    }

    public static function export(): void
    {
        self::assertAccess();
        check_admin_referer('vdm_export_portable_site');

        $packagePath = '';
        try {
            $package = PortableExporter::build();
            $packagePath = (string) ($package['path'] ?? '');
            $filename = sanitize_file_name((string) ($package['filename'] ?? 'visual-designer-site.zip'));
            if ($packagePath === '' || !is_file($packagePath) || !is_readable($packagePath)) {
                throw new \RuntimeException('Den genererede eksportfil kunne ikke læses.');
            }

            $size = filesize($packagePath);
            if (!is_int($size) || $size < 0) {
                throw new \RuntimeException('Eksportfilens størrelse kunne ikke bestemmes.');
            }

            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            nocache_headers();
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . (string) $size);
            header('X-Content-Type-Options: nosniff');
            readfile($packagePath);
            @unlink($packagePath);
            exit;
        } catch (\Throwable $exception) {
            if ($packagePath !== '' && is_file($packagePath)) {
                @unlink($packagePath);
            }
            self::storeResult(['error' => $exception->getMessage()]);
            self::redirect(['export_error' => '1']);
        }
    }

    public static function preflight(): void
    {
        self::assertAccess();
        check_admin_referer('vdm_import_preflight');
        self::cleanupStagedFiles();

        $upload = $_FILES['vdm_portable_zip'] ?? null;
        if (!is_array($upload)) {
            self::storeResult(['error' => 'Vælg en VDM portable ZIP-fil.']);
            self::redirect(['preflight_error' => '1']);
        }

        $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        $uploadedPath = (string) ($upload['tmp_name'] ?? '');
        if ($error !== UPLOAD_ERR_OK || $uploadedPath === '' || !is_uploaded_file($uploadedPath)) {
            self::storeResult(['error' => self::uploadError($error)]);
            self::redirect(['preflight_error' => '1']);
        }

        $token = strtolower(wp_generate_password(32, false, false));
        $stagedPath = trailingslashit(get_temp_dir()) . 'vdm-import-' . $token . '.zip';
        if (!move_uploaded_file($uploadedPath, $stagedPath)) {
            self::storeResult(['error' => 'Importfilen kunne ikke flyttes til VDMs midlertidige preflight-område.']);
            self::redirect(['preflight_error' => '1']);
        }
        @chmod($stagedPath, 0600);

        $migration = null;
        try {
            try {
                $inspection = PortablePackage::inspect($stagedPath);
            } catch (\Throwable $nativeError) {
                try {
                    $converted = SchemaOneMigrator::convert($stagedPath);
                } catch (\Throwable $migrationError) {
                    throw new \RuntimeException(
                        'Pakken er hverken en gyldig native V2-pakke eller en migrerbar schema 1.0-pakke. ' .
                        'V2-validering: ' . $nativeError->getMessage() . ' Schema 1.0-validering: ' . $migrationError->getMessage()
                    );
                }

                $convertedPath = (string) ($converted['path'] ?? '');
                $convertedInspection = $converted['inspection'] ?? null;
                if ($convertedPath === '' || !is_file($convertedPath) || !is_array($convertedInspection)) {
                    throw new \RuntimeException('Schema 1.0-konverteringen returnerede ikke en gyldig native V2-pakke.');
                }
                @unlink($stagedPath);
                $stagedPath = $convertedPath;
                $inspection = $convertedInspection;
                $migration = is_array($converted['migration'] ?? null) ? $converted['migration'] : [];
            }

            $sha = hash_file('sha256', $stagedPath);
            if (!is_string($sha)) {
                throw new \RuntimeException('Importfilens SHA-256 kunne ikke beregnes.');
            }

            set_transient(self::PREFLIGHT_PREFIX . $token, [
                'userId' => get_current_user_id(),
                'path' => $stagedPath,
                'sha256' => $sha,
                'inspection' => $inspection,
                'migration' => $migration,
                'createdAt' => time(),
            ], self::PREFLIGHT_TTL);

            self::redirect(['preflight' => $token]);
        } catch (\Throwable $exception) {
            @unlink($stagedPath);
            self::storeResult(['error' => $exception->getMessage()]);
            self::redirect(['preflight_error' => '1']);
        }
    }

    public static function import(): void
    {
        self::assertAccess();
        $token = isset($_POST['vdm_preflight_token']) ? sanitize_key((string) wp_unslash($_POST['vdm_preflight_token'])) : '';
        if ($token === '') {
            self::storeResult(['error' => 'Preflight-token mangler. Upload pakken igen.']);
            self::redirect(['import_error' => '1']);
        }
        check_admin_referer('vdm_import_portable_site_' . $token);

        $state = get_transient(self::PREFLIGHT_PREFIX . $token);
        if (!is_array($state) || (int) ($state['userId'] ?? 0) !== get_current_user_id()) {
            self::storeResult(['error' => 'Preflight er udløbet eller tilhører en anden bruger. Upload pakken igen.']);
            self::redirect(['import_error' => '1']);
        }

        $path = (string) ($state['path'] ?? '');
        try {
            if ($path === '' || !is_file($path) || !is_readable($path)) {
                throw new \RuntimeException('Den validerede importfil findes ikke længere.');
            }
            $currentSha = hash_file('sha256', $path);
            $expectedSha = strtolower((string) ($state['sha256'] ?? ''));
            if (!is_string($currentSha) || preg_match('/^[a-f0-9]{64}$/', $expectedSha) !== 1 || !hash_equals($expectedSha, strtolower($currentSha))) {
                throw new \RuntimeException('Importfilen er ændret siden preflight og blev afvist.');
            }

            // Defense in depth: the complete package is revalidated immediately before import.
            PortablePackage::inspect($path);
            $summary = PortableImporter::import($path, get_current_user_id());

            delete_transient(self::PREFLIGHT_PREFIX . $token);
            @unlink($path);
            self::storeResult(['success' => true, 'summary' => $summary]);
            self::redirect(['imported' => '1']);
        } catch (\Throwable $exception) {
            delete_transient(self::PREFLIGHT_PREFIX . $token);
            if ($path !== '' && is_file($path)) {
                @unlink($path);
            }
            self::storeResult(['error' => $exception->getMessage()]);
            self::redirect(['import_error' => '1']);
        }
    }

    public static function render(): void
    {
        self::assertAccess();
        self::cleanupStagedFiles();
        $result = self::takeResult();

        echo '<div class="wrap">';
        echo '<h1>Visual Designer Manager · Eksport / import</h1>';
        echo '<p>Portable V2-sitepakker flytter VDM-layouts, Header/Footer, Site Design, Siteindstillinger, indhold, WordPress-menuer og refererede originale mediefiler.</p>';

        if (isset($result['error']) && is_string($result['error']) && $result['error'] !== '') {
            echo '<div class="notice notice-error"><p><strong>Fejl:</strong> ' . esc_html($result['error']) . '</p></div>';
        } elseif (!empty($result['success']) && is_array($result['summary'] ?? null)) {
            $summary = $result['summary'];
            echo '<div class="notice notice-success"><p><strong>Import gennemført.</strong> ';
            echo esc_html(self::summaryText(array_map('intval', $summary))) . '</p></div>';
        }

        echo '<hr><h2>Eksportér dette site</h2>';
        echo '<p>Eksporten bygger en ny ZIP og validerer dens manifest, filstørrelser og SHA-256-værdier før download.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="vdm_export_portable_site">';
        wp_nonce_field('vdm_export_portable_site');
        echo '<p><button type="submit" class="button button-primary">Opret portable V2 ZIP</button></p>';
        echo '</form>';

        echo '<hr><h2>Importér portable V2 ZIP</h2>';
        echo '<p>Import foregår altid i to trin. Først valideres pakken uden at ændre sitet. Derefter skal preflight-resultatet godkendes eksplicit.</p>';
        echo '<div class="notice notice-info inline"><p><strong>RC.4:</strong> Native V2 schema <code>' . esc_html(PortablePackage::SCHEMA_VERSION) . '</code> importeres direkte. Validerede schema 1.0-sitepakker konverteres isoleret til native V2. URL-baserede legacy-billeder uden media-ID forsøges genfundet sikkert fra uploadstien under preflight og pakkes lokalt før import.</p></div>';
        echo '<form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="vdm_import_preflight">';
        wp_nonce_field('vdm_import_preflight');
        echo '<p><input type="file" name="vdm_portable_zip" accept=".zip,application/zip" required></p>';
        echo '<p><button type="submit" class="button">Kør preflight</button></p>';
        echo '</form>';

        $token = isset($_GET['preflight']) ? sanitize_key((string) wp_unslash($_GET['preflight'])) : '';
        if ($token !== '') {
            self::renderPreflight($token);
        }

        echo '</div>';
    }

    private static function renderPreflight(string $token): void
    {
        $state = get_transient(self::PREFLIGHT_PREFIX . $token);
        if (!is_array($state) || (int) ($state['userId'] ?? 0) !== get_current_user_id()) {
            echo '<div class="notice notice-error inline"><p>Preflight er udløbet. Upload pakken igen.</p></div>';
            return;
        }

        $inspection = is_array($state['inspection'] ?? null) ? $state['inspection'] : [];
        $manifest = is_array($inspection['manifest'] ?? null) ? $inspection['manifest'] : [];
        $summary = is_array($inspection['summary'] ?? null) ? array_map('intval', $inspection['summary']) : [];
        $source = is_array($manifest['source'] ?? null) ? $manifest['source'] : [];
        $migration = is_array($state['migration'] ?? null) ? $state['migration'] : [];
        $migrationWarnings = is_array($migration['warnings'] ?? null) ? array_values(array_filter(array_map('strval', $migration['warnings']))) : [];

        echo '<hr><h2>Preflight godkendt</h2>';
        echo '<table class="widefat striped" style="max-width:900px"><tbody>';
        self::row('Format', (string) ($manifest['format'] ?? ''));
        self::row('Schema', (string) ($manifest['schemaVersion'] ?? ''));
        self::row('VDM-kildeversion', (string) ($manifest['managerVersion'] ?? ''));
        self::row('Kildesite', (string) ($source['name'] ?? ''));
        if ($migration !== []) {
            self::row('Kildeschema', (string) ($migration['sourceSchemaVersion'] ?? '1.0'));
            self::row('Konverteret til', (string) ($manifest['schemaVersion'] ?? PortablePackage::SCHEMA_VERSION));
            self::row('Kilde-VDM', (string) ($migration['sourceManagerVersion'] ?? ''));
            self::row('Genfundne legacy-billeder', (string) max(0, (int) ($migration['recoveredMedia'] ?? 0)));
            self::row('Uafklarede legacy-billeder', (string) max(0, (int) ($migration['unresolvedMedia'] ?? 0)));
        }
        self::row('Content SHA-256', (string) ($manifest['contentSha256'] ?? ''));
        self::row('Indhold', self::summaryText($summary));
        echo '</tbody></table>';
        if ($migrationWarnings !== []) {
            echo '<h3>Migrationsbemærkninger</h3><ul>';
            foreach ($migrationWarnings as $warning) {
                echo '<li>' . esc_html($warning) . '</li>';
            }
            echo '</ul>';
        }
        echo '<p><strong>Preflight har ikke ændret WordPress.</strong> Ved import remappes kilde-ID’er til mål-ID’er. Allerede importerede objekter fra samme kilde kan genbruges/opdateres.</p>';
        echo '<div class="notice notice-warning inline"><p>På et site med eksisterende indhold anbefales en frisk backup før import. Nyoprettede objekter ryddes op ved en importfejl, mens opdateringer af allerede eksisterende målobjekter ikke kan garanteres fuldt tilbagerullet.</p></div>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="vdm_import_portable_site">';
        echo '<input type="hidden" name="vdm_preflight_token" value="' . esc_attr($token) . '">';
        wp_nonce_field('vdm_import_portable_site_' . $token);
        echo '<p><button type="submit" class="button button-primary">Importér den validerede pakke</button></p>';
        echo '</form>';
    }

    private static function row(string $label, string $value): void
    {
        echo '<tr><th style="width:220px">' . esc_html($label) . '</th><td><code>' . esc_html($value) . '</code></td></tr>';
    }

    /** @param array<string,int> $summary */
    private static function summaryText(array $summary): string
    {
        $labels = [
            'pages' => 'sider',
            'events' => 'events',
            'vehicles' => 'køretøjer',
            'galleries' => 'albummer',
            'menus' => 'menuer',
            'media' => 'medier',
        ];
        $parts = [];
        foreach ($labels as $key => $label) {
            $parts[] = (int) ($summary[$key] ?? 0) . ' ' . $label;
        }
        if (array_key_exists('mediaCreated', $summary) || array_key_exists('mediaReused', $summary)) {
            $parts[] = (int) ($summary['mediaCreated'] ?? 0) . ' medier oprettet';
            $parts[] = (int) ($summary['mediaReused'] ?? 0) . ' medier genbrugt';
        }
        return implode(' · ', $parts);
    }

    private static function storeResult(array $result): void
    {
        set_transient(self::RESULT_PREFIX . get_current_user_id(), $result, 300);
    }

    /** @return array<string,mixed> */
    private static function takeResult(): array
    {
        $key = self::RESULT_PREFIX . get_current_user_id();
        $result = get_transient($key);
        delete_transient($key);
        return is_array($result) ? $result : [];
    }

    /** @param array<string,string> $args */
    private static function redirect(array $args): void
    {
        $url = add_query_arg(array_merge(['page' => self::MENU_SLUG], $args), admin_url('admin.php'));
        wp_safe_redirect($url, 303);
        exit;
    }

    private static function assertAccess(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Du har ikke adgang til Eksport / import.', 'visual-designer-manager'));
        }
    }

    private static function cleanupStagedFiles(): void
    {
        $paths = glob(trailingslashit(get_temp_dir()) . 'vdm-import-*.zip');
        if (!is_array($paths)) {
            return;
        }
        $threshold = time() - 7200;
        foreach ($paths as $path) {
            if (!is_string($path) || !is_file($path)) {
                continue;
            }
            $modified = filemtime($path);
            if (is_int($modified) && $modified < $threshold) {
                @unlink($path);
            }
        }
    }

    private static function uploadError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'ZIP-filen er større end serverens uploadgrænse.',
            UPLOAD_ERR_PARTIAL => 'ZIP-filen blev kun delvist uploadet.',
            UPLOAD_ERR_NO_FILE => 'Ingen ZIP-fil blev valgt.',
            UPLOAD_ERR_NO_TMP_DIR => 'Serveren mangler en midlertidig uploadmappe.',
            UPLOAD_ERR_CANT_WRITE => 'Serveren kunne ikke skrive uploadfilen.',
            UPLOAD_ERR_EXTENSION => 'En PHP-udvidelse stoppede uploaden.',
            default => 'Uploaden fejlede med kode ' . $code . '.',
        };
    }
}
