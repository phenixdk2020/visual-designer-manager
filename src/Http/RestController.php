<?php

declare(strict_types=1);

namespace VisualDesignerManager\Http;

use VisualDesignerManager\Frontend\Renderer;
use VisualDesignerManager\Model\LayoutDocument;
use VisualDesignerManager\Storage\LayoutRepository;

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
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [self::class, 'health'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/layouts/(?P<id>\d+)', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [self::class, 'getLayout'],
                'permission_callback' => [self::class, 'canEditLayout'],
            ],
            [
                'methods' => \WP_REST_Server::EDITABLE,
                'callback' => [self::class, 'saveLayout'],
                'permission_callback' => [self::class, 'canEditLayout'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/render', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'renderLayout'],
            'permission_callback' => static fn(): bool => current_user_can('edit_pages'),
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

    public static function canEditLayout(\WP_REST_Request $request): bool
    {
        $postId = absint($request['id']);
        return $postId > 0 && get_post_type($postId) === 'page' && current_user_can('edit_post', $postId);
    }

    public static function getLayout(\WP_REST_Request $request): \WP_REST_Response
    {
        $postId = absint($request['id']);
        return new \WP_REST_Response([
            'document' => LayoutRepository::get($postId),
            'version' => LayoutRepository::version($postId),
        ], 200);
    }

    public static function saveLayout(\WP_REST_Request $request): \WP_REST_Response
    {
        $postId = absint($request['id']);
        $params = $request->get_json_params();
        $document = is_array($params['document'] ?? null) ? $params['document'] : [];

        try {
            $saved = LayoutRepository::save($postId, $document, get_current_user_id());
            return new \WP_REST_Response($saved, 200);
        } catch (\Throwable $error) {
            return new \WP_REST_Response([
                'code' => 'vdm_layout_invalid',
                'message' => $error->getMessage(),
            ], 400);
        }
    }

    public static function renderLayout(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();
        $document = is_array($params['document'] ?? null) ? $params['document'] : [];

        try {
            $normalized = LayoutDocument::normalize($document);
            return new \WP_REST_Response([
                'html' => Renderer::render($normalized),
                'document' => $normalized,
            ], 200);
        } catch (\Throwable $error) {
            return new \WP_REST_Response([
                'code' => 'vdm_render_invalid',
                'message' => $error->getMessage(),
            ], 400);
        }
    }
}
