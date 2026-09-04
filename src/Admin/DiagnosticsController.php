<?php

declare(strict_types=1);

namespace VisualDesignerManager\Admin;

use VisualDesignerManager\Diagnostics\DiagnosticStore;

final class DiagnosticsController
{
    private const CLEAR_ACTION = 'vdm_clear_diagnostics_parity';

    private function __construct()
    {
    }

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'replaceMenu'], 1004);
        add_action('admin_post_' . self::CLEAR_ACTION, [self::class, 'clear']);
    }

    public static function replaceMenu(): void
    {
        remove_submenu_page(AdminController::MENU_SLUG, ParityController::LOG_SLUG);
        add_submenu_page(
            AdminController::MENU_SLUG,
            'Log / diagnostics',
            'Log',
            'manage_options',
            ParityController::LOG_SLUG,
            [self::class, 'render']
        );
        ParityController::normalizeMenu();
    }

    public static function render(): void
    {
        self::guard();
        $postId = absint($_GET['post_id'] ?? 0);
        $entries = DiagnosticStore::all();
        if ($postId > 0) {
            $entries = array_values(array_filter($entries, static function (array $entry) use ($postId): bool {
                $context = is_array($entry['context'] ?? null) ? $entry['context'] : [];
                return absint($context['postid'] ?? 0) === $postId;
            }));
        }
        $pages = get_pages(['sort_column' => 'post_title', 'post_status' => ['publish', 'draft', 'private']]);

        echo '<div class="wrap"><h1>Log / diagnostics</h1>';
        echo '<p>Drifts- og supportlog for Designer, sider, formularer, migration, opdateringer og administrative handlinger.</p>';
        if (sanitize_key((string) ($_GET['vdm_cleared'] ?? '')) === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>Loggen er ryddet.</p></div>';
        }

        echo '<div class="card" style="max-width:none;display:flex;gap:14px;align-items:end;flex-wrap:wrap">';
        echo '<form method="get"><input type="hidden" name="page" value="' . esc_attr(ParityController::LOG_SLUG) . '"><label><strong>Sidefilter</strong><br><select name="post_id" onchange="this.form.submit()"><option value="0">Alle hændelser</option>';
        foreach ($pages as $page) {
            if (!$page instanceof \WP_Post) {
                continue;
            }
            echo '<option value="' . esc_attr((string) $page->ID) . '"' . selected($postId, (int) $page->ID, false) . '>' . esc_html((string) $page->post_title) . '</option>';
        }
        echo '</select></label></form>';

        $support = DiagnosticStore::supportUrl($postId);
        echo '<button type="button" class="button" id="vdm-copy-support-url" data-url="' . esc_attr($support) . '">Kopiér diagnose-link</button>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-left:auto">';
        wp_nonce_field(self::CLEAR_ACTION . '_' . $postId);
        echo '<input type="hidden" name="action" value="' . esc_attr(self::CLEAR_ACTION) . '"><input type="hidden" name="post_id" value="' . esc_attr((string) $postId) . '"><button class="button" type="submit" onclick="return confirm(\'Ryd ' . ($postId > 0 ? 'loggen for denne side' : 'hele loggen') . '?\');">Ryd ' . ($postId > 0 ? 'sidelog' : 'hele loggen') . '</button></form></div>';

        echo '<div class="card" style="max-width:none"><h2>Hændelser</h2>';
        if ($entries === []) {
            echo '<p>Ingen diagnostikhændelser matcher filteret.</p>';
        } else {
            echo '<table class="widefat striped"><thead><tr><th>Dato UTC</th><th>Niveau</th><th>Hændelse</th><th>Kontekst</th></tr></thead><tbody>';
            foreach ($entries as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $level = sanitize_key((string) ($entry['level'] ?? 'info'));
                $context = is_array($entry['context'] ?? null) ? $entry['context'] : [];
                $pairs = [];
                foreach ($context as $key => $value) {
                    $pairs[] = sanitize_key((string) $key) . '=' . sanitize_text_field((string) $value);
                }
                echo '<tr><td><code>' . esc_html((string) ($entry['time'] ?? '')) . '</code></td><td><strong>' . esc_html(strtoupper($level)) . '</strong></td><td>' . esc_html((string) ($entry['message'] ?? '')) . '</td><td><code>' . esc_html(implode(' · ', $pairs)) . '</code></td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div></div>';

        echo '<script>(()=>{const b=document.getElementById("vdm-copy-support-url");if(!b)return;b.addEventListener("click",async()=>{const u=b.dataset.url||window.location.href;try{await navigator.clipboard.writeText(u);b.textContent="Diagnose-link kopieret";setTimeout(()=>b.textContent="Kopiér diagnose-link",1800);}catch(e){window.prompt("Kopiér diagnose-link",u);}});})();</script>';
    }

    public static function clear(): void
    {
        self::guard();
        $postId = absint($_POST['post_id'] ?? 0);
        check_admin_referer(self::CLEAR_ACTION . '_' . $postId);
        if ($postId > 0) {
            DiagnosticStore::clearForPost($postId);
        } else {
            DiagnosticStore::clear();
        }
        $url = add_query_arg(['page' => ParityController::LOG_SLUG, 'post_id' => $postId, 'vdm_cleared' => '1'], admin_url('admin.php'));
        wp_safe_redirect($url, 303);
        exit;
    }

    private static function guard(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Du har ikke adgang til Log.', 'visual-designer-manager'));
        }
    }
}
