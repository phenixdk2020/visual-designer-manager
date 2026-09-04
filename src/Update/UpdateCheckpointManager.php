<?php

declare(strict_types=1);

namespace VisualDesignerManager\Update;

use VisualDesignerManager\Diagnostics\DiagnosticStore;
use VisualDesignerManager\Transfer\PortableExporter;
use VisualDesignerManager\Transfer\PortablePackage;

final class UpdateCheckpointManager
{
    private const HISTORY_OPTION = 'vdm_update_checkpoints_v1';
    private const CHECKPOINT_DIR = 'vdm-update-checkpoints';
    private const LEGACY_PROGRAM_DIR = 'vdm-program-backups';
    private const DOWNLOAD_ACTION = 'vdm_download_update_checkpoint';
    private const DOWNLOAD_NONCE = 'vdm_download_update_checkpoint';
    private const MAX_CHECKPOINTS = 12;

    private static int $requestStartedAt = 0;
    private static bool $checkpointCreatedThisRequest = false;

    private function __construct()
    {
    }

    public static function register(): void
    {
        self::$requestStartedAt = time();
        add_filter('upgrader_pre_install', [self::class, 'checkpointBeforeInstall'], 30, 2);
        add_action('admin_post_' . self::DOWNLOAD_ACTION, [self::class, 'download']);
    }

    public static function checkpointBeforeInstall(mixed $response, array $hookExtra = []): mixed
    {
        if (is_wp_error($response) || self::$checkpointCreatedThisRequest) {
            return $response;
        }

        $plugin = (string) ($hookExtra['plugin'] ?? '');
        $plugins = is_array($hookExtra['plugins'] ?? null) ? $hookExtra['plugins'] : [];
        if ($plugin !== 'visual-designer-manager/visual-designer-manager.php'
            && !in_array('visual-designer-manager/visual-designer-manager.php', $plugins, true)) {
            return $response;
        }

        $status = GitHubUpdater::status(true);
        $targetVersion = $status['ok'] ? (string) $status['latest'] : 'unknown';
        $programSource = self::findProgramBackup($targetVersion);
        if ($programSource === '') {
            DiagnosticStore::add('error', 'VDM-data-checkpoint blev stoppet, fordi programbackup ikke kunne findes.', [
                'current' => VDM_VERSION,
                'target' => $targetVersion,
            ]);
            return new \WP_Error(
                'vdm_checkpoint_program_missing',
                'Visual Designer Manager-opdateringen blev stoppet, fordi den netop oprettede programbackup ikke kunne findes.'
            );
        }

        $result = self::createCheckpoint($programSource, $targetVersion);
        if (is_wp_error($result)) {
            DiagnosticStore::add('error', 'VDM-data-checkpoint før opdatering fejlede.', [
                'current' => VDM_VERSION,
                'target' => $targetVersion,
                'message' => $result->get_error_message(),
            ]);
            return new \WP_Error(
                'vdm_update_checkpoint_failed',
                'Visual Designer Manager-opdateringen blev stoppet, fordi VDM-data-checkpointet ikke kunne oprettes: ' . $result->get_error_message()
            );
        }

        self::$checkpointCreatedThisRequest = true;
        DiagnosticStore::add('info', 'Komplet update-checkpoint blev oprettet før GitHub-opdatering.', [
            'from' => VDM_VERSION,
            'to' => $targetVersion,
            'program' => (string) ($result['programFile'] ?? ''),
            'data' => (string) ($result['dataFile'] ?? ''),
        ]);
        return $response;
    }

    /** @return list<array<string,mixed>> */
    public static function checkpoints(): array
    {
        $stored = get_option(self::HISTORY_OPTION, []);
        $rows = is_array($stored) ? array_values(array_filter($stored, 'is_array')) : [];
        $knownProgramFiles = [];
        foreach ($rows as $row) {
            $file = basename((string) ($row['programFile'] ?? ''));
            if ($file !== '') {
                $knownProgramFiles[$file] = true;
            }
        }

        foreach (self::legacyProgramBackups() as $legacy) {
            $file = basename((string) ($legacy['programFile'] ?? ''));
            if ($file === '' || isset($knownProgramFiles[$file])) {
                continue;
            }
            $rows[] = $legacy;
        }

        usort($rows, static function (array $a, array $b): int {
            return strcmp((string) ($b['createdUtc'] ?? ''), (string) ($a['createdUtc'] ?? ''));
        });
        return array_slice($rows, 0, self::MAX_CHECKPOINTS);
    }

    public static function downloadUrl(string $id, string $kind): string
    {
        if (!in_array($kind, ['program', 'data'], true) || $id === '') {
            return '';
        }
        return wp_nonce_url(
            add_query_arg([
                'action' => self::DOWNLOAD_ACTION,
                'checkpoint' => $id,
                'kind' => $kind,
            ], admin_url('admin-post.php')),
            self::DOWNLOAD_NONCE
        );
    }

