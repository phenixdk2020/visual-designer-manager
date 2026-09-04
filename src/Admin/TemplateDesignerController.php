<?php

declare(strict_types=1);

namespace VisualDesignerManager\Admin;

use VisualDesignerManager\Frontend\Renderer;
use VisualDesignerManager\Http\RestController;
use VisualDesignerManager\Navigation\NavigationRepository;
use VisualDesignerManager\Storage\TemplateRepository;

final class TemplateDesignerController
{
    public const MENU_SLUG = 'vdm-header-footer';
    private const ACTION_TEMPLATE = 'vdm_template_action';
    private const ACTION_SETTINGS = 'vdm_template_settings';
    private const ACTION_RESTORE = 'vdm_template_restore';

    private function __construct()
    {
    }

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'menu'], 21);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
        add_action('admin_post_' . self::ACTION_TEMPLATE, [self::class, 'templateAction']);
        add_action('admin_post_' . self::ACTION_SETTINGS, [self::class, 'saveSettings']);
        add_action('admin_post_' . self::ACTION_RESTORE, [self::class, 'restoreVersion']);
    }

    public static function menu(): void
    {
        add_submenu_page(
            AdminController::MENU_SLUG,
            'Header / Footer',
            'Header / Footer',
            'edit_theme_options',
            self::MENU_SLUG,
            [self::class, 'render']
        );
    }

    public static function enqueue(string $hook): void
    {
        if (strpos($hook, self::MENU_SLUG) === false || !current_user_can('edit_theme_options')) {
            return;
        }

        $slot = self::requestedSlot();
        $templateId = self::requestedTemplateId($slot);
        if ($templateId === '') {
            return;
        }
        $layoutId = 'template-' . $slot . '-' . $templateId;

        wp_enqueue_media();
        wp_enqueue_style('vdm-frontend', VDM_URL . 'assets/frontend.css', [], VDM_VERSION);
        wp_enqueue_style('vdm-designer', VDM_URL . 'assets/designer.css', ['vdm-frontend'], VDM_VERSION);
        wp_enqueue_script('vdm-frontend-runtime', VDM_URL . 'assets/frontend.js', [], VDM_VERSION, true);
        wp_enqueue_script('vdm-designer', VDM_URL . 'assets/designer.js', ['media-editor', 'vdm-frontend-runtime'], VDM_VERSION, true);

        wp_localize_script('vdm-designer', 'VDMDesignerConfig', [
            'ready' => true,
            'pageId' => $layoutId,
            'templateSlot' => $slot,
            'templateId' => $templateId,
            'restBase' => esc_url_raw(rest_url(RestController::NAMESPACE)),
            'saveUrl' => esc_url_raw(rest_url(RestController::NAMESPACE . '/layouts/' . $layoutId)),
            'renderUrl' => esc_url_raw(rest_url(RestController::NAMESPACE . '/render')),
            'nonce' => wp_create_nonce('wp_rest'),
            'document' => TemplateRepository::getTemplate($slot, $templateId),
            'version' => TemplateRepository::versionTemplate($slot, $templateId),
            'themeColors' => DesignerController::themeColors(),
            'navigationMenus' => NavigationRepository::choices(),
        ]);
    }

    public static function render(): void
    {
        self::guard();
        $slot = self::requestedSlot();
        $templateId = self::requestedTemplateId($slot);
        if ($templateId === '') {
            $templateId = TemplateRepository::create($slot, $slot === TemplateRepository::HEADER ? 'Header – Standard' : 'Footer – Standard');
            TemplateRepository::setDefault($slot, $templateId);
        }

        $labels = [TemplateRepository::HEADER => 'Header', TemplateRepository::FOOTER => 'Footer'];
        $templates = TemplateRepository::all($slot);
        $meta = TemplateRepository::meta($slot, $templateId) ?? [];
        $settings = TemplateRepository::settings($slot, $templateId);
        $history = TemplateRepository::historyTemplate($slot, $templateId);
        $version = TemplateRepository::versionTemplate($slot, $templateId);
        $previewVersion = absint($_GET['preview_version'] ?? 0);

        echo '<div class="wrap vdm-designer-admin">';
        echo '<div class="vdm-designer-heading"><div><h1>Visual Designer Manager · Header / Footer</h1><p>Navngivne globale templates med stabile ID’er, egne historikker og per-side assignment.</p></div>';
        echo '<div class="vdm-template-tabs">';
        foreach ($labels as $key => $label) {
            $url = add_query_arg(['page' => self::MENU_SLUG, 'slot' => $key], admin_url('admin.php'));
            echo '<a class="button' . ($slot === $key ? ' button-primary' : '') . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a> ';
        }
        echo '</div></div>';
        self::notice();

        echo '<div style="display:grid;grid-template-columns:minmax(480px,1fr) minmax(300px,430px);gap:18px;align-items:start;margin:18px 0">';
        echo '<section class="card" style="max-width:none;margin:0"><h2>' . esc_html($labels[$slot]) . '-templates</h2>';
        echo '<table class="widefat striped"><thead><tr><th>Navn</th><th>Status</th><th>Version</th><th>Brug</th><th>Handlinger</th></tr></thead><tbody>';
        foreach ($templates as $row) {
            $id = sanitize_key((string) ($row['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $selected = $id === $templateId;
            echo '<tr' . ($selected ? ' style="box-shadow:inset 4px 0 #2271b1"' : '') . '><td><strong>' . esc_html((string) ($row['name'] ?? 'Template')) . '</strong><br><code>' . esc_html($id) . '</code>';
            if (!empty($row['isDefault'])) {
                echo ' <span class="dashicons dashicons-admin-home" title="Website-standard"></span>';
            }
            echo '</td><td>' . (!empty($row['active']) ? '<strong>Aktiv</strong>' : 'Inaktiv') . '</td><td>v' . esc_html((string) ($row['version'] ?? 0)) . '</td><td>' . esc_html((string) ($row['usageCount'] ?? 0)) . ' sider</td><td>';
            echo '<a class="button' . ($selected ? ' button-primary' : '') . '" href="' . esc_url(self::url($slot, $id)) . '">Redigér</a> ';
            echo self::actionButton($slot, $id, 'duplicate', 'Duplikér');
            if (empty($row['isDefault'])) {
                echo self::actionButton($slot, $id, 'default', 'Sæt standard');
            }
            echo self::actionButton($slot, $id, !empty($row['active']) ? 'deactivate' : 'activate', !empty($row['active']) ? 'Deaktivér' : 'Aktivér');
            echo '</td></tr>';
        }
        echo '</tbody></table></section>';

        echo '<aside class="card" style="max-width:none;margin:0"><h2>Ny ' . esc_html($labels[$slot]) . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field(self::ACTION_TEMPLATE);
        echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION_TEMPLATE) . '"><input type="hidden" name="operation" value="create"><input type="hidden" name="slot" value="' . esc_attr($slot) . '">';
        echo '<p><label>Navn<br><input class="widefat" name="template_name" required placeholder="' . esc_attr($labels[$slot] . ' – navn') . '"></label></p>';
        echo '<p><button type="submit" class="button button-primary">Opret template</button></p></form></aside></div>';

        echo '<section class="card" style="max-width:none"><div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap"><div><h2 style="margin-bottom:4px">' . esc_html((string) ($meta['name'] ?? $labels[$slot])) . '</h2><code>' . esc_html($templateId) . '</code> · v' . esc_html((string) $version) . '</div>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:flex;gap:8px;align-items:end;margin-left:auto">';
        wp_nonce_field(self::ACTION_TEMPLATE);
        echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION_TEMPLATE) . '"><input type="hidden" name="operation" value="rename"><input type="hidden" name="slot" value="' . esc_attr($slot) . '"><input type="hidden" name="template_id" value="' . esc_attr($templateId) . '"><label>Templatenavn<br><input name="template_name" value="' . esc_attr((string) ($meta['name'] ?? '')) . '" required></label><button class="button" type="submit">Omdøb</button></form></div>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:16px;display:flex;gap:18px;align-items:end;flex-wrap:wrap">';
        wp_nonce_field(self::ACTION_SETTINGS);
        echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION_SETTINGS) . '"><input type="hidden" name="slot" value="' . esc_attr($slot) . '"><input type="hidden" name="template_id" value="' . esc_attr($templateId) . '">';
        if ($slot === TemplateRepository::HEADER) {
            echo '<label><input type="checkbox" name="sticky" value="1"' . checked(!empty($settings['sticky']), true, false) . '> Sticky Header</label>';
            echo '<label><input type="checkbox" name="overlay" value="1"' . checked(!empty($settings['overlay']), true, false) . '> Overlay første sektion</label>';
        }
        echo '<label>Indre max-bredde<br><input type="number" min="320" max="2400" name="content_width" value="' . esc_attr((string) ($settings['contentWidth'] ?? 1440)) . '"> px</label>';
        echo '<button class="button" type="submit">Gem templateindstillinger</button></form></section>';

        echo '<div class="vdm-designer-toolbar">';
        echo '<div class="vdm-toolbar-left"><div class="vdm-breakpoints" role="group" aria-label="Breakpoint">';
        foreach (['desktop' => 'Desktop', 'laptop' => 'Laptop', 'tablet' => 'Tablet', 'mobile' => 'Mobil'] as $key => $label) {
            echo '<button type="button" class="button vdm-breakpoint' . ($key === 'desktop' ? ' is-active' : '') . '" data-breakpoint="' . esc_attr($key) . '">' . esc_html($label) . '</button>';
        }
        echo '</div><button type="button" class="button" id="vdm-undo" disabled>Fortryd</button><button type="button" class="button" id="vdm-redo" disabled>Gentag</button></div>';
        echo '<div class="vdm-toolbar-right"><span id="vdm-save-status" class="vdm-save-status">Ikke gemt</span> <button type="button" class="button button-primary" id="vdm-save">Gem som ny version</button></div></div>';

        echo '<div class="vdm-workspace"><aside class="vdm-panel vdm-palette"><h2>Elementer</h2>';
        foreach ([
            'section' => 'Sektion', 'container' => 'Kasse', 'text' => 'Tekst', 'image' => 'Billede', 'button' => 'Knap',
            'spacer' => 'Mellemrum', 'divider' => 'Skillelinje', 'events' => 'Events', 'vehicles' => 'Køretøjer',
            'galleries' => 'Billedgalleri', 'navigation' => 'Navigation',
        ] as $type => $label) {
            echo '<button type="button" class="button vdm-palette-item" data-node-type="' . esc_attr($type) . '">' . esc_html($label) . '</button>';
        }
        echo '<p><small>Samme canonical V2-model og renderer som siderne.</small></p></aside>';
        echo '<main class="vdm-stage"><div class="vdm-stage-scroll"><div id="vdm-canvas" data-vdm-breakpoint="desktop" aria-label="Template Designer canvas"></div></div></main>';
        echo '<aside class="vdm-panel vdm-inspector"><h2>Indstillinger</h2><div id="vdm-inspector"><p>Vælg et element.</p></div></aside></div>';

        echo '<section class="card" style="max-width:none;margin-top:18px"><h2>Versionshistorik</h2>';
        if ($history === []) {
            echo '<p>Ingen tidligere versioner endnu.</p>';
        } else {
            echo '<table class="widefat striped"><thead><tr><th>Version</th><th>Gemt</th><th>Bruger</th><th>Elementer</th><th>Handlinger</th></tr></thead><tbody>';
            foreach (array_slice($history, 0, 50) as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $entryVersion = max(0, (int) ($entry['version'] ?? 0));
                if ($entryVersion <= 0) {
                    continue;
                }
                $savedBy = absint($entry['savedBy'] ?? 0);
                $user = $savedBy > 0 ? get_userdata($savedBy) : false;
                $nodes = is_array($entry['document']['nodes'] ?? null) ? $entry['document']['nodes'] : [];
                echo '<tr><td><strong>v' . esc_html((string) $entryVersion) . '</strong></td><td>' . esc_html((string) ($entry['savedAt'] ?? '')) . '</td><td>' . esc_html($user instanceof \WP_User ? (string) $user->display_name : '—') . '</td><td>' . esc_html((string) count($nodes)) . '</td><td>';
                echo '<a class="button button-small" href="' . esc_url(add_query_arg(['preview_version' => $entryVersion], self::url($slot, $templateId))) . '">Forhåndsvis</a> ';
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline">';
                wp_nonce_field(self::ACTION_RESTORE);
                echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION_RESTORE) . '"><input type="hidden" name="slot" value="' . esc_attr($slot) . '"><input type="hidden" name="template_id" value="' . esc_attr($templateId) . '"><input type="hidden" name="version" value="' . esc_attr((string) $entryVersion) . '"><button class="button button-small" type="submit" onclick="return confirm(\'Gendan denne version som en ny version?\');">Gendan som ny</button></form></td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '</section>';

        if ($previewVersion > 0) {
            foreach ($history as $entry) {
                if (!is_array($entry) || (int) ($entry['version'] ?? 0) !== $previewVersion || !is_array($entry['document'] ?? null)) {
                    continue;
                }
                echo '<section class="card" style="max-width:none;margin-top:18px"><h2>Forhåndsvisning af v' . esc_html((string) $previewVersion) . '</h2><p><a class="button" href="' . esc_url(self::url($slot, $templateId)) . '">Luk historisk preview</a></p>';
                echo '<div class="vdm-template-history-preview">' . Renderer::render($entry['document']) . '</div></section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                break;
            }
        }
        echo '</div>';
    }

    public static function templateAction(): void
    {
        self::guard();
        check_admin_referer(self::ACTION_TEMPLATE);
        $slot = self::postedSlot();
        $operation = sanitize_key((string) ($_POST['operation'] ?? ''));
        $templateId = sanitize_key((string) ($_POST['template_id'] ?? ''));
        $name = sanitize_text_field((string) wp_unslash($_POST['template_name'] ?? ''));
        try {
            if ($operation === 'create') {
                $templateId = TemplateRepository::create($slot, $name);
                self::redirect($slot, $templateId, 'Template oprettet.');
            }
            if ($templateId === '' || !TemplateRepository::exists($slot, $templateId)) {
                throw new \RuntimeException('Template findes ikke.');
            }
            if ($operation === 'duplicate') {
                $templateId = TemplateRepository::duplicate($slot, $templateId);
                self::redirect($slot, $templateId, 'Template duplikeret.');
            } elseif ($operation === 'rename') {
                TemplateRepository::rename($slot, $templateId, $name);
            } elseif ($operation === 'default') {
                TemplateRepository::setDefault($slot, $templateId);
            } elseif ($operation === 'activate') {
                TemplateRepository::setActive($slot, $templateId, true);
            } elseif ($operation === 'deactivate') {
                TemplateRepository::setActive($slot, $templateId, false);
            } else {
                throw new \RuntimeException('Ukendt templatehandling.');
            }
            self::redirect($slot, $templateId, 'Template opdateret.');
        } catch (\Throwable $error) {
            self::redirect($slot, $templateId, $error->getMessage(), 'error');
        }
    }

    public static function saveSettings(): void
    {
        self::guard();
        check_admin_referer(self::ACTION_SETTINGS);
        $slot = self::postedSlot();
        $templateId = sanitize_key((string) ($_POST['template_id'] ?? ''));
        try {
            TemplateRepository::saveSettings($slot, $templateId, [
                'sticky' => isset($_POST['sticky']),
                'overlay' => isset($_POST['overlay']),
                'contentWidth' => (int) ($_POST['content_width'] ?? 1440),
            ]);
            self::redirect($slot, $templateId, 'Templateindstillinger gemt.');
        } catch (\Throwable $error) {
            self::redirect($slot, $templateId, $error->getMessage(), 'error');
        }
    }

    public static function restoreVersion(): void
    {
        self::guard();
        check_admin_referer(self::ACTION_RESTORE);
        $slot = self::postedSlot();
        $templateId = sanitize_key((string) ($_POST['template_id'] ?? ''));
        $version = absint($_POST['version'] ?? 0);
        try {
            foreach (TemplateRepository::historyTemplate($slot, $templateId) as $entry) {
                if (!is_array($entry) || (int) ($entry['version'] ?? 0) !== $version || !is_array($entry['document'] ?? null)) {
                    continue;
                }
                TemplateRepository::saveTemplate($slot, $templateId, $entry['document'], get_current_user_id());
                self::redirect($slot, $templateId, 'Version v' . $version . ' er gendannet som en ny version.');
            }
            throw new \RuntimeException('Den valgte version findes ikke.');
        } catch (\Throwable $error) {
            self::redirect($slot, $templateId, $error->getMessage(), 'error');
        }
    }

    private static function requestedSlot(): string
    {
        $slot = sanitize_key((string) ($_GET['slot'] ?? TemplateRepository::HEADER));
        return in_array($slot, TemplateRepository::slots(), true) ? $slot : TemplateRepository::HEADER;
    }

    private static function postedSlot(): string
    {
        $slot = sanitize_key((string) ($_POST['slot'] ?? TemplateRepository::HEADER));
        return in_array($slot, TemplateRepository::slots(), true) ? $slot : TemplateRepository::HEADER;
    }

    private static function requestedTemplateId(string $slot): string
    {
        $id = sanitize_key((string) ($_GET['template_id'] ?? ''));
        if ($id !== '' && TemplateRepository::exists($slot, $id)) {
            return $id;
        }
        return TemplateRepository::defaultId($slot);
    }

    private static function url(string $slot, string $templateId = ''): string
    {
        $args = ['page' => self::MENU_SLUG, 'slot' => $slot];
        if ($templateId !== '') {
            $args['template_id'] = $templateId;
        }
        return add_query_arg($args, admin_url('admin.php'));
    }

    private static function actionButton(string $slot, string $templateId, string $operation, string $label): string
    {
        $nonce = wp_create_nonce(self::ACTION_TEMPLATE);
        $url = add_query_arg([
            'action' => self::ACTION_TEMPLATE,
            'operation' => $operation,
            'slot' => $slot,
            'template_id' => $templateId,
            '_wpnonce' => $nonce,
        ], admin_url('admin-post.php'));
        return '<a class="button button-small" href="' . esc_url($url) . '"' . ($operation === 'deactivate' ? ' onclick="return confirm(\'Deaktivér denne template?\');"' : '') . '>' . esc_html($label) . '</a> ';
    }

    private static function notice(): void
    {
        $message = sanitize_text_field((string) wp_unslash($_GET['vdm_message'] ?? ''));
        if ($message === '') {
            return;
        }
        $type = sanitize_key((string) ($_GET['vdm_notice'] ?? 'success')) === 'error' ? 'notice-error' : 'notice-success';
        echo '<div class="notice ' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }

    private static function redirect(string $slot, string $templateId, string $message, string $type = 'success'): void
    {
        $url = add_query_arg([
            'page' => self::MENU_SLUG,
            'slot' => $slot,
            'template_id' => $templateId,
            'vdm_notice' => $type,
            'vdm_message' => $message,
        ], admin_url('admin.php'));
        wp_safe_redirect($url, 303);
        exit;
    }

    private static function guard(): void
    {
        if (!current_user_can('edit_theme_options')) {
            wp_die(esc_html__('Du har ikke adgang til Header / Footer.', 'visual-designer-manager'));
        }
    }
}
