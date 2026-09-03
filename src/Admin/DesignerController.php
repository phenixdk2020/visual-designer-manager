<?php

declare(strict_types=1);

namespace VisualDesignerManager\Admin;

use VisualDesignerManager\Http\RestController;
use VisualDesignerManager\Storage\LayoutRepository;

final class DesignerController
{
    public const MENU_SLUG = 'vdm-designer';

    private function __construct()
    {
    }

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'menu'], 20);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
    }

    public static function menu(): void
    {
        add_submenu_page(
            AdminController::MENU_SLUG,
            'Designer',
            'Designer',
            'edit_pages',
            self::MENU_SLUG,
            [self::class, 'render']
        );
    }

    public static function enqueue(string $hook): void
    {
        if (strpos($hook, self::MENU_SLUG) === false) {
            return;
        }

        wp_enqueue_style('vdm-frontend', VDM_URL . 'assets/frontend.css', [], VDM_VERSION);
        wp_enqueue_style('vdm-designer', VDM_URL . 'assets/designer.css', ['vdm-frontend'], VDM_VERSION);
        wp_enqueue_script('vdm-designer', VDM_URL . 'assets/designer.js', [], VDM_VERSION, true);

        $postId = self::requestedPageId();
        wp_localize_script('vdm-designer', 'VDMDesignerConfig', [
            'pageId' => $postId,
            'restBase' => esc_url_raw(rest_url(RestController::NAMESPACE)),
            'nonce' => wp_create_nonce('wp_rest'),
            'document' => $postId > 0 ? LayoutRepository::get($postId) : null,
            'version' => $postId > 0 ? LayoutRepository::version($postId) : 0,
        ]);
    }

    public static function render(): void
    {
        if (!current_user_can('edit_pages')) {
            wp_die(esc_html__('Du har ikke adgang til Designer.', 'visual-designer-manager'));
        }

        $pages = get_pages([
            'sort_column' => 'post_title',
            'sort_order' => 'ASC',
            'post_status' => ['publish', 'draft', 'private'],
        ]);
        $postId = self::requestedPageId();

        echo '<div class="wrap vdm-designer-admin">';
        echo '<div class="vdm-designer-heading"><div><h1>Visual Designer Manager · Designer</h1><p>Version ' . esc_html(VDM_VERSION) . '</p></div>';
        echo '<form method="get"><input type="hidden" name="page" value="' . esc_attr(self::MENU_SLUG) . '"><label for="vdm-page-select">Side</label> <select id="vdm-page-select" name="post_id" onchange="this.form.submit()">';
        echo '<option value="0">Vælg side</option>';
        foreach ($pages as $page) {
            echo '<option value="' . esc_attr((string) $page->ID) . '"' . selected($postId, (int) $page->ID, false) . '>' . esc_html((string) $page->post_title) . '</option>';
        }
        echo '</select></form></div>';

        if ($postId <= 0) {
            echo '<div class="notice notice-info"><p>Vælg en WordPress-side for at åbne V2 Designeren.</p></div></div>';
            return;
        }

        echo '<div class="vdm-designer-toolbar">';
        echo '<div class="vdm-breakpoints" role="group" aria-label="Breakpoint">';
        foreach (['desktop' => 'Desktop', 'laptop' => 'Laptop', 'tablet' => 'Tablet', 'mobile' => 'Mobil'] as $key => $label) {
            echo '<button type="button" class="button vdm-breakpoint' . ($key === 'desktop' ? ' is-active' : '') . '" data-breakpoint="' . esc_attr($key) . '">' . esc_html($label) . '</button>';
        }
        echo '</div>';
        echo '<div><span id="vdm-save-status" class="vdm-save-status">Ikke gemt</span> <button type="button" class="button button-primary" id="vdm-save">Gem</button></div>';
        echo '</div>';

        echo '<div class="vdm-workspace">';
        echo '<aside class="vdm-panel vdm-palette"><h2>Elementer</h2>';
        foreach ([
            'section' => 'Sektion',
            'container' => 'Kasse',
            'text' => 'Tekst',
            'image' => 'Billede',
            'button' => 'Knap',
            'spacer' => 'Mellemrum',
            'divider' => 'Skillelinje',
        ] as $type => $label) {
            echo '<button type="button" class="button vdm-palette-item" data-node-type="' . esc_attr($type) . '">' . esc_html($label) . '</button>';
        }
        echo '</aside>';

        echo '<main class="vdm-stage"><div class="vdm-stage-scroll"><div id="vdm-canvas" data-vdm-breakpoint="desktop" aria-label="Designer canvas"></div></div></main>';
        echo '<aside class="vdm-panel vdm-inspector"><h2>Indstillinger</h2><div id="vdm-inspector"><p>Vælg et element.</p></div></aside>';
        echo '</div></div>';
    }

    private static function requestedPageId(): int
    {
        $postId = absint($_GET['post_id'] ?? 0);
        if ($postId > 0 && get_post_type($postId) === 'page' && current_user_can('edit_post', $postId)) {
            return $postId;
        }
        return 0;
    }
}
