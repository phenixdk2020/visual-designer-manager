<?php

declare(strict_types=1);

namespace VisualDesignerManager\Admin;

use VisualDesignerManager\Diagnostics\DiagnosticStore;
use VisualDesignerManager\Storage\LayoutRepository;
use VisualDesignerManager\Storage\TemplateAssignmentRepository;
use VisualDesignerManager\Storage\TemplateRepository;

final class PageLifecycleController
{
    private const DUPLICATE = 'vdm_duplicate_page';
    private const STATUS = 'vdm_page_status';
    private const TRASH = 'vdm_trash_page';

    private function __construct()
    {
    }

    public static function register(): void
    {
        add_action('admin_post_' . self::DUPLICATE, [self::class, 'duplicate']);
        add_action('admin_post_' . self::STATUS, [self::class, 'status']);
        add_action('admin_post_' . self::TRASH, [self::class, 'trash']);
        add_action('admin_footer', [self::class, 'injectActions']);
    }

    public static function injectActions(): void
    {
        if (!current_user_can('edit_pages') || sanitize_key((string) ($_GET['page'] ?? '')) !== ParityController::PAGES_SLUG) {
            return;
        }

        $pages = get_pages(['sort_column' => 'menu_order,post_title', 'post_status' => ['publish', 'draft', 'private']]);
        $config = [];
        foreach ($pages as $page) {
            if (!$page instanceof \WP_Post || !current_user_can('edit_post', (int) $page->ID)) {
                continue;
            }
            $id = (int) $page->ID;
            $config[(string) $id] = [
                'status' => (string) $page->post_status,
                'duplicateNonce' => wp_create_nonce(self::DUPLICATE . '_' . $id),
                'statusNonce' => wp_create_nonce(self::STATUS . '_' . $id),
                'trashNonce' => wp_create_nonce(self::TRASH . '_' . $id),
            ];
        }
        $json = wp_json_encode($config);
        if (!is_string($json)) {
            return;
        }
        ?>
        <script>
        (() => {
            const rows = <?php echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
            function actionForm(action, postId, nonce, label, className, confirmText) {
                const form = document.createElement('form');
                form.method = 'post';
                form.action = <?php echo wp_json_encode(admin_url('admin-post.php')); ?>;
                form.style.display = 'inline';
                [['action',action],['post_id',postId],['_wpnonce',nonce]].forEach(([name,value]) => {
                    const input = document.createElement('input'); input.type='hidden'; input.name=name; input.value=value; form.append(input);
                });
                const button = document.createElement('button'); button.type='submit'; button.className=className || 'button'; button.textContent=label;
                if (confirmText) button.addEventListener('click', event => { if (!window.confirm(confirmText)) event.preventDefault(); });
                form.append(button); return form;
            }
            document.querySelectorAll('table.widefat tbody tr').forEach(row => {
                const link = Array.from(row.querySelectorAll('a')).find(a => a.href.includes('page=vdm-designer') && a.href.includes('post_id='));
                if (!link) return;
                const id = new URL(link.href).searchParams.get('post_id');
                const data = rows[id];
                if (!data || row.querySelector('[data-vdm-page-lifecycle]')) return;
                const cell = row.lastElementChild; if (!cell) return;
                const wrap = document.createElement('span'); wrap.dataset.vdmPageLifecycle='1'; wrap.style.marginLeft='6px';
                wrap.append(actionForm('vdm_duplicate_page', id, data.duplicateNonce, 'Duplikér', 'button', 'Opret en ny kladdeside som kopi?'));
                wrap.append(document.createTextNode(' '));
                wrap.append(actionForm('vdm_page_status', id, data.statusNonce, data.status === 'publish' ? 'Gør til kladde' : 'Publicér', 'button', data.status === 'publish' ? 'Fjern siden fra offentlig visning?' : 'Publicér siden?'));
                wrap.append(document.createTextNode(' '));
                wrap.append(actionForm('vdm_trash_page', id, data.trashNonce, 'Papirkurv', 'button button-link-delete', 'Flyt siden til papirkurven?'));
                cell.append(wrap);
            });
        })();
        </script>
        <?php
    }

