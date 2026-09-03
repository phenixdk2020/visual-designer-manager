<?php

declare(strict_types=1);

namespace VisualDesignerManager\Frontend;

use VisualDesignerManager\Storage\LayoutRepository;
use VisualDesignerManager\Storage\SiteDesignRepository;

final class FrontendController
{
    private function __construct()
    {
    }

    public static function register(): void
    {
        add_action('wp_enqueue_scripts', [self::class, 'registerAssets']);
        add_filter('template_include', [self::class, 'templateInclude'], 99);
        add_filter('the_content', [self::class, 'renderPage'], 20);
    }

    public static function registerAssets(): void
    {
        wp_register_style(
            'vdm-frontend',
            VDM_URL . 'assets/frontend.css',
            [],
            VDM_VERSION
        );
        wp_register_script(
            'vdm-frontend-runtime',
            VDM_URL . 'assets/frontend.js',
            [],
            VDM_VERSION,
            true
        );
    }

    public static function enqueueAssets(): void
    {
        if (!wp_style_is('vdm-frontend', 'registered')) {
            wp_register_style('vdm-frontend', VDM_URL . 'assets/frontend.css', [], VDM_VERSION);
        }
        if (!wp_script_is('vdm-frontend-runtime', 'registered')) {
            wp_register_script('vdm-frontend-runtime', VDM_URL . 'assets/frontend.js', [], VDM_VERSION, true);
        }
        wp_enqueue_style('vdm-frontend');
        wp_enqueue_script('vdm-frontend-runtime');
    }

    public static function templateInclude(string $template): string
    {
        if (is_admin() || !is_singular('page')) {
            return $template;
        }

        $design = SiteDesignRepository::get();
        if (empty($design['shellEnabled'])) {
            return $template;
        }

        $postId = get_queried_object_id();
        if ($postId <= 0 || (LayoutRepository::get($postId)['nodes'] ?? []) === []) {
            return $template;
        }

        $shell = VDM_DIR . 'templates/page-shell.php';
        if (!is_file($shell)) {
            return $template;
        }

        self::enqueueAssets();
        return $shell;
    }

    public static function renderPage(string $content): string
    {
        if (is_admin() || !is_singular('page') || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        if (!empty(SiteDesignRepository::get()['shellEnabled'])) {
            return $content;
        }

        $postId = get_the_ID();
        if ($postId <= 0) {
            return $content;
        }

        $document = LayoutRepository::get($postId);
        if (($document['nodes'] ?? []) === []) {
            return $content;
        }

        self::enqueueAssets();
        return Renderer::render($document);
    }
}
