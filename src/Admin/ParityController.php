<?php

declare(strict_types=1);

namespace VisualDesignerManager\Admin;

use VisualDesignerManager\Diagnostics\DiagnosticStore;
use VisualDesignerManager\Fields\EventFieldRegistry;
use VisualDesignerManager\Fields\VehicleFieldRegistry;
use VisualDesignerManager\Storage\LayoutRepository;
use VisualDesignerManager\Transfer\PortableExporter;

final class ParityController
{
    public const VEHICLE_FIELDS_SLUG = 'vdm-vehicle-fields';
    public const EVENT_FIELDS_SLUG = 'vdm-event-fields';
    public const PAGES_SLUG = 'vdm-pages';
    public const BACKUP_SLUG = 'vdm-backup';
    public const UPDATES_SLUG = 'vdm-updates';
    public const LOG_SLUG = 'vdm-log';
    public const MANUAL_SLUG = 'vdm-manual';

    private const SAVE_VEHICLE_FIELDS = 'vdm_save_vehicle_fields';
    private const SAVE_EVENT_FIELDS = 'vdm_save_event_fields';
    private const DOWNLOAD_BACKUP = 'vdm_download_backup';
    private const CLEAR_LOG = 'vdm_clear_log';
    private const SET_HOME = 'vdm_set_home_page';

