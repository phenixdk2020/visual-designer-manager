<?php

declare(strict_types=1);

namespace VisualDesignerManager\Admin;

use VisualDesignerManager\Gallery\GalleryRepository;

final class GalleryController
{
    private function __construct()
    {
    }

    public static function register(): void
    {
        add_action('init', [self::class, 'postType']);
        add_action('add_meta_boxes', [self::class, 'metaBoxes']);
        add_action('save_post_' . GalleryRepository::POST_TYPE, [self::class, 'save'], 10, 2);
        add_filter('manage_' . GalleryRepository::POST_TYPE . '_posts_columns', [self::class, 'columns']);
        add_action('manage_' . GalleryRepository::POST_TYPE . '_posts_custom_column', [self::class, 'columnValue'], 10, 2);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
    }

    public static function postType(): void
    {
        register_post_type(GalleryRepository::POST_TYPE, [
            'labels' => [
                'name' => 'Billedgalleri',
                'singular_name' => 'Album',
                'add_new' => 'Tilføj album',
                'add_new_item' => 'Tilføj album',
                'edit_item' => 'Rediger album',
                'new_item' => 'Nyt album',
                'view_item' => 'Vis album',
                'search_items' => 'Søg albummer',
                'not_found' => 'Ingen albummer fundet',
            ],
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => AdminController::MENU_SLUG,
            'show_in_rest' => true,
            'has_archive' => true,
            'rewrite' => ['slug' => 'billedgalleri'],
            'supports' => ['title', 'editor', 'thumbnail', 'page-attributes'],
            'menu_icon' => 'dashicons-format-gallery',
        ]);
    }

    public static function enqueue(string $hook): void
    {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== GalleryRepository::POST_TYPE) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_script('vdm-gallery-admin', VDM_URL . 'assets/gallery-admin.js', ['media-editor'], VDM_VERSION, true);
    }

    public static function metaBoxes(): void
    {
        add_meta_box(
            'vdm-gallery-images',
            'Albumbilleder',
            [self::class, 'renderMetaBox'],
            GalleryRepository::POST_TYPE,
            'normal',
            'high'
        );
    }

    public static function renderMetaBox(\WP_Post $post): void
    {
        $album = GalleryRepository::get((int) $post->ID);
        wp_nonce_field('vdm_save_gallery', 'vdm_gallery_nonce');

        echo '<p><label for="vdm-gallery-summary"><strong>Kort beskrivelse</strong></label></p>';
        echo '<textarea class="large-text" rows="3" id="vdm-gallery-summary" name="vdm_gallery[summary]">' . esc_textarea((string) $album['summary']) . '</textarea>';
        echo '<p class="description">Vises på albumkortet. Den fulde albumbeskrivelse skrives i WordPress-editoren.</p>';

        $ids = is_array($album['imageIds'] ?? null) ? array_values($album['imageIds']) : [];
        echo '<input type="hidden" id="vdm-gallery-image-ids" name="vdm_gallery[imageIdsJson]" value="' . esc_attr((string) wp_json_encode($ids)) . '">';
        echo '<p><button type="button" class="button button-primary" id="vdm-gallery-select-images">Vælg billeder</button> ';
        echo '<button type="button" class="button" id="vdm-gallery-clear-images">Fjern alle</button></p>';
        echo '<p class="description">Billederne gemmes i den rækkefølge, de vises nedenfor. Træk dem for at ændre rækkefølgen.</p>';
        echo '<div id="vdm-gallery-image-list" class="vdm-gallery-image-list">';
        foreach ((array) ($album['images'] ?? []) as $image) {
            if (!is_array($image)) {
                continue;
            }
            echo '<div class="vdm-gallery-admin-image" draggable="true" data-attachment-id="' . esc_attr((string) ($image['id'] ?? 0)) . '">';
            echo '<img src="' . esc_url((string) ($image['thumb'] ?? '')) . '" alt="">';
            echo '<button type="button" class="button-link-delete vdm-gallery-remove-image">Fjern</button>';
            echo '</div>';
        }
        echo '</div>';
    }

    public static function save(int $postId, \WP_Post $post): void
    {
        if ($post->post_type !== GalleryRepository::POST_TYPE || wp_is_post_revision($postId)) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!isset($_POST['vdm_gallery_nonce']) || !wp_verify_nonce(sanitize_text_field((string) wp_unslash($_POST['vdm_gallery_nonce'])), 'vdm_save_gallery')) {
            return;
        }
        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        $raw = isset($_POST['vdm_gallery']) && is_array($_POST['vdm_gallery']) ? wp_unslash($_POST['vdm_gallery']) : [];
        if (!is_array($raw)) {
            $raw = [];
        }
        $decoded = json_decode((string) ($raw['imageIdsJson'] ?? '[]'), true);
        $raw['imageIds'] = is_array($decoded) ? $decoded : [];
        GalleryRepository::save($postId, $raw);
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
                $result['vdm_gallery_images'] = 'Billeder';
            }
        }
        return $result;
    }

    public static function columnValue(string $column, int $postId): void
    {
        if ($column !== 'vdm_gallery_images') {
            return;
        }
        $album = GalleryRepository::get($postId);
        echo esc_html((string) count((array) ($album['imageIds'] ?? [])));
    }
}
