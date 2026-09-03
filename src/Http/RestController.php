<?php

declare(strict_types=1);

namespace VisualDesignerManager\Http;

final class RestController
{
    public const NAMESPACE = 'visual-designer-manager/v2';

    private function __construct()
    {
    }

    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'routes']);
    }

    public static function routes(): void
    {
        register_rest_route(self::NAMESPACE, '/health', [
            'methods' => 'GET',
            'callback' => [self::class, 'health'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function health(): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'product' => 'Visual Designer Manager',
            'version' => VDM_VERSION,
            'schemaVersion' => 2,
            'status' => 'ok',
        ], 200);
    }
}
