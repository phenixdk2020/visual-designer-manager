<?php

declare(strict_types=1);

namespace VisualDesignerManager\Gallery;

final class GalleryRepository
{
    public const POST_TYPE = 'vdm_gallery';
    public const META_IMAGES = '_vdm_gallery_images';
    public const META_SUMMARY = '_vdm_gallery_summary';

    /** @return array<string,mixed> */
    public static function get(int $postId): array
    {
        $rawIds = get_post_meta($postId, self::META_IMAGES, true);
        $ids = self::normalizeIds(is_array($rawIds) ? $rawIds : []);
        $images = [];
        foreach ($ids as $id) {
            $url = wp_get_attachment_image_url($id, 'large');
            $thumb = wp_get_attachment_image_url($id, 'medium');
            if (!is_string($url) || $url === '') {
                continue;
            }
            $images[] = [
                'id' => $id,
                'url' => $url,
                'thumb' => is_string($thumb) && $thumb !== '' ? $thumb : $url,
                'alt' => (string) get_post_meta($id, '_wp_attachment_image_alt', true),
                'caption' => (string) wp_get_attachment_caption($id),
            ];
        }

        $cover = get_the_post_thumbnail_url($postId, 'large') ?: '';
        if ($cover === '' && $images !== []) {
            $cover = (string) $images[0]['url'];
        }

        return [
            'id' => $postId,
            'title' => get_the_title($postId),
            'permalink' => get_permalink($postId) ?: '',
            'summary' => (string) get_post_meta($postId, self::META_SUMMARY, true),
            'content' => (string) get_post_field('post_content', $postId),
            'cover' => $cover,
            'imageIds' => $ids,
            'images' => $images,
        ];
    }

    /** @param array<string,mixed> $data */
    public static function save(int $postId, array $data): void
    {
        $summary = sanitize_textarea_field((string) ($data['summary'] ?? ''));
        if ($summary === '') {
            delete_post_meta($postId, self::META_SUMMARY);
        } else {
            update_post_meta($postId, self::META_SUMMARY, $summary);
        }

        $ids = self::normalizeIds(is_array($data['imageIds'] ?? null) ? $data['imageIds'] : []);
        if ($ids === []) {
            delete_post_meta($postId, self::META_IMAGES);
        } else {
            update_post_meta($postId, self::META_IMAGES, $ids);
        }
    }

    /** @return list<array<string,mixed>> */
    public static function query(int $limit = 24): array
    {
        $query = new \WP_Query([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => max(1, min(100, $limit)),
            'orderby' => ['menu_order' => 'ASC', 'title' => 'ASC'],
            'order' => 'ASC',
            'no_found_rows' => true,
        ]);

        $albums = [];
        foreach ($query->posts as $post) {
            $albums[] = self::get((int) $post->ID);
        }
        wp_reset_postdata();
        return $albums;
    }

    /** @param array<mixed> $ids
     *  @return list<int>
     */
    public static function normalizeIds(array $ids): array
    {
        $result = [];
        foreach ($ids as $id) {
            $value = absint($id);
            if ($value <= 0 || get_post_type($value) !== 'attachment' || !wp_attachment_is_image($value)) {
                continue;
            }
            if (!in_array($value, $result, true)) {
                $result[] = $value;
            }
            if (count($result) >= 500) {
                break;
            }
        }
        return $result;
    }

    private function __construct()
    {
    }
}
