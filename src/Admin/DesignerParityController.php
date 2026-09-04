<?php

declare(strict_types=1);

namespace VisualDesignerManager\Admin;

final class DesignerParityController
{
    private function __construct()
    {
    }

    public static function register(): void
    {
        add_action('admin_enqueue_scripts', [self::class, 'enqueue'], 50);
    }

    public static function enqueue(string $hook): void
    {
        $page = sanitize_key((string) ($_GET['page'] ?? ''));
        if (!in_array($page, [DesignerController::MENU_SLUG, TemplateDesignerController::MENU_SLUG], true)) {
            return;
        }
        if (!current_user_can('edit_pages') && !current_user_can('edit_theme_options')) {
            return;
        }

        wp_enqueue_style('vdm-parity', VDM_URL . 'assets/parity.css', ['vdm-frontend'], VDM_VERSION);
        wp_enqueue_style('vdm-designer-parity', VDM_URL . 'assets/designer-parity.css', ['vdm-designer'], VDM_VERSION);
        wp_enqueue_script('vdm-designer-parity', VDM_URL . 'assets/designer-parity.js', ['vdm-designer'], VDM_VERSION, true);
    }
}
