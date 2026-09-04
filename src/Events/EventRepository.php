<?php

declare(strict_types=1);

namespace VisualDesignerManager\Events;

use VisualDesignerManager\Fields\EventFieldRegistry;

final class EventRepository
{
    public const POST_TYPE = 'vdm_event';
    public const META_START_DATE = '_vdm_event_start_date';
    public const META_START_TIME = '_vdm_event_start_time';
    public const META_END_TIME = '_vdm_event_end_time';
    public const META_LOCATION = '_vdm_event_location';
    public const META_ADDRESS = '_vdm_event_address';
    public const META_CONTACT = '_vdm_event_contact';
    public const META_SUMMARY = '_vdm_event_summary';
    public const META_CUSTOM_FIELDS = '_vdm_event_custom_fields_v2';

    /** @return array<string,mixed> */
    public static function get(int $postId): array
    {
        $custom = get_post_meta($postId, self::META_CUSTOM_FIELDS, true);
        if (!is_array($custom)) {
            $custom = [];
        }
        return [
            'id' => $postId,
            'title' => get_the_title($postId),
            'permalink' => get_permalink($postId) ?: '',
            'startDate' => (string) get_post_meta($postId, self::META_START_DATE, true),
            'startTime' => (string) get_post_meta($postId, self::META_START_TIME, true),
            'endTime' => (string) get_post_meta($postId, self::META_END_TIME, true),
            'location' => (string) get_post_meta($postId, self::META_LOCATION, true),
            'address' => (string) get_post_meta($postId, self::META_ADDRESS, true),
            'contact' => (string) get_post_meta($postId, self::META_CONTACT, true),
            'summary' => (string) get_post_meta($postId, self::META_SUMMARY, true),
            'customFields' => self::normalizeCustomFields($custom),
            'content' => (string) get_post_field('post_content', $postId),
            'image' => get_the_post_thumbnail_url($postId, 'large') ?: '',
        ];
    }

    /** @param array<string,mixed> $data */
    public static function save(int $postId, array $data): void
    {
        self::update($postId, self::META_START_DATE, self::date((string) ($data['startDate'] ?? '')));
        self::update($postId, self::META_START_TIME, self::time((string) ($data['startTime'] ?? '')));
        self::update($postId, self::META_END_TIME, self::time((string) ($data['endTime'] ?? '')));
        self::update($postId, self::META_LOCATION, sanitize_text_field((string) ($data['location'] ?? '')));
        self::update($postId, self::META_ADDRESS, sanitize_text_field((string) ($data['address'] ?? '')));
        self::update($postId, self::META_CONTACT, sanitize_text_field((string) ($data['contact'] ?? '')));
        self::update($postId, self::META_SUMMARY, sanitize_textarea_field((string) ($data['summary'] ?? '')));

        $custom = self::normalizeCustomFields(is_array($data['customFields'] ?? null) ? $data['customFields'] : []);
        if ($custom === []) {
            delete_post_meta($postId, self::META_CUSTOM_FIELDS);
        } else {
            update_post_meta($postId, self::META_CUSTOM_FIELDS, $custom);
        }
    }

    /** @return list<array<string,mixed>> */
    public static function query(int $limit = 6, bool $showPast = false): array
    {
        $args = [
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => max(1, min(50, $limit)),
            'meta_key' => self::META_START_DATE,
            'orderby' => ['meta_value' => 'ASC', 'title' => 'ASC'],
            'order' => 'ASC',
            'no_found_rows' => true,
        ];

        if (!$showPast) {
            $args['meta_query'] = [[
                'key' => self::META_START_DATE,
                'value' => wp_date('Y-m-d'),
                'compare' => '>=',
                'type' => 'DATE',
            ]];
        }

        $query = new \WP_Query($args);
        $events = [];
        foreach ($query->posts as $post) {
            $events[] = self::get((int) $post->ID);
        }
        wp_reset_postdata();
        return $events;
    }

    /** @param array<string,mixed> $values @return array<string,string> */
    private static function normalizeCustomFields(array $values): array
    {
        $definitions = EventFieldRegistry::byId();
        $out = [];
        foreach ($values as $key => $value) {
            $id = sanitize_key((string) $key);
            if ($id === '' || !isset($definitions[$id]) || empty($definitions[$id]['enabled'])) {
                continue;
            }
            $type = (string) ($definitions[$id]['type'] ?? 'text');
            $text = is_scalar($value) ? (string) $value : '';
            if ($type === 'richtext') {
                $text = wp_kses_post($text);
            } elseif (in_array($type, ['textarea'], true)) {
                $text = sanitize_textarea_field($text);
            } elseif ($type === 'url') {
                $text = esc_url_raw($text);
            } elseif ($type === 'boolean') {
                $text = !empty($value) ? '1' : '';
            } else {
                $text = sanitize_text_field($text);
            }
            if ($text !== '') {
                $out[$id] = $text;
            }
        }
        return $out;
    }

    private static function update(int $postId, string $key, string $value): void
    {
        if ($value === '') {
            delete_post_meta($postId, $key);
            return;
        }
        update_post_meta($postId, $key, $value);
    }

    private static function date(string $value): string
    {
        $value = trim($value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : '';
    }

    private static function time(string $value): string
    {
        $value = trim($value);
        return preg_match('/^\d{2}:\d{2}$/', $value) === 1 ? $value : '';
    }

    private function __construct()
    {
    }
}
