<?php

declare(strict_types=1);

namespace VisualDesignerManager\Admin;

/**
 * Canonical VDM user manual shown both in wp-admin and on a public WordPress
 * page. A Word-compatible .docx is generated on demand from the same source.
 */
final class ManualController
{
    public const PAGE_SLUG = 'visual-designer-brugermanual';
    public const SHORTCODE = 'visual_designer_manager_manual';
    private const PAGE_OPTION = 'vdm_user_manual_page_id_v2';
    private const DOWNLOAD_ACTION = 'vdm_download_user_manual_docx';

    private function __construct()
    {
    }

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'replaceMenu'], 1003);
        add_action('admin_init', [self::class, 'ensurePage'], 40);
        add_shortcode(self::SHORTCODE, [self::class, 'shortcode']);
        add_action('admin_post_' . self::DOWNLOAD_ACTION, [self::class, 'downloadDocx']);
        add_action('wp_enqueue_scripts', [self::class, 'frontendStyle']);
    }

    public static function replaceMenu(): void
    {
        remove_submenu_page(AdminController::MENU_SLUG, ParityController::MANUAL_SLUG);
        add_submenu_page(
            AdminController::MENU_SLUG,
            'Brugermanual',
            'Brugermanual',
            'edit_pages',
            ParityController::MANUAL_SLUG,
            [self::class, 'adminPage']
        );
        ParityController::normalizeMenu();
    }

    public static function ensurePage(): void
    {
        if (!current_user_can('edit_pages')) {
            return;
        }
        $storedId = absint(get_option(self::PAGE_OPTION, 0));
        if ($storedId > 0) {
            $stored = get_post($storedId);
            if ($stored instanceof \WP_Post && $stored->post_type === 'page' && $stored->post_status !== 'trash') {
                return;
            }
        }
        $existing = get_page_by_path(self::PAGE_SLUG, OBJECT, 'page');
        if ($existing instanceof \WP_Post && $existing->post_status !== 'trash') {
            if (!has_shortcode((string) $existing->post_content, self::SHORTCODE)) {
                wp_update_post(['ID' => (int) $existing->ID, 'post_content' => '[' . self::SHORTCODE . ']']);
            }
            update_option(self::PAGE_OPTION, (int) $existing->ID, false);
            return;
        }
        $pageId = wp_insert_post([
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_title' => 'Brugermanual',
            'post_name' => self::PAGE_SLUG,
            'post_content' => '[' . self::SHORTCODE . ']',
            'comment_status' => 'closed',
            'ping_status' => 'closed',
        ], true);
        if (!is_wp_error($pageId) && (int) $pageId > 0) {
            update_option(self::PAGE_OPTION, (int) $pageId, false);
        }
    }

    public static function adminPage(): void
    {
        if (!current_user_can('edit_pages')) {
            wp_die(esc_html__('Du har ikke adgang.', 'visual-designer-manager'));
        }
        self::ensurePage();
        echo '<div class="wrap"><h1>Brugermanual</h1><p>Samme kanoniske manual vises her, på websitet og i Word-download.</p>';
        echo self::toolbar(true); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo self::manualHtml(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</div>';
    }

    public static function shortcode(): string
    {
        return '<div class="vdm-user-manual">' . self::toolbar(false) . self::manualHtml() . '</div>';
    }

    public static function frontendStyle(): void
    {
        if (!is_singular('page')) {
            return;
        }
        $post = get_post();
        if (!$post instanceof \WP_Post || !has_shortcode((string) $post->post_content, self::SHORTCODE)) {
            return;
        }
        wp_register_style('vdm-manual-inline', false, [], VDM_VERSION);
        wp_enqueue_style('vdm-manual-inline');
        wp_add_inline_style('vdm-manual-inline', '.vdm-user-manual{max-width:1100px;margin:24px auto;padding:0 20px}.vdm-user-manual article{background:#fff;border:1px solid #ddd;padding:28px}.vdm-user-manual table{border-collapse:collapse;width:100%}.vdm-user-manual th,.vdm-user-manual td{border:1px solid #ddd;padding:8px;text-align:left}.vdm-user-manual-toolbar{display:flex;gap:10px;flex-wrap:wrap;margin:12px 0 20px}.vdm-user-manual-toolbar a{display:inline-block;padding:9px 13px;border:1px solid #2271b1;text-decoration:none;border-radius:4px}.vdm-user-manual-toolbar a.is-primary{background:#2271b1;color:#fff}');
    }

    public static function downloadDocx(): void
    {
        if (!current_user_can('edit_pages')) {
            wp_die(esc_html__('Du har ikke adgang.', 'visual-designer-manager'));
        }
        check_admin_referer(self::DOWNLOAD_ACTION);
        if (!class_exists('ZipArchive')) {
            wp_die(esc_html__('PHP ZipArchive er nødvendig for Word-download.', 'visual-designer-manager'));
        }
        $tmp = wp_tempnam('visual-designer-manager-brugermanual.docx');
        if (!is_string($tmp) || $tmp === '') {
            wp_die(esc_html__('Kunne ikke oprette Word-fil.', 'visual-designer-manager'));
        }
        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            wp_die(esc_html__('Kunne ikke oprette Word-fil.', 'visual-designer-manager'));
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $zip->addFromString('word/document.xml', self::documentXml());
        $zip->close();
        $size = filesize($tmp);
        if (!is_int($size) || $size <= 0) {
            @unlink($tmp);
            wp_die(esc_html__('Word-filen blev ikke oprettet korrekt.', 'visual-designer-manager'));
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        nocache_headers();
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="visual-designer-manager-brugermanual.docx"');
        header('Content-Length: ' . (string) $size);
        header('X-Content-Type-Options: nosniff');
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    private static function toolbar(bool $admin): string
    {
        $download = wp_nonce_url(admin_url('admin-post.php?action=' . self::DOWNLOAD_ACTION), self::DOWNLOAD_ACTION);
        $website = self::websiteUrl();
        $html = '<div class="vdm-user-manual-toolbar">';
        if ($admin) {
            $html .= '<a class="button button-primary" target="_blank" rel="noopener" href="' . esc_url($website) . '">Åbn på websitet</a>';
            $html .= '<a class="button" href="' . esc_url($download) . '">Download som Word (.docx)</a>';
        } else {
            $html .= '<a class="is-primary" href="' . esc_url(admin_url('admin.php?page=' . AdminController::MENU_SLUG)) . '">Åbn Visual Designer Manager</a>';
            if (is_user_logged_in() && current_user_can('edit_pages')) {
                $html .= '<a href="' . esc_url($download) . '">Download som Word (.docx)</a>';
            }
        }
        return $html . '</div>';
    }

    private static function websiteUrl(): string
    {
        $pageId = absint(get_option(self::PAGE_OPTION, 0));
        $url = $pageId > 0 ? get_permalink($pageId) : false;
        return is_string($url) && $url !== '' ? $url : home_url('/' . self::PAGE_SLUG . '/');
    }

    private static function manualHtml(): string
    {
        $version = esc_html(VDM_VERSION);
        return '<article class="vdm-user-manual-content">'
            . '<h1>Visual Designer Manager – Brugermanual</h1><p><strong>Version ' . $version . '</strong></p>'
            . '<h2>1. Dashboard</h2><p>Dashboardet samler adgang til Visual Designer, sider, indholdsmoduler, Header/Footer, Menu, Tema/Site Design, Siteindstillinger, Backup, Eksport/import, Opdateringer, Log og denne manual.</p>'
            . '<h2>2. Sider</h2><p>Opret nye WordPress-sider med titel, valgfri slug, parent og status. Åbn siden direkte i Visual Designer, sæt den som Hjem og kontroller VDM-version og elementantal.</p>'
            . '<h2>3. Visual Designer</h2><p>Arbejd i Desktop, Laptop, Tablet og Mobil. Elementer flyttes og skaleres på den kanoniske gridmodel. Fortryd/Gentag og versionshistorik beskytter arbejdet. Gem opretter altid en ny version.</p>'
            . '<h3>Elementer</h3><table><thead><tr><th>Element</th><th>Formål</th></tr></thead><tbody>'
            . '<tr><td>Sektion</td><td>Overordnet layoutområde.</td></tr><tr><td>Kasse</td><td>Nestable indholdscontainer.</td></tr><tr><td>Tekst</td><td>Rich text og typografi.</td></tr><tr><td>Billede</td><td>WordPress Media Library-billede.</td></tr><tr><td>Knap</td><td>Handlingslink.</td></tr><tr><td>Mellemrum / Skillelinje</td><td>Layout og visuel separation.</td></tr><tr><td>Navigation</td><td>Viser en WordPress-menu.</td></tr><tr><td>Events / Køretøjer / Billedgalleri</td><td>Dynamiske VDM-moduler.</td></tr><tr><td>Kontakt / Bliv medlem</td><td>Servervaliderede formularer.</td></tr></tbody></table>'
            . '<h2>4. Header / Footer</h2><p>Opret flere navngivne Header- og Footer-templates. Hver template har stabilt ID, egen versionshistorik, Aktiv-status og kan vælges som website-standard. En side kan bruge Automatisk/standard, en konkret template eller Ingen Header/Footer.</p>'
            . '<h2>5. Menu</h2><p>Opret og redigér WordPress-menuer direkte i VDM. Tilføj sider, eksterne links og overskrifter, justér rækkefølge og parent-hierarki, tildel theme locations og gendan tidligere snapshots.</p>'
            . '<h2>6. Events, Køretøjer og Billedgalleri</h2><p>Opret og vedligehold indhold i de tre moduler. Feltdefinitioner for Events og Køretøjer har stabile ID’er, så data ikke flytter sig ved omdøbning.</p>'
            . '<h2>7. Tema / Site Design</h2><p>VDM-site-shell kan aktiveres uafhængigt af temaets visuelle layout. Her styres global sidebredde, padding, farver og grundtypografi.</p>'
            . '<h2>8. Siteindstillinger</h2><p>Webstedstitel, slogan, forening, kontaktoplysninger, VDM-logo og site-ikon vedligeholdes ét sted.</p>'
            . '<h2>9. Backup og eksport/import</h2><p>Portable VDM-pakker valideres med manifest, filstørrelser og SHA-256. Import kører altid preflight før sitet ændres.</p>'
            . '<h2>10. Opdateringer</h2><p>Før en direkte pluginopdatering opretter VDM både program-ZIP og komplet data-checkpoint. Downloadpakken SHA-256-verificeres før installation.</p>'
            . '<h2>11. Log og fejlsøgning</h2><p>Loggen viser administrative handlinger, migration og supportdiagnostik. Brug den ved fejl og før en rollback.</p>'
            . '<h2>12. Sikker arbejdsrutine</h2><ol><li>Tag backup før større import/migration.</li><li>Brug Forhåndsvis før publicering.</li><li>Gem som ny version i stedet for at overskrive historik.</li><li>Kontrollér Desktop, Laptop, Tablet og Mobil.</li><li>Ved V1→V2 migration sammenlignes test4 visuelt mod test3 før produktion.</li></ol>'
            . '</article>';
    }

    private static function documentXml(): string
    {
        $plain = wp_strip_all_tags(str_replace(['</h1>', '</h2>', '</h3>', '</p>', '</li>', '</tr>'], "\n", self::manualHtml()));
        $lines = preg_split('/\R+/', html_entity_decode($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?: [];
        $body = '';
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $body .= '<w:p><w:r><w:t xml:space="preserve">' . htmlspecialchars($line, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</w:t></w:r></w:p>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>' . $body . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440"/></w:sectPr></w:body></w:document>';
    }
}
