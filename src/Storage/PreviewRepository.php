<?php

declare(strict_types=1);

namespace VisualDesignerManager\Storage;

use VisualDesignerManager\Model\LayoutDocument;

final class PreviewRepository
{
    private const PREFIX = 'vdm_preview_';
    private const TTL = 1200;

    private function __construct()
    {
    }

    /** @param array<string,mixed> $document */
    public static function stage(int $postId, array $document, int $userId): string
    {
        if ($postId <= 0 || get_post_type($postId) !== 'page') {
            throw new \InvalidArgumentException('Forhåndsvisning kræver en gyldig side.');
        }
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Forhåndsvisning kræver en logget ind bruger.');
        }

        $normalized = LayoutDocument::normalize($document);
        try {
            $token = bin2hex(random_bytes(24));
        } catch (\Throwable) {
            $token = strtolower(wp_generate_password(48, false, false));
        }

        $payload = [
            'postId' => $postId,
            'userId' => $userId,
            'createdAt' => gmdate('c'),
            'document' => $normalized,
        ];

        if (!set_transient(self::key($token), $payload, self::TTL)) {
            throw new \RuntimeException('Forhåndsvisningen kunne ikke gemmes midlertidigt.');
        }

        return $token;
    }

    /** @return array<string,mixed>|null */
    public static function resolve(int $postId): ?array
    {
        if ($postId <= 0 || !is_user_logged_in()) {
            return null;
        }

        $raw = isset($_GET['vdm_preview']) ? wp_unslash($_GET['vdm_preview']) : '';
        $token = is_string($raw) ? strtolower(sanitize_text_field($raw)) : '';
        if (!preg_match('/^[a-z0-9]{32,64}$/', $token)) {
            return null;
        }

        $payload = get_transient(self::key($token));
        if (!is_array($payload)) {
            return null;
        }

        if ((int) ($payload['postId'] ?? 0) !== $postId || (int) ($payload['userId'] ?? 0) !== get_current_user_id()) {
            return null;
        }

        $document = $payload['document'] ?? null;
        if (!is_array($document)) {
            return null;
        }

        try {
            $normalized = LayoutDocument::normalize($document);
        } catch (\Throwable) {
            return null;
        }

        if (function_exists('nocache_headers')) {
            nocache_headers();
        }

        return $normalized;
    }

    private static function key(string $token): string
    {
        return self::PREFIX . hash('sha256', $token);
    }
}
