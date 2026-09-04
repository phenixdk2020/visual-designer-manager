<?php

declare(strict_types=1);

namespace VisualDesignerManager\Admin;

use VisualDesignerManager\Diagnostics\DiagnosticStore;

final class PageWorkflowController
{
    public const CREATE_PAGE_ACTION = 'vdm_create_designer_page';

    private function __construct()
    {
    }

    public static function register(): void
    {
        add_action('admin_post_' . self::CREATE_PAGE_ACTION, [self::class, 'createPage']);
    }

    /** @param array<int,\WP_Post> $pages */
    public static function renderCreateForm(array $pages): void
    {
        if (!current_user_can('edit_pages')) {
            return;
        }

        echo '<div class="card" style="max-width:980px;margin:16px 0 24px">';
        echo '<h2>Ny side</h2>';
        echo '<p>Opret en WordPress-side og åbn den direkte i Visual Designer. Første <strong>Gem som ny version</strong> opretter Visual Designer-version v1.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field(self::CREATE_PAGE_ACTION);
        echo '<input type="hidden" name="action" value="' . esc_attr(self::CREATE_PAGE_ACTION) . '">';
        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row"><label for="vdm-new-page-title">Titel</label></th><td><input class="regular-text" id="vdm-new-page-title" name="title" type="text" required></td></tr>';
        echo '<tr><th scope="row"><label for="vdm-new-page-slug">Slug</label></th><td><input class="regular-text" id="vdm-new-page-slug" name="slug" type="text" placeholder="Valgfri – WordPress genererer ellers automatisk"><p class="description">Lad feltet stå tomt for automatisk slug.</p></td></tr>';
        echo '<tr><th scope="row"><label for="vdm-new-page-parent">Overordnet side</label></th><td><select id="vdm-new-page-parent" name="parent_id"><option value="0">Ingen</option>';
        foreach ($pages as $page) {
            if (!$page instanceof \WP_Post) {
                continue;
            }
            echo '<option value="' . esc_attr((string) $page->ID) . '">' . esc_html((string) $page->post_title) . '</option>';
        }
        echo '</select></td></tr>';
        echo '<tr><th scope="row"><label for="vdm-new-page-status">Status</label></th><td><select id="vdm-new-page-status" name="status"><option value="draft">Kladde</option>';
        if (current_user_can('publish_pages')) {
            echo '<option value="publish">Publiceret</option>';
        }
        echo '</select></td></tr>';
        echo '</tbody></table>';
        submit_button('Opret og åbn Visual Designer', 'primary', 'submit', false);
        echo '</form></div>';
    }

    public static function createPage(): void
    {
        if (!current_user_can('edit_pages')) {
            wp_die(esc_html__('Du har ikke adgang til at oprette sider.', 'visual-designer-manager'));
        }
        check_admin_referer(self::CREATE_PAGE_ACTION);

        $title = sanitize_text_field((string) wp_unslash($_POST['title'] ?? ''));
        $slug = sanitize_title((string) wp_unslash($_POST['slug'] ?? ''));
        $parentId = absint($_POST['parent_id'] ?? 0);
        $requestedStatus = sanitize_key((string) wp_unslash($_POST['status'] ?? 'draft'));

        if ($title === '') {
            self::redirectPages('Sidetitel skal udfyldes.', 'error');
        }

        if ($parentId > 0) {
            if (get_post_type($parentId) !== 'page' || !current_user_can('edit_post', $parentId)) {
                self::redirectPages('Den valgte overordnede side er ugyldig.', 'error');
            }
        }

        $status = $requestedStatus === 'publish' && current_user_can('publish_pages') ? 'publish' : 'draft';
        $postarr = [
            'post_type' => 'page',
            'post_status' => $status,
            'post_title' => $title,
            'post_parent' => $parentId,
        ];
        if ($slug !== '') {
            $postarr['post_name'] = $slug;
        }

        $postId = wp_insert_post($postarr, true);
        if (is_wp_error($postId)) {
            DiagnosticStore::add('error', 'Oprettelse af Visual Designer-side fejlede.', [
                'message' => $postId->get_error_message(),
            ]);
            self::redirectPages('Siden kunne ikke oprettes: ' . $postId->get_error_message(), 'error');
        }

        DiagnosticStore::add('info', 'Ny side blev oprettet fra Sider og åbnet i Visual Designer.', [
            'postId' => (int) $postId,
            'status' => $status,
        ]);

        $url = add_query_arg([
            'page' => DesignerController::MENU_SLUG,
            'post_id' => (int) $postId,
            'vdm_page_created' => '1',
        ], admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }

    private static function redirectPages(string $message, string $type = 'success'): void
    {
        $url = add_query_arg([
            'page' => ParityController::PAGES_SLUG,
            'vdm_notice' => $type,
            'vdm_message' => $message,
        ], admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }
}
