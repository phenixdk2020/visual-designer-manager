<?php

declare(strict_types=1);

namespace VisualDesignerManager\Admin;

use VisualDesignerManager\Storage\TemplateAssignmentRepository;
use VisualDesignerManager\Storage\TemplateRepository;

final class TemplateAssignmentController
{
    private const ACTION = 'vdm_save_template_assignments';

    private function __construct()
    {
    }

    public static function register(): void
    {
        add_action('admin_post_' . self::ACTION, [self::class, 'save']);
        add_action('admin_notices', [self::class, 'renderDesignerControls']);
    }

    public static function renderDesignerControls(): void
    {
        if (!current_user_can('edit_pages')) {
            return;
        }
        $page = sanitize_key((string) ($_GET['page'] ?? ''));
        if ($page !== DesignerController::MENU_SLUG) {
            return;
        }
        $postId = absint($_GET['post_id'] ?? 0);
        if ($postId <= 0 || get_post_type($postId) !== 'page' || !current_user_can('edit_post', $postId)) {
            return;
        }

        $headerChoice = TemplateAssignmentRepository::getChoice($postId, TemplateRepository::HEADER);
        $footerChoice = TemplateAssignmentRepository::getChoice($postId, TemplateRepository::FOOTER);
        $updated = sanitize_key((string) ($_GET['vdm_assignments'] ?? '')) === 'saved';

        echo '<div class="notice notice-info" style="padding:12px 14px;margin:12px 20px 14px 2px">';
        if ($updated) {
            echo '<p><strong>Header/Footer-valg er gemt.</strong></p>';
        }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:flex;gap:14px;align-items:end;flex-wrap:wrap">';
        wp_nonce_field(self::ACTION . '_' . $postId);
        echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION) . '"><input type="hidden" name="post_id" value="' . esc_attr((string) $postId) . '">';
        echo '<label><strong>Header på denne side</strong><br>';
        self::select(TemplateRepository::HEADER, $headerChoice);
        echo '</label><label><strong>Footer på denne side</strong><br>';
        self::select(TemplateRepository::FOOTER, $footerChoice);
        echo '</label><button class="button" type="submit">Gem Header/Footer-valg</button>';
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=' . TemplateDesignerController::MENU_SLUG)) . '">Administrér templates</a>';
        echo '<span class="description">Resolver: sidevalg → website-standard → tom fallback. “Ingen” slår regionen helt fra.</span>';
        echo '</form></div>';
    }

    public static function save(): void
    {
        if (!current_user_can('edit_pages')) {
            wp_die(esc_html__('Du har ikke adgang.', 'visual-designer-manager'));
        }
        $postId = absint($_POST['post_id'] ?? 0);
        if ($postId <= 0 || get_post_type($postId) !== 'page' || !current_user_can('edit_post', $postId)) {
            wp_die(esc_html__('Ugyldig side.', 'visual-designer-manager'));
        }
        check_admin_referer(self::ACTION . '_' . $postId);

        try {
            TemplateAssignmentRepository::saveChoice(
                $postId,
                TemplateRepository::HEADER,
                sanitize_key((string) ($_POST['header_choice'] ?? 'auto'))
            );
            TemplateAssignmentRepository::saveChoice(
                $postId,
                TemplateRepository::FOOTER,
                sanitize_key((string) ($_POST['footer_choice'] ?? 'auto'))
            );
        } catch (\Throwable $error) {
            wp_die(esc_html($error->getMessage()));
        }

        $url = add_query_arg([
            'page' => DesignerController::MENU_SLUG,
            'post_id' => $postId,
            'vdm_assignments' => 'saved',
        ], admin_url('admin.php'));
        wp_safe_redirect($url, 303);
        exit;
    }

    private static function select(string $slot, string $selected): void
    {
        $name = $slot === TemplateRepository::HEADER ? 'header_choice' : 'footer_choice';
        echo '<select name="' . esc_attr($name) . '">';
        echo '<option value="auto"' . selected($selected, 'auto', false) . '>Automatisk / standard</option>';
        echo '<option value="none"' . selected($selected, 'none', false) . '>Ingen ' . esc_html(ucfirst($slot)) . '</option>';
        foreach (TemplateRepository::all($slot) as $row) {
            if (empty($row['active'])) {
                continue;
            }
            $id = sanitize_key((string) ($row['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $label = (string) ($row['name'] ?? $id);
            if (!empty($row['isDefault'])) {
                $label .= ' · standard';
            }
            echo '<option value="' . esc_attr($id) . '"' . selected($selected, $id, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }
}
