<?php

declare(strict_types=1);

namespace VisualDesignerManager\Admin;

use VisualDesignerManager\Storage\SiteDesignRepository;

final class SiteDesignController
{
    public const MENU_SLUG = 'vdm-site-design';

    private function __construct()
    {
    }

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'menu'], 22);
    }

    public static function menu(): void
    {
        add_submenu_page(
            AdminController::MENU_SLUG,
            'Site Design',
            'Site Design',
            'edit_theme_options',
            self::MENU_SLUG,
            [self::class, 'render']
        );
    }

    public static function render(): void
    {
        if (!current_user_can('edit_theme_options')) {
            wp_die(esc_html__('Du har ikke adgang til Site Design.', 'visual-designer-manager'));
        }

        $saved = false;
        if (isset($_POST['vdm_site_design_submit'])) {
            check_admin_referer('vdm_save_site_design');
            $raw = [
                'shellEnabled' => isset($_POST['shellEnabled']),
                'maxWidth' => (int) ($_POST['maxWidth'] ?? 1440),
                'contentPadding' => (int) ($_POST['contentPadding'] ?? 24),
                'pageBackground' => sanitize_text_field((string) wp_unslash($_POST['pageBackground'] ?? '#ffffff')),
                'textColor' => sanitize_text_field((string) wp_unslash($_POST['textColor'] ?? '#222222')),
                'headingColor' => sanitize_text_field((string) wp_unslash($_POST['headingColor'] ?? '#222222')),
                'linkColor' => sanitize_text_field((string) wp_unslash($_POST['linkColor'] ?? '#2271b1')),
                'baseFontSize' => (int) ($_POST['baseFontSize'] ?? 16),
                'fontFamily' => sanitize_key((string) ($_POST['fontFamily'] ?? 'theme')),
            ];
            SiteDesignRepository::save($raw);
            $saved = true;
        }

        $design = SiteDesignRepository::get();
        echo '<div class="wrap">';
        echo '<h1>Visual Designer Manager · Site Design</h1>';
        echo '<p>Globale V2-designværdier for VDM-site-shell, sider, Header og Footer.</p>';
        if ($saved) {
            echo '<div class="notice notice-success is-dismissible"><p>Site Design er gemt.</p></div>';
        }

        echo '<form method="post">';
        wp_nonce_field('vdm_save_site_design');
        echo '<table class="form-table" role="presentation"><tbody>';
        self::checkboxRow('Brug VDM som sideskal', 'shellEnabled', (bool) $design['shellEnabled'], 'Når den er aktiv, renderer VDM hele V2-siden med egne Header/Footer-skabeloner i stedet for temaets side-template.');
        self::numberRow('Maksimal indholdsbredde', 'maxWidth', (int) $design['maxWidth'], 640, 2400, 'px');
        self::numberRow('Vandret sidepadding', 'contentPadding', (int) $design['contentPadding'], 0, 160, 'px');
        self::colorRow('Sidebaggrund', 'pageBackground', (string) $design['pageBackground']);
        self::colorRow('Tekstfarve', 'textColor', (string) $design['textColor']);
        self::colorRow('Overskriftsfarve', 'headingColor', (string) $design['headingColor']);
        self::colorRow('Linkfarve', 'linkColor', (string) $design['linkColor']);
        self::numberRow('Grundskriftstørrelse', 'baseFontSize', (int) $design['baseFontSize'], 12, 28, 'px');

        echo '<tr><th scope="row"><label for="fontFamily">Skrifttype</label></th><td><select id="fontFamily" name="fontFamily">';
        foreach (['theme' => 'Tema / arvet', 'system' => 'Systemskrift', 'serif' => 'Serif'] as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"' . selected((string) $design['fontFamily'], $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></td></tr>';
        echo '</tbody></table>';
        echo '<p class="submit"><button type="submit" name="vdm_site_design_submit" value="1" class="button button-primary">Gem Site Design</button></p>';
        echo '</form></div>';
    }

    private static function checkboxRow(string $label, string $name, bool $checked, string $help): void
    {
        echo '<tr><th scope="row">' . esc_html($label) . '</th><td><label><input type="checkbox" name="' . esc_attr($name) . '" value="1"' . checked($checked, true, false) . '> Aktiv</label>';
        echo '<p class="description">' . esc_html($help) . '</p></td></tr>';
    }

    private static function numberRow(string $label, string $name, int $value, int $min, int $max, string $suffix): void
    {
        echo '<tr><th scope="row"><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label></th><td>';
        echo '<input class="small-text" type="number" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" min="' . esc_attr((string) $min) . '" max="' . esc_attr((string) $max) . '" value="' . esc_attr((string) $value) . '"> ' . esc_html($suffix);
        echo '</td></tr>';
    }

    private static function colorRow(string $label, string $name, string $value): void
    {
        echo '<tr><th scope="row"><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label></th><td>';
        echo '<span style="display:inline-block;width:24px;height:24px;vertical-align:middle;border:1px solid #8c8f94;background:' . esc_attr($value) . '"></span> ';
        echo '<input class="regular-text code" type="text" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" pattern="#[0-9A-Fa-f]{6}" maxlength="7">';
        echo '</td></tr>';
    }
}
