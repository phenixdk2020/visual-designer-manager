<?php

declare(strict_types=1);

namespace VisualDesignerManager\Admin;

final class AdminController
{
    public const MENU_SLUG = 'vdm-manager';

    private function __construct()
    {
    }

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'menu']);
    }

    public static function menu(): void
    {
        add_menu_page(
            'Visual Designer Manager',
            'Visual Designer Manager',
            'edit_pages',
            self::MENU_SLUG,
            [self::class, 'renderDashboard'],
            'dashicons-layout',
            3
        );
    }

    public static function renderDashboard(): void
    {
        if (!current_user_can('edit_pages')) {
            wp_die(esc_html__('Du har ikke adgang til Visual Designer Manager.', 'visual-designer-manager'));
        }

        $designerUrl = admin_url('admin.php?page=' . DesignerController::MENU_SLUG);

        echo '<div class="wrap">';
        echo '<h1>Visual Designer Manager</h1>';
        echo '<p><strong>Version ' . esc_html(VDM_VERSION) . '</strong></p>';
        echo '<p>V2-kernen bruger sin egen layoutmodel, renderer, lagring og API-kontrakt.</p>';
        echo '<p><a class="button button-primary" href="' . esc_url($designerUrl) . '">Åbn Designer</a></p>';
        echo '</div>';
    }
}