    private function __construct()
    {
    }

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'menu'], 18);
        add_action('admin_menu', [self::class, 'normalizeMenu'], 999);
        add_action('admin_post_' . self::SAVE_VEHICLE_FIELDS, [self::class, 'saveVehicleFields']);
        add_action('admin_post_' . self::SAVE_EVENT_FIELDS, [self::class, 'saveEventFields']);
        add_action('admin_post_' . self::DOWNLOAD_BACKUP, [self::class, 'downloadBackup']);
        add_action('admin_post_' . self::CLEAR_LOG, [self::class, 'clearLog']);
        add_action('admin_post_' . self::SET_HOME, [self::class, 'setHomePage']);
    }

    public static function menu(): void
    {
        $parent = AdminController::MENU_SLUG;
        $cap = 'edit_pages';
        add_submenu_page($parent, 'Køretøjsfelter', 'Køretøjsfelter', $cap, self::VEHICLE_FIELDS_SLUG, [self::class, 'vehicleFields']);
        add_submenu_page($parent, 'Eventfelter', 'Eventfelter', $cap, self::EVENT_FIELDS_SLUG, [self::class, 'eventFields']);
        add_submenu_page($parent, 'Sider', 'Sider', $cap, self::PAGES_SLUG, [self::class, 'pages']);
        add_submenu_page($parent, 'Backup', 'Backup', 'manage_options', self::BACKUP_SLUG, [self::class, 'backup']);
        add_submenu_page($parent, 'Opdateringer', 'Opdateringer', 'update_plugins', self::UPDATES_SLUG, [self::class, 'updates']);
        add_submenu_page($parent, 'Log', 'Log', 'manage_options', self::LOG_SLUG, [self::class, 'log']);
        add_submenu_page($parent, 'Brugermanual', 'Brugermanual', $cap, self::MANUAL_SLUG, [self::class, 'manual']);
    }

    public static function normalizeMenu(): void
    {
        global $submenu;
        $parent = AdminController::MENU_SLUG;
        if (!isset($submenu[$parent]) || !is_array($submenu[$parent])) {
            return;
        }

        $desired = [
            $parent => 'Dashboard',
            'edit.php?post_type=vdm_vehicle' => 'Køretøjer',
            self::VEHICLE_FIELDS_SLUG => 'Køretøjsfelter',
            'edit.php?post_type=vdm_event' => 'Events',
            self::EVENT_FIELDS_SLUG => 'Eventfelter',
            'edit.php?post_type=vdm_gallery' => 'Billedgalleri',
            self::PAGES_SLUG => 'Sider',
            self::BACKUP_SLUG => 'Backup',
            self::UPDATES_SLUG => 'Opdateringer',
            self::LOG_SLUG => 'Log',
            self::MANUAL_SLUG => 'Brugermanual',
            DesignerController::MENU_SLUG => 'Visual Designer',
            TransferController::MENU_SLUG => 'Eksport',
            NavigationController::MENU_SLUG => 'Menu',
            TemplateDesignerController::MENU_SLUG => 'Header / Footer',
            SiteDesignController::MENU_SLUG => 'Tema',
            SiteSettingsController::MENU_SLUG => 'Siteindstillinger',
        ];

        $bySlug = [];
        foreach ($submenu[$parent] as $item) {
            if (!is_array($item) || !isset($item[2])) {
                continue;
            }
            $slug = (string) $item[2];
            if (!isset($bySlug[$slug])) {
                $bySlug[$slug] = $item;
            }
        }

        $ordered = [];
        foreach ($desired as $slug => $label) {
            if (!isset($bySlug[$slug])) {
                continue;
            }
            $item = $bySlug[$slug];
            $item[0] = $label;
            $ordered[] = $item;
            unset($bySlug[$slug]);
        }
        foreach ($bySlug as $item) {
            $ordered[] = $item;
        }
        $submenu[$parent] = $ordered;
    }

    public static function vehicleFields(): void
    {
        self::guard('edit_pages');
        self::renderFieldEditor('Køretøjsfelter', VehicleFieldRegistry::all(), self::SAVE_VEHICLE_FIELDS, false);
    }

    public static function eventFields(): void
    {
        self::guard('edit_pages');
        self::renderFieldEditor('Eventfelter', EventFieldRegistry::all(), self::SAVE_EVENT_FIELDS, true);
    }

    public static function saveVehicleFields(): void
    {
        self::guard('edit_pages');
        check_admin_referer(self::SAVE_VEHICLE_FIELDS);
        $rows = isset($_POST['vdm_fields']) && is_array($_POST['vdm_fields']) ? wp_unslash($_POST['vdm_fields']) : [];
        VehicleFieldRegistry::save(self::normalizeSubmittedRows(is_array($rows) ? $rows : [], false));
        DiagnosticStore::add('info', 'Køretøjsfelter blev gemt.', ['count' => count(VehicleFieldRegistry::all())]);
        self::redirect(self::VEHICLE_FIELDS_SLUG, 'Feltopsætningen er gemt.');
    }

    public static function saveEventFields(): void
    {
        self::guard('edit_pages');
        check_admin_referer(self::SAVE_EVENT_FIELDS);
        $rows = isset($_POST['vdm_fields']) && is_array($_POST['vdm_fields']) ? wp_unslash($_POST['vdm_fields']) : [];
        EventFieldRegistry::save(self::normalizeSubmittedRows(is_array($rows) ? $rows : [], true));
        DiagnosticStore::add('info', 'Eventfelter blev gemt.', ['count' => count(EventFieldRegistry::all())]);
        self::redirect(self::EVENT_FIELDS_SLUG, 'Feltopsætningen er gemt.');
    }

    public static function pages(): void
    {
        self::guard('edit_pages');
        $pages = get_pages(['sort_column' => 'menu_order,post_title', 'post_status' => ['publish', 'draft', 'private']]);
        $frontId = get_option('show_on_front', 'posts') === 'page' ? absint(get_option('page_on_front', 0)) : 0;
        self::open('Sider', 'Alle WordPress-sider med Visual Designer-status, version og direkte adgang til Designeren.');
        self::notice();
        echo '<table class="widefat striped"><thead><tr><th>Side</th><th>Status</th><th>Visual Designer</th><th>Version</th><th>Elementer</th><th>Handlinger</th></tr></thead><tbody>';
        foreach ($pages as $page) {
            if (!$page instanceof \WP_Post) {
                continue;
            }
            $document = LayoutRepository::get((int) $page->ID);
            $nodes = is_array($document['nodes'] ?? null) ? $document['nodes'] : [];
            $version = LayoutRepository::version((int) $page->ID);
            $designer = add_query_arg(['page' => DesignerController::MENU_SLUG, 'post_id' => (int) $page->ID], admin_url('admin.php'));
            $edit = get_edit_post_link((int) $page->ID, 'raw');
            echo '<tr><td><strong>' . esc_html((string) $page->post_title) . '</strong>' . ((int) $page->ID === $frontId ? ' <span class="dashicons dashicons-admin-home" title="Hjemmeside"></span>' : '') . '<br><code>' . esc_html((string) $page->post_name) . '</code></td>';
            echo '<td>' . esc_html((string) $page->post_status) . '</td><td>' . ($version > 0 || $nodes !== [] ? '<strong>Ja</strong>' : 'Nej') . '</td><td>v' . esc_html((string) $version) . '</td><td>' . esc_html((string) count($nodes)) . '</td><td>';
            echo '<a class="button button-primary" href="' . esc_url($designer) . '">Visual Designer</a> ';
            if (is_string($edit) && $edit !== '') {
                echo '<a class="button" href="' . esc_url($edit) . '">WordPress</a> ';
            }
            if ((int) $page->ID !== $frontId) {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline">';
                wp_nonce_field(self::SET_HOME);
                echo '<input type="hidden" name="action" value="' . esc_attr(self::SET_HOME) . '"><input type="hidden" name="post_id" value="' . esc_attr((string) $page->ID) . '"><button class="button" type="submit">Sæt som Hjem</button></form>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table>';
        self::close();
    }

    public static function setHomePage(): void
    {
        self::guard('edit_pages');
        check_admin_referer(self::SET_HOME);
        $postId = absint($_POST['post_id'] ?? 0);
        if ($postId <= 0 || get_post_type($postId) !== 'page') {
            self::redirect(self::PAGES_SLUG, 'Siden kunne ikke vælges som Hjem.', 'error');
        }
        if (get_post_status($postId) !== 'publish' && current_user_can('publish_pages')) {
            wp_update_post(['ID' => $postId, 'post_status' => 'publish']);
        }
        update_option('show_on_front', 'page');
        update_option('page_on_front', $postId);
        DiagnosticStore::add('info', 'Hjemmesiden blev ændret.', ['postId' => $postId]);
        self::redirect(self::PAGES_SLUG, 'Hjemmesiden er opdateret.');
    }

    public static function backup(): void
    {
        self::guard('manage_options');
        self::open('Backup', 'Opret en komplet portabel VDM-backup med sider, layouts, Header/Footer, indhold, menuer, indstillinger og medier.');
        self::notice();
        echo '<div class="card" style="max-width:760px"><h2>Komplet backup</h2><p>Backuppen bruger samme validerede VDM-format som Eksport og indeholder SHA-256-kontroller for alle filer.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field(self::DOWNLOAD_BACKUP);
        echo '<input type="hidden" name="action" value="' . esc_attr(self::DOWNLOAD_BACKUP) . '">';
        submit_button('Download backup', 'primary', 'submit', false);
        echo '</form><p><a href="' . esc_url(admin_url('admin.php?page=' . TransferController::MENU_SLUG)) . '">Åbn Eksport for import/gendannelse</a></p></div>';
        self::close();
    }

    public static function downloadBackup(): void
    {
        self::guard('manage_options');
        check_admin_referer(self::DOWNLOAD_BACKUP);
        $path = '';
        try {
            $package = PortableExporter::build();
            $path = (string) ($package['path'] ?? '');
            $filename = sanitize_file_name('visual-designer-manager-backup-' . gmdate('Ymd-His') . '.zip');
            if ($path === '' || !is_file($path) || !is_readable($path)) {
                throw new \RuntimeException('Backupfilen kunne ikke læses.');
            }
            $size = filesize($path);
            if (!is_int($size) || $size <= 0) {
                throw new \RuntimeException('Backupfilen er tom.');
            }
            DiagnosticStore::add('info', 'Komplet backup blev oprettet.', ['bytes' => $size]);
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            nocache_headers();
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . (string) $size);
            header('X-Content-Type-Options: nosniff');
            readfile($path);
        } catch (\Throwable $error) {
            DiagnosticStore::add('error', 'Backup fejlede.', ['message' => $error->getMessage()]);
            if ($path !== '' && is_file($path)) {
                @unlink($path);
            }
            wp_die(esc_html('Backup fejlede: ' . $error->getMessage()));
        }
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }
        exit;
    }

    public static function updates(): void
    {
        self::guard('update_plugins');
        self::open('Opdateringer', 'Versionsstatus for Visual Designer Manager.');
        echo '<div class="card" style="max-width:760px"><h2>Installeret version</h2><p><strong>' . esc_html(VDM_VERSION) . '</strong></p>';
        echo '<p>RC.2 bruger GitHub Actions som kontrolleret build- og QA-kanal. En pakke må først installeres, når både QA og package-workflow er grønne.</p>';
        echo '<p><a class="button" href="' . esc_url('https://github.com/phenixdk2020/visual-designer-manager/actions') . '" target="_blank" rel="noopener noreferrer">Åbn build-status på GitHub</a> ';
        echo '<a class="button" href="' . esc_url(admin_url('plugins.php')) . '">WordPress plugins</a></p></div>';
        self::close();
    }

    public static function log(): void
    {
        self::guard('manage_options');
        self::open('Log', 'VDM-diagnostics fra administrative handlinger og RC-migration.');
        self::notice();
        $entries = DiagnosticStore::all();
        echo '<p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline">';
        wp_nonce_field(self::CLEAR_LOG);
        echo '<input type="hidden" name="action" value="' . esc_attr(self::CLEAR_LOG) . '"><button class="button" type="submit">Ryd log</button></form></p>';
        echo '<table class="widefat striped"><thead><tr><th>Tid (UTC)</th><th>Niveau</th><th>Besked</th><th>Kontekst</th></tr></thead><tbody>';
        if ($entries === []) {
            echo '<tr><td colspan="4">Ingen logposter endnu.</td></tr>';
        }
        foreach ($entries as $entry) {
            $context = is_array($entry['context'] ?? null) ? $entry['context'] : [];
            echo '<tr><td>' . esc_html((string) ($entry['time'] ?? '')) . '</td><td>' . esc_html(strtoupper((string) ($entry['level'] ?? 'info'))) . '</td><td>' . esc_html((string) ($entry['message'] ?? '')) . '</td><td><code>' . esc_html((string) wp_json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</code></td></tr>';
        }
        echo '</tbody></table>';
        self::close();
    }

    public static function clearLog(): void
    {
        self::guard('manage_options');
        check_admin_referer(self::CLEAR_LOG);
        DiagnosticStore::clear();
        self::redirect(self::LOG_SLUG, 'Loggen er ryddet.');
    }

    public static function manual(): void
    {
        self::guard('edit_pages');
        self::open('Brugermanual', 'Kort reference til den brugerflade, der svarer til V1-arbejdsgangen.');
        echo '<div class="card" style="max-width:900px"><h2>Administration</h2><p>Brug menuen i den kendte rækkefølge: indholdsmoduler først, derefter Sider/Backup/Log og til sidst Visual Designer, Eksport, Menu, Header / Footer, Tema og Siteindstillinger.</p>';
        echo '<h2>Visual Designer</h2><p>Vælg en side, byg layoutet, kontrollér breakpoint og gem. Preview og live-output bruger samme VDM-renderer.</p>';
        echo '<h2>Backup og Eksport</h2><p>Backup opretter en komplet portabel VDM-pakke. Eksport indeholder også import med forhåndskontrol før ændringer udføres.</p>';
        echo '<h2>Migration</h2><p>Importer fra tidligere schema gennem Eksport. Efter konvertering gemmes indholdet kun i VDM2-format.</p>';
        echo '<h2>RC.2 test</h2><p>Sammenlign den migrerede installation med referenceinstallationen på desktop, laptop, tablet og mobil. Ingen manuel efterstyling skal være nødvendig for et godkendt resultat.</p></div>';
        self::close();
    }

    /** @param list<array<string,mixed>> $rows */
    private static function renderFieldEditor(string $title, array $rows, string $action, bool $event): void
    {
        self::open($title, $event
            ? 'Definér genbrugelige eventfelter og hvor de vises.'
            : 'Definér genbrugelige tekniske køretøjsfelter. Felt-ID bevares ved omdøbning.');
        self::notice();
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field($action);
        echo '<input type="hidden" name="action" value="' . esc_attr($action) . '"><table class="widefat striped" id="vdm-field-table"><thead><tr><th>Navn</th><th>Type</th>';
        if (!$event) {
            echo '<th>Enhed</th>';
        }
        if ($event) {
            echo '<th>Påkrævet</th><th>Kort</th><th>Detalje</th>';
        }
        echo '<th>Rækkefølge</th><th>Aktiv</th><th></th></tr></thead><tbody>';
        foreach ($rows as $index => $row) {
            self::fieldRow((int) $index, $row, $event);
        }
        echo '</tbody></table><p><button type="button" class="button" id="vdm-add-field">+ Tilføj felt</button> ';
        submit_button('Gem feltopsætning', 'primary', 'submit', false);
        echo '</p></form>';
        echo '<script>(function(){const b=document.getElementById("vdm-add-field"),t=document.querySelector("#vdm-field-table tbody");if(!b||!t)return;b.addEventListener("click",function(){const i=t.children.length;const tr=document.createElement("tr");tr.innerHTML=' . wp_json_encode(self::blankFieldRowHtml($event)) . '.replaceAll("__INDEX__",String(i));t.appendChild(tr);});t.addEventListener("click",function(e){if(e.target&&e.target.matches(".vdm-remove-field")){e.preventDefault();e.target.closest("tr").remove();}});})();</script>';
        self::close();
    }

    /** @param array<string,mixed> $row */
    private static function fieldRow(int $index, array $row, bool $event): void
    {
        echo str_replace('__INDEX__', (string) $index, self::fieldRowHtml($row, $event));
    }

    /** @param array<string,mixed> $row */
    private static function fieldRowHtml(array $row, bool $event): string
    {
        $index = '__INDEX__';
        $html = '<tr><td><input type="hidden" name="vdm_fields[' . $index . '][id]" value="' . esc_attr((string) ($row['id'] ?? '')) . '"><input class="regular-text" required type="text" name="vdm_fields[' . $index . '][label]" value="' . esc_attr((string) ($row['label'] ?? '')) . '"></td>';
        $html .= '<td><select name="vdm_fields[' . $index . '][type]">';
        $types = $event
            ? ['text' => 'Tekst', 'textarea' => 'Flere linjer', 'richtext' => 'Rich text', 'number' => 'Tal', 'integer' => 'Heltal', 'boolean' => 'Ja/nej', 'date' => 'Dato', 'datetime' => 'Dato/tid', 'url' => 'URL']
            : ['text' => 'Tekst', 'textarea' => 'Flere linjer', 'richtext' => 'Rich text', 'number' => 'Tal', 'integer' => 'Heltal', 'boolean' => 'Ja/nej', 'date' => 'Dato'];
        foreach ($types as $value => $label) {
            $html .= '<option value="' . esc_attr($value) . '"' . selected((string) ($row['type'] ?? 'text'), $value, false) . '>' . esc_html($label) . '</option>';
        }
        $html .= '</select></td>';
        if (!$event) {
            $html .= '<td><input type="text" name="vdm_fields[' . $index . '][unit]" value="' . esc_attr((string) ($row['unit'] ?? '')) . '" placeholder="fx kg"></td>';
        }
        if ($event) {
            $html .= self::checkCell($index, 'required', !empty($row['required']));
            $html .= self::checkCell($index, 'showCard', !empty($row['showCard']));
            $html .= self::checkCell($index, 'showDetail', array_key_exists('showDetail', $row) ? (bool) $row['showDetail'] : true);
        }
        $html .= '<td><input type="number" name="vdm_fields[' . $index . '][order]" value="' . esc_attr((string) ((int) ($row['order'] ?? 10))) . '" style="width:90px"></td>';
        $html .= self::checkCell($index, 'enabled', array_key_exists('enabled', $row) ? (bool) $row['enabled'] : true);
        $html .= '<td><button type="button" class="button-link-delete vdm-remove-field">Fjern</button></td></tr>';
        return $html;
    }

    private static function blankFieldRowHtml(bool $event): string
    {
        return self::fieldRowHtml(['id' => '', 'label' => '', 'type' => 'text', 'unit' => '', 'enabled' => true, 'required' => false, 'showCard' => false, 'showDetail' => true, 'order' => 100], $event);
    }

    private static function checkCell(string $index, string $name, bool $checked): string
    {
        return '<td><input type="checkbox" name="vdm_fields[' . $index . '][' . esc_attr($name) . ']" value="1"' . checked($checked, true, false) . '></td>';
    }

    /** @param array<int,mixed> $rows @return list<array<string,mixed>> */
    private static function normalizeSubmittedRows(array $rows, bool $event): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $item = [
                'id' => sanitize_key((string) ($row['id'] ?? '')),
                'label' => sanitize_text_field((string) ($row['label'] ?? '')),
                'type' => sanitize_key((string) ($row['type'] ?? 'text')),
                'enabled' => !empty($row['enabled']),
                'order' => (int) ($row['order'] ?? 0),
            ];
            if ($event) {
                $item['required'] = !empty($row['required']);
                $item['showCard'] = !empty($row['showCard']);
                $item['showDetail'] = !empty($row['showDetail']);
            } else {
                $item['unit'] = sanitize_text_field((string) ($row['unit'] ?? ''));
            }
            $out[] = $item;
        }
        return $out;
    }

    private static function open(string $title, string $description): void
    {
        echo '<div class="wrap"><h1>' . esc_html($title) . '</h1><p>' . esc_html($description) . '</p>';
    }

    private static function close(): void
    {
        echo '</div>';
    }

    private static function notice(): void
    {
        $message = isset($_GET['vdm_message']) ? sanitize_text_field((string) wp_unslash($_GET['vdm_message'])) : '';
        if ($message === '') {
            return;
        }
        $status = sanitize_key((string) ($_GET['vdm_status'] ?? 'success'));
        echo '<div class="notice ' . ($status === 'error' ? 'notice-error' : 'notice-success') . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }

    private static function redirect(string $slug, string $message, string $status = 'success'): never
    {
        wp_safe_redirect(add_query_arg(['page' => $slug, 'vdm_status' => $status, 'vdm_message' => $message], admin_url('admin.php')));
        exit;
    }

    private static function guard(string $capability): void
    {
        if (!current_user_can($capability)) {
            wp_die(esc_html__('Du har ikke adgang til denne Visual Designer Manager-funktion.', 'visual-designer-manager'));
        }
    }
}
