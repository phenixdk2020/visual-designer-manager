<?php

declare(strict_types=1);

namespace VisualDesignerManager\Vehicles;

final class VehicleRepository
{
    public const POST_TYPE = 'vdm_vehicle';
    public const META_TYPE = '_vdm_vehicle_type';
    public const META_MANUFACTURER = '_vdm_vehicle_manufacturer';
    public const META_MODEL = '_vdm_vehicle_model';
    public const META_YEAR = '_vdm_vehicle_year';
    public const META_COUNTRY = '_vdm_vehicle_country';
    public const META_STATUS = '_vdm_vehicle_status';
    public const META_ENGINE = '_vdm_vehicle_engine';
    public const META_POWER = '_vdm_vehicle_power';
    public const META_WEIGHT = '_vdm_vehicle_weight';
    public const META_LENGTH = '_vdm_vehicle_length';
    public const META_WIDTH = '_vdm_vehicle_width';
    public const META_HEIGHT = '_vdm_vehicle_height';
    public const META_CREW = '_vdm_vehicle_crew';
    public const META_SUMMARY = '_vdm_vehicle_summary';
    public const META_SPECS = '_vdm_vehicle_specs';

    /** @return array<string,mixed> */
    public static function get(int $postId): array
    {
        $specs = get_post_meta($postId, self::META_SPECS, true);
        if (!is_array($specs)) {
            $specs = [];
        }

        return [
            'id' => $postId,
            'title' => get_the_title($postId),
            'permalink' => get_permalink($postId) ?: '',
            'type' => (string) get_post_meta($postId, self::META_TYPE, true),
            'manufacturer' => (string) get_post_meta($postId, self::META_MANUFACTURER, true),
            'model' => (string) get_post_meta($postId, self::META_MODEL, true),
            'year' => (string) get_post_meta($postId, self::META_YEAR, true),
            'country' => (string) get_post_meta($postId, self::META_COUNTRY, true),
            'status' => (string) get_post_meta($postId, self::META_STATUS, true),
            'engine' => (string) get_post_meta($postId, self::META_ENGINE, true),
            'power' => (string) get_post_meta($postId, self::META_POWER, true),
            'weight' => (string) get_post_meta($postId, self::META_WEIGHT, true),
            'length' => (string) get_post_meta($postId, self::META_LENGTH, true),
            'width' => (string) get_post_meta($postId, self::META_WIDTH, true),
            'height' => (string) get_post_meta($postId, self::META_HEIGHT, true),
            'crew' => (string) get_post_meta($postId, self::META_CREW, true),
            'summary' => (string) get_post_meta($postId, self::META_SUMMARY, true),
            'specs' => self::normalizeSpecs($specs),
            'content' => (string) get_post_field('post_content', $postId),
            'image' => get_the_post_thumbnail_url($postId, 'large') ?: '',
        ];
    }

    /** @param array<string,mixed> $data */
    public static function save(int $postId, array $data): void
    {
        $fields = [
            self::META_TYPE => 'type',
            self::META_MANUFACTURER => 'manufacturer',
            self::META_MODEL => 'model',
            self::META_YEAR => 'year',
            self::META_COUNTRY => 'country',
            self::META_STATUS => 'status',
            self::META_ENGINE => 'engine',
            self::META_POWER => 'power',
            self::META_WEIGHT => 'weight',
            self::META_LENGTH => 'length',
            self::META_WIDTH => 'width',
            self::META_HEIGHT => 'height',
            self::META_CREW => 'crew',
        ];

        foreach ($fields as $metaKey => $inputKey) {
            self::update($postId, $metaKey, sanitize_text_field((string) ($data[$inputKey] ?? '')));
        }

        self::update($postId, self::META_SUMMARY, sanitize_textarea_field((string) ($data['summary'] ?? '')));

        $specs = self::normalizeSpecs(is_array($data['specs'] ?? null) ? $data['specs'] : []);
        if ($specs === []) {
            delete_post_meta($postId, self::META_SPECS);
        } else {
            update_post_meta($postId, self::META_SPECS, $specs);
        }
    }

    /** @return list<array<string,mixed>> */
    public static function query(int $limit = 12): array
    {
        $query = new \WP_Query([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => max(1, min(100, $limit)),
            'orderby' => 'title',
            'order' => 'ASC',
            'no_found_rows' => true,
        ]);

        $vehicles = [];
        foreach ($query->posts as $post) {
            $vehicles[] = self::get((int) $post->ID);
        }
        wp_reset_postdata();
        return $vehicles;
    }

    /** @param array<mixed> $specs
     *  @return list<array{label:string,value:string}>
     */
    public static function normalizeSpecs(array $specs): array
    {
        $normalized = [];
        foreach ($specs as $spec) {
            if (!is_array($spec)) {
                continue;
            }
            $label = sanitize_text_field((string) ($spec['label'] ?? ''));
            $value = sanitize_text_field((string) ($spec['value'] ?? ''));
            if ($label === '' && $value === '') {
                continue;
            }
            $normalized[] = ['label' => $label, 'value' => $value];
            if (count($normalized) >= 50) {
                break;
            }
        }
        return $normalized;
    }

    private static function update(int $postId, string $key, string $value): void
    {
        if ($value === '') {
            delete_post_meta($postId, $key);
            return;
        }
        update_post_meta($postId, $key, $value);
    }

    private function __construct()
    {
    }
}
