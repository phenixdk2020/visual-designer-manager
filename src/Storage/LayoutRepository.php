<?php

declare(strict_types=1);

namespace VisualDesignerManager\Storage;

use VisualDesignerManager\Model\LayoutDocument;

final class LayoutRepository
{
    public const DOCUMENT_META = '_vdm_layout_v2';
    public const VERSION_META = '_vdm_layout_version_v2';
    public const HISTORY_META = '_vdm_layout_history_v2';

    /** @return array<string,mixed> */
    public static function get(int $postId): array
    {
        $value = get_post_meta($postId, self::DOCUMENT_META, true);
        if (!is_array($value)) {
            return LayoutDocument::empty();
        }

        try {
            return LayoutDocument::normalize($value);
        } catch (\Throwable) {
            return LayoutDocument::empty();
        }
    }

    public static function version(int $postId): int
    {
        return max(0, (int) get_post_meta($postId, self::VERSION_META, true));
    }

    /** @param array<string,mixed> $document
     *  @return array{document:array<string,mixed>,version:int}
     */
    public static function save(int $postId, array $document, int $userId): array
    {
        if (get_post_type($postId) !== 'page') {
            throw new \InvalidArgumentException('VDM layouts can only be stored on pages.');
        }

        $normalized = LayoutDocument::normalize($document);
        $current = self::get($postId);
        $currentVersion = self::version($postId);

        if (wp_json_encode($current) === wp_json_encode($normalized)) {
            return ['document' => $normalized, 'version' => $currentVersion];
        }

        $history = get_post_meta($postId, self::HISTORY_META, true);
        if (!is_array($history)) {
            $history = [];
        }

        if ($current['nodes'] !== []) {
            array_unshift($history, [
                'version' => $currentVersion,
                'savedAt' => gmdate('c'),
                'savedBy' => $userId,
                'document' => $current,
            ]);
            $history = array_slice($history, 0, 50);
            update_post_meta($postId, self::HISTORY_META, $history);
        }

        $nextVersion = $currentVersion + 1;
        update_post_meta($postId, self::DOCUMENT_META, $normalized);
        update_post_meta($postId, self::VERSION_META, $nextVersion);

        return ['document' => $normalized, 'version' => $nextVersion];
    }

    /** @return list<array<string,mixed>> */
    public static function history(int $postId): array
    {
        $value = get_post_meta($postId, self::HISTORY_META, true);
        return is_array($value) ? array_values($value) : [];
    }

    private function __construct()
    {
    }
}
