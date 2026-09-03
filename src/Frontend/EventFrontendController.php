<?php

declare(strict_types=1);

namespace VisualDesignerManager\Frontend;

use VisualDesignerManager\Events\EventRepository;
use VisualDesignerManager\Storage\SiteDesignRepository;

final class EventFrontendController
{
    private function __construct()
    {
    }

    public static function register(): void
    {
        add_filter('template_include', [self::class, 'templateInclude'], 100);
        add_filter('the_content', [self::class, 'renderContent'], 21);
    }

    public static function templateInclude(string $template): string
    {
        if (is_admin() || !is_singular(EventRepository::POST_TYPE)) {
            return $template;
        }

        if (empty(SiteDesignRepository::get()['shellEnabled'])) {
            return $template;
        }

        $shell = VDM_DIR . 'templates/single-event.php';
        if (!is_file($shell)) {
            return $template;
        }

        FrontendController::enqueueAssets();
        return $shell;
    }

    public static function renderContent(string $content): string
    {
        if (is_admin() || !is_singular(EventRepository::POST_TYPE) || !in_the_loop() || !is_main_query()) {
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
        return EventRenderer::renderDetail($postId);
    }
}
