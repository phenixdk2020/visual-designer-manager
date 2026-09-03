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
        $templateUrl = admin_url('admin.php?page=' . TemplateDesignerController::MENU_SLUG);
        $siteDesignUrl = admin_url('admin.php?page=' . SiteDesignController::MENU_SLUG);
        $siteSettingsUrl = admin_url('admin.php?page=' . SiteSettingsController::MENU_SLUG);
        $navigationUrl = admin_url('admin.php?page=' . NavigationController::MENU_SLUG);

        echo '<div class="wrap">';
        echo '<h1>Visual Designer Manager</h1>';
        echo '<p><strong>Version ' . esc_html(VDM_VERSION) . '</strong></p>';
        echo '<p>V2-kernen bruger sin egen layoutmodel, renderer, lagring, globale skabeloner og API-kontrakt.</p>';
        echo '<p><a class="button button-primary" href="' . esc_url($designerUrl) . '">Åbn Designer</a> ';
        if (current_user_can('edit_theme_options')) {
            echo '<a class="button" href="' . esc_url($templateUrl) . '">Header / Footer</a> ';
            echo '<a class="button" href="' . esc_url($siteDesignUrl) . '">Site Design</a> ';
            if (current_user_can('manage_options')) {
                echo '<a class="button" href="' . esc_url($siteSettingsUrl) . '">Siteindstillinger</a> ';
            }
            echo '<a class="button" href="' . esc_url($navigationUrl) . '">Navigation</a>';
        }
        echo '</p></div>';
    }
}
