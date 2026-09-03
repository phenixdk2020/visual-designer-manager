<?php

declare(strict_types=1);

namespace VisualDesignerManager\Admin;

use VisualDesignerManager\Storage\SiteSettingsRepository;

final class SiteSettingsController
{
    public const MENU_SLUG = 'vdm-site-settings';

    private function __construct()
    {
    }

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'menu'], 23);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
        add_action('admin_post_vdm_save_site_settings', [self::class, 'save']);
    }

    public static function menu(): void
    {
        add_submenu_page(
            AdminController::MENU_SLUG,
            'Siteindstillinger',
            'Siteindstillinger',
            'manage_options',
            self::MENU_SLUG,
            [self::class, 'render']
        );
    }

    public static function enqueue(string $hook): void
    {
        if (strpos($hook, self::MENU_SLUG) === false) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_script('vdm-site-settings', VDM_URL . 'assets/site-settings.js', ['media-editor'], VDM_VERSION, true);
    }

    public static function save(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Du har ikke adgang til Siteindstillinger.', 'visual-designer-manager'));
        }
        check_admin_referer('vdm_save_site_settings');

        $raw = isset($_POST['vdm_site_settings']) && is_array($_POST['vdm_site_settings'])
            ? wp_unslash($_POST['vdm_site_settings'])
            : [];
        if (!is_array($raw)) {
            $raw = [];
        }

        SiteSettingsRepository::save($raw);
        $url = add_query_arg([
            'page' => self::MENU_SLUG,
            'updated' => '1',
        ], admin_url('admin.php'));
        wp_safe_redirect($url, 303);
        exit;
    }

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Du har ikke adgang til Siteindstillinger.', 'visual-designer-manager'));
        }

        $settings = SiteSettingsRepository::get();
        echo '<div class="wrap">';
        echo '<h1>Visual Designer Manager · Siteindstillinger</h1>';
        echo '<p>Canonical V2-identitet og kontaktoplysninger. VDM-logoet er uafhængigt af det aktive temas <code>custom_logo</code>.</p>';
        if (isset($_GET['updated']) && sanitize_key((string) wp_unslash($_GET['updated'])) === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>Siteindstillinger er gemt.</p></div>';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="vdm_save_site_settings">';
        wp_nonce_field('vdm_save_site_settings');
        echo '<table class="form-table" role="presentation"><tbody>';
        self::textRow('Webstedstitel', 'siteTitle', (string) $settings['siteTitle'], 'WordPress blogname.');
        self::textRow('Slogan', 'tagline', (string) $settings['tagline'], 'WordPress blogdescription.');
        self::textRow('Virksomhed / forening', 'organizationName', (string) $settings['organizationName'], 'VDM-identitet til eksport/import og dynamisk siteindhold.');
        self::emailRow('Kontakt-e-mail', 'contactEmail', (string) $settings['contactEmail'], 'Modtager for VDM Kontaktformular og Bliv medlem. Hvis feltet er tomt, bruges WordPress-administratorens e-mail.');
        self::textRow('Kontakttelefon', 'contactPhone', (string) $settings['contactPhone'], 'Canonical VDM-kontakttelefon.');
        self::mediaRow('VDM-logo', 'logoId', (int) $settings['logoId'], 'Logo gemt af VDM og ikke bundet til temaets custom_logo.');
        self::mediaRow('Site-ikon / favicon', 'siteIconId', (int) $settings['siteIconId'], 'WordPress site_icon. Et kvadratisk billede på mindst 512×512 px anbefales.');

        echo '<tr><th scope="row">Hjemadresse</th><td><code>' . esc_html((string) $settings['homeUrl']) . '</code><p class="description">Read-only. Ændres i WordPress’ generelle indstillinger.</p></td></tr>';
        echo '<tr><th scope="row">WordPress-adresse</th><td><code>' . esc_html((string) $settings['siteUrl']) . '</code><p class="description">Read-only. Ændres i WordPress’ generelle indstillinger.</p></td></tr>';
        echo '</tbody></table>';
        echo '<p class="submit"><button type="submit" class="button button-primary">Gem Siteindstillinger</button></p>';
        echo '</form></div>';
    }

    private static function textRow(string $label, string $name, string $value, string $help): void
    {
        echo '<tr><th scope="row"><label for="vdm-' . esc_attr($name) . '">' . esc_html($label) . '</label></th><td>';
        echo '<input class="regular-text" type="text" id="vdm-' . esc_attr($name) . '" name="vdm_site_settings[' . esc_attr($name) . ']" value="' . esc_attr($value) . '">';
        echo '<p class="description">' . esc_html($help) . '</p></td></tr>';
    }

    private static function emailRow(string $label, string $name, string $value, string $help): void
    {
        echo '<tr><th scope="row"><label for="vdm-' . esc_attr($name) . '">' . esc_html($label) . '</label></th><td>';
        echo '<input class="regular-text" type="email" id="vdm-' . esc_attr($name) . '" name="vdm_site_settings[' . esc_attr($name) . ']" value="' . esc_attr($value) . '">';
        echo '<p class="description">' . esc_html($help) . '</p></td></tr>';
    }

    private static function mediaRow(string $label, string $name, int $attachmentId, string $help): void
    {
        $url = $attachmentId > 0 ? wp_get_attachment_image_url($attachmentId, 'medium') : false;
        $url = is_string($url) ? $url : '';
        echo '<tr><th scope="row">' . esc_html($label) . '</th><td>';
        echo '<input type="hidden" class="vdm-site-media-id" id="vdm-' . esc_attr($name) . '" name="vdm_site_settings[' . esc_attr($name) . ']" value="' . esc_attr((string) $attachmentId) . '">';
        echo '<div class="vdm-site-media-preview" data-vdm-media-preview style="margin-bottom:8px;min-height:24px">';
        if ($url !== '') {
            echo '<img src="' . esc_url($url) . '" alt="" style="display:block;max-width:220px;max-height:120px;width:auto;height:auto;border:1px solid #dcdcde;background:#fff;padding:4px">';
        } else {
            echo '<span class="description">Intet billede valgt.</span>';
        }
        echo '</div>';
        echo '<button type="button" class="button vdm-site-media-select" data-target="vdm-' . esc_attr($name) . '">Vælg billede</button> ';
        echo '<button type="button" class="button vdm-site-media-clear" data-target="vdm-' . esc_attr($name) . '">Fjern</button>';
        echo '<p class="description">' . esc_html($help) . '</p></td></tr>';
    }
}
