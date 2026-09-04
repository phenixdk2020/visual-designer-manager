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

        // Header/Footer table actions are compact links, while their protected
        // handler consumes POST. Convert clicks to nonce-preserving POST forms.
        if ($page === TemplateDesignerController::MENU_SLUG) {
            $js = <<<'JS'
(() => {
    document.querySelectorAll('a[href*="action=vdm_template_action"]').forEach(link => {
        link.addEventListener('click', event => {
            event.preventDefault();
            const url = new URL(link.href, window.location.href);
            const form = document.createElement('form');
            form.method = 'post';
            form.action = url.pathname;
            ['action','operation','slot','template_id','_wpnonce'].forEach(key => {
                const value = url.searchParams.get(key);
                if (value === null) return;
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = value;
                form.append(input);
            });
            document.body.append(form);
            form.submit();
        });
        link.removeAttribute('onclick');
    });
})();
JS;
            wp_add_inline_script('vdm-designer-parity', $js, 'after');
        }
    }
}
