<?php

declare(strict_types=1);

namespace VisualDesignerManager\Http;

use VisualDesignerManager\Diagnostics\DiagnosticStore;
use VisualDesignerManager\Storage\LayoutRepository;
use VisualDesignerManager\Storage\PreviewRepository;

final class PageWorkflowRestController
{
    private function __construct()
    {
    }

    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'routes']);
    }

    public static function routes(): void
    {
        register_rest_route(RestController::NAMESPACE, '/pages/(?P<id>\d+)/preview', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'preview'],
            'permission_callback' => [self::class, 'canEditPage'],
        ]);

        register_rest_route(RestController::NAMESPACE, '/pages/(?P<id>\d+)/history', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [self::class, 'history'],
            'permission_callback' => [self::class, 'canEditPage'],
        ]);

        register_rest_route(RestController::NAMESPACE, '/pages/(?P<id>\d+)/versions/(?P<version>\d+)/preview', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'previewVersion'],
            'permission_callback' => [self::class, 'canEditPage'],
        ]);

        register_rest_route(RestController::NAMESPACE, '/pages/(?P<id>\d+)/versions/(?P<version>\d+)/restore', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'restoreVersion'],
            'permission_callback' => [self::class, 'canEditPage'],
        ]);

        register_rest_route(RestController::NAMESPACE, '/pages/(?P<id>\d+)/versions/(?P<version>\d+)/copy', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'copyVersion'],
            'permission_callback' => [self::class, 'canEditPage'],
        ]);
    }

    public static function canEditPage(\WP_REST_Request $request): bool
    {
        $postId = absint($request['id']);
        return $postId > 0 && get_post_type($postId) === 'page' && current_user_can('edit_post', $postId);
    }

    public static function preview(\WP_REST_Request $request): \WP_REST_Response
    {
        $postId = absint($request['id']);
        $params = $request->get_json_params();
        $document = is_array($params['document'] ?? null) ? $params['document'] : [];

        try {
            $token = PreviewRepository::stage($postId, $document, get_current_user_id());
            return new \WP_REST_Response([
                'url' => self::previewUrl($postId, $token),
                'expiresIn' => 1200,
            ], 200);
        } catch (\Throwable $error) {
            return self::error('vdm_preview_failed', $error->getMessage());
        }
    }

    public static function history(\WP_REST_Request $request): \WP_REST_Response
    {
        $postId = absint($request['id']);
        $items = [];
        foreach (LayoutRepository::history($postId) as $entry) {
            $version = max(0, (int) ($entry['version'] ?? 0));
            if ($version <= 0 || !is_array($entry['document'] ?? null)) {
                continue;
            }
            $savedBy = max(0, (int) ($entry['savedBy'] ?? 0));
            $user = $savedBy > 0 ? get_userdata($savedBy) : false;
            $items[] = [
                'version' => $version,
                'savedAt' => sanitize_text_field((string) ($entry['savedAt'] ?? '')),
                'savedBy' => $savedBy,
                'savedByName' => $user instanceof \WP_User ? (string) $user->display_name : '',
                'nodeCount' => count((array) (($entry['document']['nodes'] ?? []))),
            ];
        }

        return new \WP_REST_Response([
            'currentVersion' => LayoutRepository::version($postId),
            'history' => $items,
        ], 200);
    }

    public static function previewVersion(\WP_REST_Request $request): \WP_REST_Response
    {
        $postId = absint($request['id']);
        $version = absint($request['version']);
        $entry = self::versionEntry($postId, $version);
        if ($entry === null) {
            return self::error('vdm_version_not_found', 'Den valgte version findes ikke.', 404);
        }

        try {
            $token = PreviewRepository::stage($postId, $entry['document'], get_current_user_id());
            return new \WP_REST_Response([
                'url' => self::previewUrl($postId, $token),
                'version' => $version,
                'expiresIn' => 1200,
            ], 200);
        } catch (\Throwable $error) {
            return self::error('vdm_version_preview_failed', $error->getMessage());
        }
    }

    public static function restoreVersion(\WP_REST_Request $request): \WP_REST_Response
    {
        $postId = absint($request['id']);
        $version = absint($request['version']);
        $entry = self::versionEntry($postId, $version);
        if ($entry === null) {
            return self::error('vdm_version_not_found', 'Den valgte version findes ikke.', 404);
        }

        try {
            $saved = LayoutRepository::save($postId, $entry['document'], get_current_user_id());
            DiagnosticStore::add('info', 'En tidligere Visual Designer-version blev gendannet som ny version.', [
                'postId' => $postId,
                'sourceVersion' => $version,
                'newVersion' => (int) ($saved['version'] ?? 0),
            ]);
            return new \WP_REST_Response([
                'restoredFrom' => $version,
                'document' => $saved['document'],
                'version' => $saved['version'],
            ], 200);
        } catch (\Throwable $error) {
            return self::error('vdm_version_restore_failed', $error->getMessage());
        }
    }

    public static function copyVersion(\WP_REST_Request $request): \WP_REST_Response
    {
        $postId = absint($request['id']);
        $version = absint($request['version']);
        $entry = self::versionEntry($postId, $version);
        if ($entry === null) {
            return self::error('vdm_version_not_found', 'Den valgte version findes ikke.', 404);
        }

        $source = get_post($postId);
        if (!$source instanceof \WP_Post) {
            return self::error('vdm_page_not_found', 'Kildesiden findes ikke.', 404);
        }

        $title = trim((string) $source->post_title) . ' – kopi v' . $version;
        $newId = wp_insert_post([
            'post_type' => 'page',
            'post_status' => 'draft',
            'post_title' => $title,
            'post_parent' => (int) $source->post_parent,
        ], true);

        if (is_wp_error($newId)) {
            return self::error('vdm_version_copy_failed', $newId->get_error_message());
        }

        try {
            $saved = LayoutRepository::save((int) $newId, $entry['document'], get_current_user_id());
        } catch (\Throwable $error) {
            wp_delete_post((int) $newId, true);
            return self::error('vdm_version_copy_failed', $error->getMessage());
        }

        DiagnosticStore::add('info', 'En Visual Designer-version blev kopieret til en ny kladdeside.', [
            'sourcePostId' => $postId,
            'sourceVersion' => $version,
            'newPostId' => (int) $newId,
        ]);

        return new \WP_REST_Response([
            'pageId' => (int) $newId,
            'version' => (int) ($saved['version'] ?? 1),
            'designerUrl' => add_query_arg([
                'page' => 'vdm-designer',
                'post_id' => (int) $newId,
            ], admin_url('admin.php')),
            'viewUrl' => get_permalink((int) $newId) ?: '',
        ], 201);
    }

    /** @return array{version:int,savedAt?:string,savedBy?:int,document:array<string,mixed>}|null */
    private static function versionEntry(int $postId, int $version): ?array
    {
        if ($version <= 0) {
            return null;
        }
        foreach (LayoutRepository::history($postId) as $entry) {
            if ((int) ($entry['version'] ?? 0) !== $version || !is_array($entry['document'] ?? null)) {
                continue;
            }
            return $entry;
        }
        return null;
    }

    private static function previewUrl(int $postId, string $token): string
    {
        $url = get_permalink($postId);
        if (!is_string($url) || $url === '') {
            $url = home_url('/?page_id=' . $postId);
        }
        return add_query_arg('vdm_preview', rawurlencode($token), $url);
    }

    private static function error(string $code, string $message, int $status = 400): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'code' => $code,
            'message' => $message,
        ], $status);
    }
}
