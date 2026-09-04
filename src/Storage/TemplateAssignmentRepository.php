<?php

declare(strict_types=1);

namespace VisualDesignerManager\Storage;

use VisualDesignerManager\Model\LayoutDocument;

/**
 * Per-page Header/Footer assignment resolver.
 *
 * Stored values are `auto`, `none`, or a stable named template ID. Resolution
 * order is explicit page assignment -> active site default -> empty fallback.
 */
final class TemplateAssignmentRepository
{
    private const META_PREFIX = '_vdm_template_assignment_';

    public static function getChoice(int $postId, string $slot): string
    {
        self::assertPage($postId);
        self::assertSlot($slot);
        $value = sanitize_key((string) get_post_meta($postId, self::metaKey($slot), true));
        if ($value === '' || $value === 'auto') {
            return 'auto';
        }
        if ($value === 'none') {
            return 'none';
        }
        return TemplateRepository::exists($slot, $value) ? $value : 'auto';
    }

    public static function saveChoice(int $postId, string $slot, string $choice): void
    {
        self::assertPage($postId);
        self::assertSlot($slot);
        $choice = sanitize_key($choice);
        if ($choice === '' || $choice === 'auto') {
            delete_post_meta($postId, self::metaKey($slot));
            return;
        }
        if ($choice !== 'none' && !TemplateRepository::exists($slot, $choice)) {
            throw new \InvalidArgumentException('Den valgte Header/Footer-template findes ikke.');
        }
        update_post_meta($postId, self::metaKey($slot), $choice);
    }

    public static function resolveId(int $postId, string $slot): ?string
    {
        $choice = self::getChoice($postId, $slot);
        if ($choice === 'none') {
            return null;
        }
        if ($choice !== 'auto') {
            $meta = TemplateRepository::meta($slot, $choice);
            if ($meta !== null && !empty($meta['active'])) {
                return $choice;
            }
        }

        $default = TemplateRepository::defaultId($slot);
        if ($default === '') {
            return null;
        }
        $meta = TemplateRepository::meta($slot, $default);
        return $meta !== null && !empty($meta['active']) ? $default : null;
    }

    /** @return array<string,mixed> */
    public static function resolveDocument(int $postId, string $slot): array
    {
        $id = self::resolveId($postId, $slot);
        return $id !== null ? TemplateRepository::getTemplate($slot, $id) : LayoutDocument::empty();
    }

    /** @return array<string,mixed> */
    public static function resolveSettings(int $postId, string $slot): array
    {
        $id = self::resolveId($postId, $slot);
        return $id !== null ? TemplateRepository::settings($slot, $id) : [];
    }

    public static function usageCount(string $slot, string $templateId): int
    {
        self::assertSlot($slot);
        $templateId = sanitize_key($templateId);
        if ($templateId === '') {
            return 0;
        }
        global $wpdb;
        if (!$wpdb instanceof \wpdb) {
            return 0;
        }
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s",
            self::metaKey($slot),
            $templateId
        ));
        return max(0, (int) $count);
    }

    /** @return array<int,array{postId:int,title:string,choice:string}> */
    public static function explicitUsages(string $slot, string $templateId): array
    {
        self::assertSlot($slot);
        $templateId = sanitize_key($templateId);
        if ($templateId === '') {
            return [];
        }
        $query = new \WP_Query([
            'post_type' => 'page',
            'post_status' => ['publish', 'draft', 'private'],
            'posts_per_page' => 200,
            'meta_key' => self::metaKey($slot),
            'meta_value' => $templateId,
            'orderby' => 'title',
            'order' => 'ASC',
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);
        $rows = [];
        foreach ((array) $query->posts as $postId) {
            $postId = absint($postId);
            if ($postId <= 0) {
                continue;
            }
            $rows[] = [
                'postId' => $postId,
                'title' => (string) get_the_title($postId),
                'choice' => $templateId,
            ];
        }
        return $rows;
    }

    private static function metaKey(string $slot): string
    {
        return self::META_PREFIX . $slot . '_v3';
    }

    private static function assertPage(int $postId): void
    {
        if ($postId <= 0 || get_post_type($postId) !== 'page') {
            throw new \InvalidArgumentException('Ugyldig WordPress-side.');
        }
    }

    private static function assertSlot(string $slot): void
    {
        if (!in_array($slot, TemplateRepository::slots(), true)) {
            throw new \InvalidArgumentException('Ugyldig Header/Footer-slot.');
        }
    }

    private function __construct()
    {
    }
}
