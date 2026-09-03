<?php

declare(strict_types=1);

namespace VisualDesignerManager\Admin;

use VisualDesignerManager\Navigation\NavigationRepository;

final class NavigationController
{
    public const MENU_SLUG = 'vdm-navigation';

    private function __construct()
    {
    }

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'menu'], 24);
    }

    public static function menu(): void
    {
        add_submenu_page(
            AdminController::MENU_SLUG,
            'Navigation',
            'Navigation',
            'edit_theme_options',
            self::MENU_SLUG,
            [self::class, 'render']
        );
    }

    public static function render(): void
    {
        if (!current_user_can('edit_theme_options')) {
            wp_die(esc_html__('Du har ikke adgang til Navigation.', 'visual-designer-manager'));
        }

        $menus = NavigationRepository::choices();
        echo '<div class="wrap">';
        echo '<h1>Visual Designer Manager · Navigation</h1>';
        echo '<p>VDM bruger WordPress-menuer som canonical navigationsdata. Design, placering og responsiv adfærd styres af Navigation-elementet i Designeren.</p>';
        echo '<p><a class="button button-primary" href="' . esc_url(admin_url('nav-menus.php?action=edit&menu=0')) . '">Opret / administrer menuer</a></p>';

        if ($menus === []) {
            echo '<div class="notice notice-info inline"><p>Der findes endnu ingen WordPress-menuer.</p></div></div>';
            return;
        }

        echo '<table class="widefat striped"><thead><tr><th>Menu</th><th>Menupunkter</th><th>Handling</th></tr></thead><tbody>';
        foreach ($menus as $menu) {
            $url = add_query_arg([
                'action' => 'edit',
                'menu' => (int) $menu['id'],
            ], admin_url('nav-menus.php'));
            echo '<tr><td><strong>' . esc_html((string) $menu['name']) . '</strong></td><td>' . esc_html((string) (int) $menu['count']) . '</td><td><a class="button" href="' . esc_url($url) . '">Rediger menu</a></td></tr>';
        }
        echo '</tbody></table>';
        echo '<p class="description">Vælg derefter menuen i Inspector på et Navigation-element i Side- eller Header/Footer-Designeren.</p>';
        echo '</div>';
    }
}