    public static function duplicate(): void
    {
        $postId = self::pageFromRequest(self::DUPLICATE);
        $source = get_post($postId);
        if (!$source instanceof \WP_Post) {
            self::redirect('Kildesiden findes ikke.', 'error');
        }

        $newId = wp_insert_post([
            'post_type' => 'page',
            'post_status' => 'draft',
            'post_title' => trim((string) $source->post_title) . ' – kopi',
            'post_content' => (string) $source->post_content,
            'post_excerpt' => (string) $source->post_excerpt,
            'post_parent' => (int) $source->post_parent,
            'menu_order' => (int) $source->menu_order,
            'comment_status' => 'closed',
            'ping_status' => 'closed',
        ], true);
        if (is_wp_error($newId)) {
            self::redirect('Siden kunne ikke duplikeres: ' . $newId->get_error_message(), 'error');
        }

        $thumbnail = get_post_thumbnail_id($postId);
        if ($thumbnail > 0) {
            set_post_thumbnail((int) $newId, $thumbnail);
        }
        $document = LayoutRepository::get($postId);
        if (($document['nodes'] ?? []) !== []) {
            try {
                LayoutRepository::save((int) $newId, $document, get_current_user_id());
            } catch (\Throwable $error) {
                wp_delete_post((int) $newId, true);
                self::redirect('Layoutet kunne ikke kopieres: ' . $error->getMessage(), 'error');
            }
        }
        foreach (TemplateRepository::slots() as $slot) {
            $choice = TemplateAssignmentRepository::getChoice($postId, $slot);
            TemplateAssignmentRepository::saveChoice((int) $newId, $slot, $choice);
        }

        DiagnosticStore::add('info', 'Visual Designer-side blev duplikeret.', ['sourcePostId' => $postId, 'postId' => (int) $newId]);
        $url = add_query_arg(['page' => DesignerController::MENU_SLUG, 'post_id' => (int) $newId], admin_url('admin.php'));
        wp_safe_redirect($url, 303);
        exit;
    }

    public static function status(): void
    {
        $postId = self::pageFromRequest(self::STATUS);
        $current = (string) get_post_status($postId);
        $next = $current === 'publish' ? 'draft' : 'publish';
        if ($next === 'publish' && !current_user_can('publish_pages')) {
            self::redirect('Du har ikke rettigheder til at publicere sider.', 'error');
        }
        $result = wp_update_post(['ID' => $postId, 'post_status' => $next], true);
        if (is_wp_error($result)) {
            self::redirect('Sidestatus kunne ikke ændres: ' . $result->get_error_message(), 'error');
        }
        DiagnosticStore::add('info', 'Sidestatus blev ændret.', ['postId' => $postId, 'status' => $next]);
        self::redirect($next === 'publish' ? 'Siden er publiceret.' : 'Siden er nu kladde.');
    }

    public static function trash(): void
    {
        $postId = self::pageFromRequest(self::TRASH);
        if (!current_user_can('delete_post', $postId)) {
            self::redirect('Du har ikke rettigheder til at flytte siden til papirkurven.', 'error');
        }
        if (!wp_trash_post($postId)) {
            self::redirect('Siden kunne ikke flyttes til papirkurven.', 'error');
        }
        DiagnosticStore::add('info', 'Visual Designer-side blev flyttet til papirkurven.', ['postId' => $postId]);
        self::redirect('Siden er flyttet til papirkurven.');
    }

    private static function pageFromRequest(string $action): int
    {
        if (!current_user_can('edit_pages')) {
            wp_die(esc_html__('Du har ikke adgang.', 'visual-designer-manager'));
        }
        $postId = absint($_POST['post_id'] ?? 0);
        if ($postId <= 0 || get_post_type($postId) !== 'page' || !current_user_can('edit_post', $postId)) {
            self::redirect('Ugyldig side.', 'error');
        }
        check_admin_referer($action . '_' . $postId);
        return $postId;
    }

    private static function redirect(string $message, string $type = 'success'): void
    {
        $url = add_query_arg(['page' => ParityController::PAGES_SLUG, 'vdm_notice' => $type, 'vdm_message' => $message], admin_url('admin.php'));
        wp_safe_redirect($url, 303);
        exit;
    }
}