    public static function download(): never
    {
        if (!current_user_can('update_plugins')) {
            wp_die(esc_html__('Ingen adgang til update-checkpoints.', 'visual-designer-manager'));
        }
        check_admin_referer(self::DOWNLOAD_NONCE);

        $id = sanitize_key((string) ($_GET['checkpoint'] ?? ''));
        $kind = sanitize_key((string) ($_GET['kind'] ?? ''));
        if ($id === '' || !in_array($kind, ['program', 'data'], true)) {
            wp_die(esc_html__('Ugyldigt update-checkpoint.', 'visual-designer-manager'));
        }

        $selected = null;
        foreach (self::checkpoints() as $row) {
            if ((string) ($row['id'] ?? '') === $id) {
                $selected = $row;
                break;
            }
        }
        if (!is_array($selected)) {
            wp_die(esc_html__('Update-checkpointet blev ikke fundet.', 'visual-designer-manager'));
        }

        $field = $kind === 'program' ? 'programFile' : 'dataFile';
        $filename = basename((string) ($selected[$field] ?? ''));
        if ($filename === '') {
            wp_die(esc_html__('Den valgte checkpointfil findes ikke.', 'visual-designer-manager'));
        }

        $storage = (string) ($selected['storage'] ?? 'checkpoint');
        $directory = $storage === 'legacy' ? self::legacyProgramDirectory() : self::checkpointDirectory();
        $path = trailingslashit($directory) . $filename;
        $realDirectory = realpath($directory);
        $realPath = realpath($path);
        if (!is_string($realDirectory) || !is_string($realPath)
            || !str_starts_with($realPath, $realDirectory . DIRECTORY_SEPARATOR)
            || !is_file($realPath) || !is_readable($realPath)) {
            wp_die(esc_html__('Checkpointfilen kunne ikke læses.', 'visual-designer-manager'));
        }

        $size = filesize($realPath);
        if (!is_int($size) || $size <= 0) {
            wp_die(esc_html__('Checkpointfilen er tom.', 'visual-designer-manager'));
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        nocache_headers();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
        header('Content-Length: ' . (string) $size);
        header('X-Content-Type-Options: nosniff');
        readfile($realPath);
        exit;
    }

    /** @return array<string,mixed>|\WP_Error */
    private static function createCheckpoint(string $programSource, string $targetVersion): array|\WP_Error
    {
        $directory = self::checkpointDirectory();
        if ($directory === '') {
            return new \WP_Error('vdm_checkpoint_directory_failed', 'Checkpointmappen kunne ikke oprettes.');
        }

        $programFilename = basename($programSource);
        $programTarget = trailingslashit($directory) . $programFilename;
        if (!self::moveFile($programSource, $programTarget)) {
            return new \WP_Error('vdm_checkpoint_program_move_failed', 'Programbackup kunne ikke flyttes til checkpointmappen.');
        }

        $programSize = filesize($programTarget);
        $programSha = strtolower((string) hash_file('sha256', $programTarget));
        if (!is_int($programSize) || $programSize <= 0 || preg_match('/^[a-f0-9]{64}$/', $programSha) !== 1) {
            return new \WP_Error('vdm_checkpoint_program_invalid', 'Programbackup kunne ikke valideres efter flytning.');
        }

        $portablePath = '';
        $dataTarget = '';
        try {
            $portable = PortableExporter::build();
            $portablePath = (string) ($portable['path'] ?? '');
            if ($portablePath === '' || !is_file($portablePath) || !is_readable($portablePath)) {
                throw new \RuntimeException('Den portable VDM-datafil kunne ikke læses.');
            }

            $base = pathinfo($programFilename, PATHINFO_FILENAME);
            $dataFilename = sanitize_file_name($base . '-vdm-data.zip');
            $dataTarget = trailingslashit($directory) . $dataFilename;
            if (!self::moveFile($portablePath, $dataTarget)) {
                throw new \RuntimeException('Den portable VDM-datafil kunne ikke flyttes til checkpointmappen.');
            }
            $portablePath = '';

            $inspection = PortablePackage::inspect($dataTarget);
            if (!is_array($inspection['manifest'] ?? null)) {
                throw new \RuntimeException('VDM-datafilens portable manifest kunne ikke valideres.');
            }

            $dataSize = filesize($dataTarget);
            $dataSha = strtolower((string) hash_file('sha256', $dataTarget));
            if (!is_int($dataSize) || $dataSize <= 0 || preg_match('/^[a-f0-9]{64}$/', $dataSha) !== 1) {
                throw new \RuntimeException('VDM-datafilens SHA-256 eller størrelse kunne ikke valideres.');
            }

            $createdUtc = gmdate('c');
            $row = [
                'id' => substr(hash('sha256', $programFilename . '|' . $programSha . '|' . $dataSha), 0, 20),
                'fromVersion' => VDM_VERSION,
                'toVersion' => $targetVersion,
                'createdUtc' => $createdUtc,
                'programFile' => $programFilename,
                'programSha256' => $programSha,
                'programSize' => $programSize,
                'dataFile' => $dataFilename,
                'dataSha256' => $dataSha,
                'dataSize' => $dataSize,
                'dataSummary' => is_array($portable['summary'] ?? null) ? array_map('intval', $portable['summary']) : [],
                'storage' => 'checkpoint',
            ];
            self::store($row);
            return $row;
        } catch (\Throwable $error) {
            if ($portablePath !== '' && is_file($portablePath)) {
                @unlink($portablePath);
            }
            if ($dataTarget !== '' && is_file($dataTarget)) {
                @unlink($dataTarget);
            }
            return new \WP_Error('vdm_checkpoint_data_failed', $error->getMessage());
        }
    }

    private static function findProgramBackup(string $targetVersion): string
    {
        $directory = self::legacyProgramDirectory();
        if ($directory === '' || !is_dir($directory)) {
            return '';
        }

        $safeCurrent = sanitize_file_name(VDM_VERSION);
        $safeTarget = sanitize_file_name($targetVersion !== '' ? $targetVersion : 'unknown');
        $pattern = trailingslashit($directory) . 'visual-designer-manager-' . $safeCurrent . '-before-' . $safeTarget . '-*.zip';
        $files = glob($pattern);
        if (!is_array($files) || $files === []) {
            return '';
        }
        usort($files, static fn(string $a, string $b): int => ((int) @filemtime($b)) <=> ((int) @filemtime($a)));
        foreach ($files as $file) {
            $mtime = (int) @filemtime($file);
            if ($mtime >= self::$requestStartedAt - 10 && is_file($file) && is_readable($file)) {
                return $file;
            }
        }
        return '';
    }

    /** @param array<string,mixed> $row */
    private static function store(array $row): void
    {
        $history = get_option(self::HISTORY_OPTION, []);
        $history = is_array($history) ? array_values(array_filter($history, 'is_array')) : [];
        array_unshift($history, $row);

        $seen = [];
        $unique = [];
        foreach ($history as $item) {
            $id = (string) ($item['id'] ?? '');
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $unique[] = $item;
        }

        $keep = array_slice($unique, 0, self::MAX_CHECKPOINTS);
        $remove = array_slice($unique, self::MAX_CHECKPOINTS);
        update_option(self::HISTORY_OPTION, $keep, false);

        $directory = self::checkpointDirectory();
        foreach ($remove as $old) {
            foreach (['programFile', 'dataFile'] as $field) {
                $filename = basename((string) ($old[$field] ?? ''));
                if ($filename !== '' && $directory !== '') {
                    @unlink(trailingslashit($directory) . $filename);
                }
            }
        }
    }

    /** @return list<array<string,mixed>> */
    private static function legacyProgramBackups(): array
    {
        $directory = self::legacyProgramDirectory();
        if ($directory === '' || !is_dir($directory)) {
            return [];
        }
        $files = glob(trailingslashit($directory) . 'visual-designer-manager-*-before-*-*.zip');
        if (!is_array($files)) {
            return [];
        }

        $rows = [];
        foreach ($files as $path) {
            $filename = basename($path);
            if (preg_match('/^visual-designer-manager-(.+?)-before-(.+?)-(\d{8}-\d{6})\.zip$/', $filename, $match) !== 1) {
                continue;
            }
            $size = filesize($path);
            $sha = strtolower((string) hash_file('sha256', $path));
            if (!is_int($size) || $size <= 0 || preg_match('/^[a-f0-9]{64}$/', $sha) !== 1) {
                continue;
            }
            $date = \DateTimeImmutable::createFromFormat('!Ymd-His', $match[3], new \DateTimeZone('UTC'));
            $createdUtc = $date instanceof \DateTimeImmutable ? $date->format('c') : gmdate('c', (int) @filemtime($path));
            $rows[] = [
                'id' => substr(hash('sha256', 'legacy|' . $filename . '|' . $sha), 0, 20),
                'fromVersion' => (string) $match[1],
                'toVersion' => (string) $match[2],
                'createdUtc' => $createdUtc,
                'programFile' => $filename,
                'programSha256' => $sha,
                'programSize' => $size,
                'dataFile' => '',
                'dataSha256' => '',
                'dataSize' => 0,
                'dataSummary' => [],
                'storage' => 'legacy',
            ];
        }
        return $rows;
    }

    private static function checkpointDirectory(): string
    {
        return self::uploadDirectory(self::CHECKPOINT_DIR, true);
    }

    private static function legacyProgramDirectory(): string
    {
        return self::uploadDirectory(self::LEGACY_PROGRAM_DIR, false);
    }

    private static function uploadDirectory(string $name, bool $create): string
    {
        $uploads = wp_upload_dir();
        if (!empty($uploads['error'])) {
            return '';
        }
        $directory = trailingslashit((string) $uploads['basedir']) . $name;
        if ($create && !is_dir($directory) && !wp_mkdir_p($directory)) {
            return '';
        }
        return $directory;
    }

    private static function moveFile(string $source, string $target): bool
    {
        if ($source === '' || !is_file($source) || !is_readable($source)) {
            return false;
        }
        if (@rename($source, $target)) {
            return is_file($target) && is_readable($target);
        }
        if (@copy($source, $target)) {
            @unlink($source);
            return is_file($target) && is_readable($target);
        }
        return false;
    }
}
