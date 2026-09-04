<?php

declare(strict_types=1);

namespace VisualDesignerManager\Admin;

use VisualDesignerManager\Fields\VehicleFieldRegistry;
use VisualDesignerManager\Vehicles\VehicleRepository;

final class VehicleController
{
    private function __construct()
    {
    }

    public static function register(): void
    {
        add_action('init', [self::class, 'postType']);
        add_action('add_meta_boxes', [self::class, 'metaBoxes']);
        add_action('save_post_' . VehicleRepository::POST_TYPE, [self::class, 'save'], 10, 2);
        add_filter('manage_' . VehicleRepository::POST_TYPE . '_posts_columns', [self::class, 'columns']);
        add_action('manage_' . VehicleRepository::POST_TYPE . '_posts_custom_column', [self::class, 'columnValue'], 10, 2);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
    }

    public static function postType(): void
    {
        register_post_type(VehicleRepository::POST_TYPE, [
            'labels' => [
                'name' => 'Køretøjer',
                'singular_name' => 'Køretøj',
                'add_new' => 'Tilføj køretøj',
                'add_new_item' => 'Tilføj køretøj',
                'edit_item' => 'Rediger køretøj',
                'new_item' => 'Nyt køretøj',
                'view_item' => 'Vis køretøj',
                'search_items' => 'Søg køretøjer',
                'not_found' => 'Ingen køretøjer fundet',
            ],
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => AdminController::MENU_SLUG,
            'show_in_rest' => true,
            'has_archive' => true,
            'rewrite' => ['slug' => 'koeretoejer'],
            'supports' => ['title', 'editor', 'thumbnail'],
            'menu_icon' => 'dashicons-car',
        ]);
    }

