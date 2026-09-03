<?php

declare(strict_types=1);

namespace VisualDesignerManager\Frontend;

use VisualDesignerManager\Storage\LayoutRepository;

final class FrontendController
{
    private function __construct()
    {
    }

    public static function register(): void
    {
        add_action('wp_enqueue_scripts', [self::class, 'registerAssets']);
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
    }

    public static function renderPage(string $content): string
    {
        if (is_admin() || !is_singular('page') || !in_the_loop() || !is_main_query()) {
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

        wp_enqueue_style('vdm-frontend');
        return Renderer::render($document);
    }
}
