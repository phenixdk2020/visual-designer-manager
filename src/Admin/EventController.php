<?php

declare(strict_types=1);

namespace VisualDesignerManager\Admin;

use VisualDesignerManager\Events\EventRepository;
use VisualDesignerManager\Fields\EventFieldRegistry;

final class EventController
{
    private function __construct()
    {
    }

    public static function register(): void
    {
        add_action('init', [self::class, 'postType']);
        add_action('add_meta_boxes', [self::class, 'metaBoxes']);
        add_action('save_post_' . EventRepository::POST_TYPE, [self::class, 'save'], 10, 2);
        add_filter('manage_' . EventRepository::POST_TYPE . '_posts_columns', [self::class, 'columns']);
        add_action('manage_' . EventRepository::POST_TYPE . '_posts_custom_column', [self::class, 'columnValue'], 10, 2);
    }

    public static function postType(): void
    {
        register_post_type(EventRepository::POST_TYPE, [
            'labels' => [
                'name' => 'Events',
                'singular_name' => 'Event',
                'add_new' => 'Tilføj event',
                'add_new_item' => 'Tilføj event',
                'edit_item' => 'Rediger event',
                'new_item' => 'Nyt event',
                'view_item' => 'Vis event',
                'search_items' => 'Søg events',
                'not_found' => 'Ingen events fundet',
            ],
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => AdminController::MENU_SLUG,
            'show_in_rest' => true,
            'has_archive' => true,
            'rewrite' => ['slug' => 'events'],
            'supports' => ['title', 'editor', 'thumbnail'],
            'menu_icon' => 'dashicons-calendar-alt',
        ]);
    }

    public static function metaBoxes(): void
    {
        add_meta_box(
            'vdm-event-details',
            'Eventoplysninger',
            [self::class, 'renderMetaBox'],
            EventRepository::POST_TYPE,
            'normal',
            'high'
        );
    }

    public static function renderMetaBox(\WP_Post $post): void
    {
        $event = EventRepository::get((int) $post->ID);
        wp_nonce_field('vdm_save_event', 'vdm_event_nonce');

        echo '<table class="form-table" role="presentation"><tbody>';
        self::field('Dato', 'startDate', (string) $event['startDate'], 'date');
        self::field('Starttid', 'startTime', (string) $event['startTime'], 'time');
        self::field('Sluttid', 'endTime', (string) $event['endTime'], 'time');
        self::field('Sted', 'location', (string) $event['location'], 'text');
        self::field('Adresse', 'address', (string) $event['address'], 'text');
        self::field('Kontakt', 'contact', (string) $event['contact'], 'text');
        echo '<tr><th scope="row"><label for="vdm-event-summary">Kort beskrivelse</label></th><td>';
        echo '<textarea class="large-text" rows="4" id="vdm-event-summary" name="vdm_event[summary]">' . esc_textarea((string) $event['summary']) . '</textarea>';
        echo '<p class="description">Vises på eventkort. Selve eventbeskrivelsen skrives i WordPress-editoren ovenfor.</p></td></tr>';
        echo '</tbody></table>';

        $values = is_array($event['customFields'] ?? null) ? $event['customFields'] : [];
        $definitions = array_values(array_filter(EventFieldRegistry::all(), static fn(array $row): bool => !empty($row['enabled'])));
        if ($definitions !== []) {
            echo '<hr><h3>Eventfelter</h3><p class="description">Disse felter styres centralt under Visual Designer Manager → Eventfelter.</p><table class="form-table" role="presentation"><tbody>';
            foreach ($definitions as $definition) {
                self::customField($definition, (string) ($values[(string) $definition['id']] ?? ''));
            }
            echo '</tbody></table>';
        }
    }

    public static function save(int $postId, \WP_Post $post): void
    {
        if ($post->post_type !== EventRepository::POST_TYPE || wp_is_post_revision($postId)) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!isset($_POST['vdm_event_nonce']) || !wp_verify_nonce(sanitize_text_field((string) wp_unslash($_POST['vdm_event_nonce'])), 'vdm_save_event')) {
            return;
        }
        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        $raw = isset($_POST['vdm_event']) && is_array($_POST['vdm_event']) ? wp_unslash($_POST['vdm_event']) : [];
        EventRepository::save($postId, is_array($raw) ? $raw : []);
    }

    /** @param array<string,string> $columns
     *  @return array<string,string>
     */
    public static function columns(array $columns): array
    {
        $result = [];
        foreach ($columns as $key => $label) {
            $result[$key] = $label;
            if ($key === 'title') {
                $result['vdm_event_date'] = 'Dato';
                $result['vdm_event_location'] = 'Sted';
            }
        }
        return $result;
    }

    public static function columnValue(string $column, int $postId): void
    {
        $event = EventRepository::get($postId);
        if ($column === 'vdm_event_date') {
            echo esc_html((string) $event['startDate']);
        } elseif ($column === 'vdm_event_location') {
            echo esc_html((string) $event['location']);
        }
    }

    private static function field(string $label, string $name, string $value, string $type): void
    {
        echo '<tr><th scope="row"><label for="vdm-event-' . esc_attr($name) . '">' . esc_html($label) . '</label></th><td>';
        echo '<input class="regular-text" type="' . esc_attr($type) . '" id="vdm-event-' . esc_attr($name) . '" name="vdm_event[' . esc_attr($name) . ']" value="' . esc_attr($value) . '">';
        echo '</td></tr>';
    }

    /** @param array<string,mixed> $definition */
    private static function customField(array $definition, string $value): void
    {
        $id = sanitize_key((string) ($definition['id'] ?? ''));
        if ($id === '') {
            return;
        }
        $label = (string) ($definition['label'] ?? $id);
        $type = (string) ($definition['type'] ?? 'text');
        $required = !empty($definition['required']) ? ' required' : '';
        $name = 'vdm_event[customFields][' . $id . ']';
        echo '<tr><th scope="row"><label for="vdm-event-custom-' . esc_attr($id) . '">' . esc_html($label) . '</label></th><td>';
        if (in_array($type, ['textarea', 'richtext'], true)) {
            echo '<textarea class="large-text" rows="5" id="vdm-event-custom-' . esc_attr($id) . '" name="' . esc_attr($name) . '"' . $required . '>' . esc_textarea($value) . '</textarea>';
        } elseif ($type === 'boolean') {
            echo '<label><input type="checkbox" id="vdm-event-custom-' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="1"' . checked($value, '1', false) . '> Ja</label>';
        } else {
            $inputType = match ($type) {
                'number', 'integer' => 'number',
                'date' => 'date',
                'datetime' => 'datetime-local',
                'url' => 'url',
                default => 'text',
            };
            $step = $type === 'number' ? ' step="any"' : '';
            echo '<input class="regular-text" type="' . esc_attr($inputType) . '"' . $step . ' id="vdm-event-custom-' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '"' . $required . '>';
        }
        echo '</td></tr>';
    }
}
