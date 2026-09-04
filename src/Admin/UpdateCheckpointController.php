<?php

declare(strict_types=1);

namespace VisualDesignerManager\Admin;

use VisualDesignerManager\Update\GitHubUpdater;
use VisualDesignerManager\Update\UpdateCheckpointManager;

final class UpdateCheckpointController
{
    private function __construct()
    {
    }

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'replaceUpdatesPage'], 1000);
    }

    public static function replaceUpdatesPage(): void
    {
        $parent = AdminController::MENU_SLUG;
        $slug = ParityController::UPDATES_SLUG;
        $hook = get_plugin_page_hookname($slug, $parent);
        if (is_string($hook) && $hook !== '') {
            remove_action($hook, [ParityController::class, 'updates']);
        }
        remove_submenu_page($parent, $slug);
        add_submenu_page(
            $parent,
            'Opdateringer',
            'Opdateringer',
            'update_plugins',
            $slug,
            [self::class, 'render']
        );
        ParityController::normalizeMenu();
    }

    public static function render(): void
    {
        if (!current_user_can('update_plugins')) {
            wp_die(esc_html__('Ingen adgang.', 'visual-designer-manager'));
        }

        $force = isset($_GET['vdm_update_check']);
        $status = GitHubUpdater::status($force);
        $rows = UpdateCheckpointManager::checkpoints();

        echo '<div class="wrap"><h1>Opdateringer</h1>';
        echo '<p>Kontroller, installer og se update-checkpoints for Visual Designer Manager.</p>';
        self::notice();

        echo '<div class="card" style="max-width:none;margin-top:16px">';
        echo '<h2>Version</h2>';
        echo '<p style="font-size:34px;line-height:1;margin:16px 0"><strong>' . esc_html(VDM_VERSION) . '</strong></p>';
        if ($status['ok']) {
            echo '<p>Seneste GitHub-version: <strong>' . esc_html((string) $status['latest']) . '</strong></p>';
            if ($status['available']) {
                echo '<p><span style="display:inline-block;padding:4px 9px;border-radius:14px;background:#fcf0c2;border:1px solid #dba617">Opdatering tilgængelig</span></p>';
            } else {
                echo '<p><span style="display:inline-block;padding:4px 9px;border-radius:14px;background:#edfaef;border:1px solid #72aee6">Du er opdateret</span></p>';
            }
        } else {
            echo '<p><span style="color:#b32d2e">GitHub-manifestet kunne ikke læses.</span></p>';
        }

        echo '<p>Downloadpakken SHA-256-verificeres. Før installation oprettes både en program-ZIP og et komplet portabelt VDM-data-checkpoint. Opdateringen stoppes, hvis et af de to checkpoints ikke kan oprettes eller valideres.</p>';
        echo '<p style="margin-top:16px">' . GitHubUpdater::checkButtonHtml() . ' ' . GitHubUpdater::installButtonHtml() . ' ';
        echo '<a class="button" href="' . esc_url(admin_url('plugins.php')) . '">WordPress plugins</a> ';
        echo '<a class="button" href="' . esc_url('https://github.com/phenixdk2020/visual-designer-manager/actions') . '" target="_blank" rel="noopener noreferrer">GitHub build-status</a></p>';
        echo '</div>';

        echo '<div class="card" style="max-width:none;margin-top:16px">';
        echo '<h2>Update-checkpoints</h2>';
        echo '<p>De seneste automatiske checkpoints før plugin-opdateringer. Der beholdes op til 12 komplette checkpoints. Ældre V2-programbackups vises som program-only, hvis de stadig findes.</p>';
        if ($rows === []) {
            echo '<p>Ingen lokale update-checkpoints er registreret endnu.</p>';
        } else {
            echo '<table class="widefat striped"><thead><tr>';
            echo '<th>Fra</th><th>Til</th><th>Dato</th><th>Program</th><th>VDM-data</th><th>Handlinger</th>';
            echo '</tr></thead><tbody>';
            foreach ($rows as $row) {
                self::row($row);
            }
            echo '</tbody></table>';
        }
        echo '</div>';
        echo '</div>';
    }

    /** @param array<string,mixed> $row */
    private static function row(array $row): void
    {
        $id = (string) ($row['id'] ?? '');
        $from = (string) ($row['fromVersion'] ?? '—');
        $to = (string) ($row['toVersion'] ?? '—');
        $created = self::localDate((string) ($row['createdUtc'] ?? ''));
        $programFile = basename((string) ($row['programFile'] ?? ''));
        $dataFile = basename((string) ($row['dataFile'] ?? ''));
        $programSize = (int) ($row['programSize'] ?? 0);
        $dataSize = (int) ($row['dataSize'] ?? 0);

        echo '<tr>';
        echo '<td><strong>' . esc_html($from) . '</strong></td>';
        echo '<td>' . esc_html($to) . '</td>';
        echo '<td>' . esc_html($created) . '</td>';
        echo '<td>';
        if ($programFile !== '') {
            echo '<code>' . esc_html($programFile) . '</code>';
            if ($programSize > 0) {
                echo '<br><small>' . esc_html(size_format($programSize)) . '</small>';
            }
        } else {
            echo '—';
        }
        echo '</td>';

        echo '<td>';
        if ($dataFile !== '') {
            echo '<code>' . esc_html($dataFile) . '</code>';
            if ($dataSize > 0) {
                echo '<br><small>' . esc_html(size_format($dataSize)) . '</small>';
            }
            $summary = is_array($row['dataSummary'] ?? null) ? $row['dataSummary'] : [];
            if ($summary !== []) {
                $parts = [];
                foreach (['pages' => 'sider', 'events' => 'events', 'vehicles' => 'køretøjer', 'galleries' => 'gallerier', 'media' => 'medier'] as $key => $label) {
                    if (isset($summary[$key])) {
                        $parts[] = (int) $summary[$key] . ' ' . $label;
                    }
                }
                if ($parts !== []) {
                    echo '<br><small>' . esc_html(implode(' · ', $parts)) . '</small>';
                }
            }
        } else {
            echo '<span class="description">Ældre checkpoint · kun program-ZIP</span>';
        }
        echo '</td>';

        echo '<td>';
        if ($id !== '' && $programFile !== '') {
            $programUrl = UpdateCheckpointManager::downloadUrl($id, 'program');
            if ($programUrl !== '') {
                echo '<a class="button button-small" href="' . esc_url($programUrl) . '">Download program</a> ';
            }
        }
        if ($id !== '' && $dataFile !== '') {
            $dataUrl = UpdateCheckpointManager::downloadUrl($id, 'data');
            if ($dataUrl !== '') {
                echo '<a class="button button-small" href="' . esc_url($dataUrl) . '">Download VDM-data</a>';
            }
        }
        echo '</td>';
        echo '</tr>';
    }

    private static function localDate(string $value): string
    {
        if ($value === '') {
            return '—';
        }
        try {
            $date = new \DateTimeImmutable($value);
            $timezone = wp_timezone();
            return $date->setTimezone($timezone)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return $value;
        }
    }

    private static function notice(): void
    {
        $check = sanitize_key((string) ($_GET['vdm_update_check'] ?? ''));
        $message = sanitize_text_field((string) ($_GET['vdm_update_message'] ?? ''));
        if ($check === 'installed') {
            echo '<div class="notice notice-success inline"><p>Visual Designer Manager blev opdateret til <strong>' . esc_html((string) ($_GET['vdm_update_version'] ?? VDM_VERSION)) . '</strong>.</p></div>';
        } elseif ($check === 'install-error') {
            echo '<div class="notice notice-error inline"><p>Opdateringen fejlede' . ($message !== '' ? ': ' . esc_html($message) : '.') . '</p></div>';
        } elseif ($check === 'error') {
            echo '<div class="notice notice-error inline"><p>GitHub-manifestet kunne ikke hentes eller valideres.</p></div>';
        } elseif ($check === 'current') {
            echo '<div class="notice notice-success inline"><p>Du har allerede den seneste publicerede version.</p></div>';
        } elseif ($check === 'available') {
            echo '<div class="notice notice-info inline"><p>En nyere version er tilgængelig.</p></div>';
        }
    }
}
