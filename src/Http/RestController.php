<?php

declare(strict_types=1);

namespace VisualDesignerManager\Http;

use VisualDesignerManager\Frontend\Renderer;
use VisualDesignerManager\Model\LayoutDocument;
use VisualDesignerManager\Storage\LayoutRepository;
use VisualDesignerManager\Storage\TemplateRepository;

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

        register_rest_route(self::NAMESPACE, '/layouts/(?P<id>[a-z0-9-]+)', [
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
            'permission_callback' => static fn(): bool => current_user_can('edit_pages') || current_user_can('edit_theme_options'),
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
        $id = sanitize_key((string) $request['id']);
        if (self::templateTarget($id) !== null) {
            return current_user_can('edit_theme_options');
        }

        $postId = absint($id);
        return $postId > 0 && get_post_type($postId) === 'page' && current_user_can('edit_post', $postId);
    }

    public static function getLayout(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = sanitize_key((string) $request['id']);
        $target = self::templateTarget($id);
        if ($target !== null) {
            [$slot, $templateId] = $target;
            return new \WP_REST_Response([
                'document' => TemplateRepository::getTemplate($slot, $templateId),
                'version' => TemplateRepository::versionTemplate($slot, $templateId),
                'templateId' => $templateId,
                'templateSlot' => $slot,
            ], 200);
        }

        $postId = absint($id);
        return new \WP_REST_Response([
            'document' => LayoutRepository::get($postId),
            'version' => LayoutRepository::version($postId),
        ], 200);
    }

    public static function saveLayout(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = sanitize_key((string) $request['id']);
        $params = $request->get_json_params();
        $document = is_array($params['document'] ?? null) ? $params['document'] : [];

        try {
            $target = self::templateTarget($id);
            if ($target !== null) {
                [$slot, $templateId] = $target;
                $saved = TemplateRepository::saveTemplate($slot, $templateId, $document, get_current_user_id());
                $saved['templateId'] = $templateId;
                $saved['templateSlot'] = $slot;
            } else {
                $saved = LayoutRepository::save(absint($id), $document, get_current_user_id());
            }
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

    /**
     * @return array{0:string,1:string}|null
     */
    private static function templateTarget(string $id): ?array
    {
        if ($id === 'global-header') {
            $templateId = TemplateRepository::defaultId(TemplateRepository::HEADER);
            return $templateId !== '' ? [TemplateRepository::HEADER, $templateId] : null;
        }
        if ($id === 'global-footer') {
            $templateId = TemplateRepository::defaultId(TemplateRepository::FOOTER);
            return $templateId !== '' ? [TemplateRepository::FOOTER, $templateId] : null;
        }

        foreach (TemplateRepository::slots() as $slot) {
            $prefix = 'template-' . $slot . '-';
            if (!str_starts_with($id, $prefix)) {
                continue;
            }
            $templateId = sanitize_key(substr($id, strlen($prefix)));
            if ($templateId !== '' && TemplateRepository::exists($slot, $templateId)) {
                return [$slot, $templateId];
            }
        }
        return null;
    }
}
