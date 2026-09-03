<?php

declare(strict_types=1);

namespace VisualDesignerManager\Admin;

use VisualDesignerManager\Http\RestController;
use VisualDesignerManager\Storage\TemplateRepository;

final class TemplateDesignerController
{
    public const MENU_SLUG = 'vdm-header-footer';

    private function __construct()
    {
    }

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'menu'], 21);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
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
        if (strpos($hook, self::MENU_SLUG) === false) {
            return;
        }

        $slot = self::requestedSlot();
        wp_enqueue_media();
        wp_enqueue_style('vdm-frontend', VDM_URL . 'assets/frontend.css', [], VDM_VERSION);
        wp_enqueue_style('vdm-designer', VDM_URL . 'assets/designer.css', ['vdm-frontend'], VDM_VERSION);
        wp_enqueue_script('vdm-designer', VDM_URL . 'assets/designer.js', ['media-editor'], VDM_VERSION, true);

        wp_localize_script('vdm-designer', 'VDMDesignerConfig', [
            'ready' => true,
            'pageId' => 'global-' . $slot,
            'templateSlot' => $slot,
            'restBase' => esc_url_raw(rest_url(RestController::NAMESPACE)),
            'nonce' => wp_create_nonce('wp_rest'),
            'document' => TemplateRepository::get($slot),
            'version' => TemplateRepository::version($slot),
            'themeColors' => DesignerController::themeColors(),
        ]);
    }

    public static function render(): void
    {
        if (!current_user_can('edit_theme_options')) {
            wp_die(esc_html__('Du har ikke adgang til Header / Footer.', 'visual-designer-manager'));
        }

        $slot = self::requestedSlot();
        $labels = [
            TemplateRepository::HEADER => 'Header',
            TemplateRepository::FOOTER => 'Footer',
        ];

        echo '<div class="wrap vdm-designer-admin">';
        echo '<div class="vdm-designer-heading"><div><h1>Visual Designer Manager · ' . esc_html($labels[$slot]) . '</h1><p>Global V2-skabelon · Version ' . esc_html(VDM_VERSION) . '</p></div>';
        echo '<div class="vdm-template-tabs">';
        foreach ($labels as $key => $label) {
            $url = add_query_arg(['page' => self::MENU_SLUG, 'slot' => $key], admin_url('admin.php'));
            echo '<a class="button' . ($slot === $key ? ' button-primary' : '') . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a> ';
        }
        echo '</div></div>';

        echo '<div class="vdm-designer-toolbar">';
        echo '<div class="vdm-toolbar-left">';
        echo '<div class="vdm-breakpoints" role="group" aria-label="Breakpoint">';
        foreach (['desktop' => 'Desktop', 'laptop' => 'Laptop', 'tablet' => 'Tablet', 'mobile' => 'Mobil'] as $key => $label) {
            echo '<button type="button" class="button vdm-breakpoint' . ($key === 'desktop' ? ' is-active' : '') . '" data-breakpoint="' . esc_attr($key) . '">' . esc_html($label) . '</button>';
        }
        echo '</div>';
        echo '<button type="button" class="button" id="vdm-undo" disabled title="Fortryd (Ctrl+Z)">Fortryd</button>';
        echo '<button type="button" class="button" id="vdm-redo" disabled title="Annuller fortryd (Ctrl+Y)">Gentag</button>';
        echo '</div>';
        echo '<div class="vdm-toolbar-right"><span id="vdm-save-status" class="vdm-save-status">Ikke gemt</span> <button type="button" class="button button-primary" id="vdm-save" title="Gem (Ctrl+S)">Gem</button></div>';
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
        echo '<p><small>Header og Footer bruger samme canonical V2-model og renderer som siderne.</small></p>';
        echo '</aside>';

        echo '<main class="vdm-stage"><div class="vdm-stage-scroll"><div id="vdm-canvas" data-vdm-breakpoint="desktop" aria-label="Template Designer canvas"></div></div></main>';
        echo '<aside class="vdm-panel vdm-inspector"><h2>Indstillinger</h2><div id="vdm-inspector"><p>Vælg et element.</p></div></aside>';
        echo '</div></div>';
    }

    private static function requestedSlot(): string
    {
        $slot = sanitize_key((string) ($_GET['slot'] ?? TemplateRepository::HEADER));
        return in_array($slot, TemplateRepository::slots(), true) ? $slot : TemplateRepository::HEADER;
    }
}
