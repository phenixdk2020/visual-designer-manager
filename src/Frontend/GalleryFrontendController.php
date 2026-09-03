<?php

declare(strict_types=1);

namespace VisualDesignerManager\Frontend;

use VisualDesignerManager\Gallery\GalleryRepository;
use VisualDesignerManager\Storage\SiteDesignRepository;

final class GalleryFrontendController
{
    private function __construct()
    {
    }

    public static function register(): void
    {
        add_filter('template_include', [self::class, 'templateInclude'], 102);
        add_filter('the_content', [self::class, 'renderContent'], 23);
    }

    public static function templateInclude(string $template): string
    {
        if (is_admin() || !is_singular(GalleryRepository::POST_TYPE)) {
            return $template;
        }

        if (empty(SiteDesignRepository::get()['shellEnabled'])) {
            return $template;
        }

        $shell = VDM_DIR . 'templates/single-gallery.php';
        if (!is_file($shell)) {
            return $template;
        }

        FrontendController::enqueueAssets();
        return $shell;
    }

    public static function renderContent(string $content): string
    {
        if (is_admin() || !is_singular(GalleryRepository::POST_TYPE) || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        if (!empty(SiteDesignRepository::get()['shellEnabled'])) {
            return $content;
        }

        $postId = get_the_ID();
        if ($postId <= 0) {
            return $content;
        }

        FrontendController::enqueueAssets();
        return GalleryRenderer::renderDetail($postId);
    }
}