    public static function enqueue(string $hook): void
    {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== VehicleRepository::POST_TYPE) {
            return;
        }
        wp_enqueue_script('vdm-vehicle-admin', VDM_URL . 'assets/vehicle-admin.js', [], VDM_VERSION, true);
    }

    public static function metaBoxes(): void
    {
        add_meta_box(
            'vdm-vehicle-details',
            'Køretøjsoplysninger',
            [self::class, 'renderMetaBox'],
            VehicleRepository::POST_TYPE,
            'normal',
            'high'
        );
    }

    public static function renderMetaBox(\WP_Post $post): void
    {
        $vehicle = VehicleRepository::get((int) $post->ID);
        wp_nonce_field('vdm_save_vehicle', 'vdm_vehicle_nonce');

        echo '<table class="form-table" role="presentation"><tbody>';
        foreach ([
            'type' => 'Type',
            'country' => 'Oprindelsesland',
            'status' => 'Status',
            'power' => 'Motorydelse',
            'length' => 'Længde',
            'width' => 'Bredde',
            'height' => 'Højde',
        ] as $name => $label) {
            self::field($label, $name, (string) ($vehicle[$name] ?? ''));
        }
        echo '<tr><th scope="row"><label for="vdm-vehicle-summary">Kort beskrivelse</label></th><td>';
        echo '<textarea class="large-text" rows="4" id="vdm-vehicle-summary" name="vdm_vehicle[summary]">' . esc_textarea((string) $vehicle['summary']) . '</textarea>';
        echo '<p class="description">Vises på køretøjskort. Den fulde beskrivelse skrives i WordPress-editoren.</p></td></tr>';
        echo '</tbody></table>';

        $customValues = is_array($vehicle['customFields'] ?? null) ? $vehicle['customFields'] : [];
        $definitions = array_values(array_filter(VehicleFieldRegistry::all(), static fn(array $row): bool => !empty($row['enabled'])));
        if ($definitions !== []) {
            echo '<hr><h3>Tekniske data</h3><p class="description">Felterne styres centralt under Visual Designer Manager → Køretøjsfelter.</p><table class="form-table" role="presentation"><tbody>';
            foreach ($definitions as $definition) {
                self::customField($definition, (string) ($customValues[(string) $definition['id']] ?? ''));
            }
            echo '</tbody></table>';
        }

        echo '<hr><h3>Ekstra felter for dette køretøj</h3>';
        echo '<p class="description">Brug disse rækker til oplysninger, der ikke skal være et centralt genbrugsfelt.</p>';
        echo '<table class="widefat striped" id="vdm-vehicle-specs"><thead><tr><th>Felt</th><th>Værdi</th><th style="width:90px">Handling</th></tr></thead><tbody>';
        $specs = is_array($vehicle['specs'] ?? null) ? $vehicle['specs'] : [];
        if ($specs === []) {
            $specs = [['label' => '', 'value' => '']];
        }
        foreach ($specs as $index => $spec) {
            self::specRow((int) $index, is_array($spec) ? $spec : []);
        }
        echo '</tbody></table>';
        echo '<p><button type="button" class="button" id="vdm-add-vehicle-spec">Tilføj felt</button></p>';
        echo '<template id="vdm-vehicle-spec-template">';
        self::specRow(999999, ['label' => '', 'value' => '']);
        echo '</template>';
    }

    public static function save(int $postId, \WP_Post $post): void
    {
        if ($post->post_type !== VehicleRepository::POST_TYPE || wp_is_post_revision($postId)) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!isset($_POST['vdm_vehicle_nonce']) || !wp_verify_nonce(sanitize_text_field((string) wp_unslash($_POST['vdm_vehicle_nonce'])), 'vdm_save_vehicle')) {
            return;
        }
        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        $raw = isset($_POST['vdm_vehicle']) && is_array($_POST['vdm_vehicle']) ? wp_unslash($_POST['vdm_vehicle']) : [];
        VehicleRepository::save($postId, is_array($raw) ? $raw : []);
    }

    /** @param array<string,string> $columns @return array<string,string> */
    public static function columns(array $columns): array
    {
        $result = [];
        foreach ($columns as $key => $label) {
            $result[$key] = $label;
            if ($key === 'title') {
                $result['vdm_vehicle_type'] = 'Type';
                $result['vdm_vehicle_year'] = 'Årgang';
                $result['vdm_vehicle_status'] = 'Status';
            }
        }
        return $result;
    }

    public static function columnValue(string $column, int $postId): void
    {
        $vehicle = VehicleRepository::get($postId);
        $map = [
            'vdm_vehicle_type' => 'type',
            'vdm_vehicle_year' => 'year',
            'vdm_vehicle_status' => 'status',
        ];
        if (isset($map[$column])) {
            echo esc_html((string) ($vehicle[$map[$column]] ?? ''));
        }
    }

    private static function field(string $label, string $name, string $value): void
    {
        echo '<tr><th scope="row"><label for="vdm-vehicle-' . esc_attr($name) . '">' . esc_html($label) . '</label></th><td>';
        echo '<input class="regular-text" type="text" id="vdm-vehicle-' . esc_attr($name) . '" name="vdm_vehicle[' . esc_attr($name) . ']" value="' . esc_attr($value) . '">';
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
        $unit = trim((string) ($definition['unit'] ?? ''));
        if ($unit !== '') {
            $label .= ' (' . $unit . ')';
        }
        $type = (string) ($definition['type'] ?? 'text');
        $name = 'vdm_vehicle[customFields][' . $id . ']';
        echo '<tr><th scope="row"><label for="vdm-vehicle-custom-' . esc_attr($id) . '">' . esc_html($label) . '</label></th><td>';
        if (in_array($type, ['textarea', 'richtext'], true)) {
            echo '<textarea class="large-text" rows="4" id="vdm-vehicle-custom-' . esc_attr($id) . '" name="' . esc_attr($name) . '">' . esc_textarea($value) . '</textarea>';
        } elseif ($type === 'boolean') {
            echo '<label><input type="checkbox" id="vdm-vehicle-custom-' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="1"' . checked($value, '1', false) . '> Ja</label>';
        } else {
            $inputType = match ($type) {
                'number', 'integer' => 'number',
                'date' => 'date',
                default => 'text',
            };
            $step = $type === 'number' ? ' step="any"' : '';
            echo '<input class="regular-text" type="' . esc_attr($inputType) . '"' . $step . ' id="vdm-vehicle-custom-' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '">';
        }
        echo '</td></tr>';
    }

    /** @param array<string,mixed> $spec */
    private static function specRow(int $index, array $spec): void
    {
        echo '<tr class="vdm-vehicle-spec-row">';
        echo '<td><input class="widefat" type="text" name="vdm_vehicle[specs][' . esc_attr((string) $index) . '][label]" value="' . esc_attr((string) ($spec['label'] ?? '')) . '"></td>';
        echo '<td><input class="widefat" type="text" name="vdm_vehicle[specs][' . esc_attr((string) $index) . '][value]" value="' . esc_attr((string) ($spec['value'] ?? '')) . '"></td>';
        echo '<td><button type="button" class="button-link-delete vdm-remove-vehicle-spec">Fjern</button></td>';
        echo '</tr>';
    }
}
