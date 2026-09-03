<?php

declare(strict_types=1);

namespace VisualDesignerManager\Transfer;

use VisualDesignerManager\Events\EventRepository;
use VisualDesignerManager\Gallery\GalleryRepository;
use VisualDesignerManager\Model\NodeSchema;
use VisualDesignerManager\Storage\LayoutRepository;
use VisualDesignerManager\Storage\SiteDesignRepository;
use VisualDesignerManager\Storage\SiteSettingsRepository;
use VisualDesignerManager\Storage\TemplateRepository;
use VisualDesignerManager\Vehicles\VehicleRepository;

final class PortableExporter
{
    /** @return array{path:string,filename:string,manifest:array<string,mixed>,summary:array<string,int>} */
    public static function build(): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('PHP ZipArchive is required for VDM portable export.');
        }

        $temp = wp_tempnam('vdm-portable-' . gmdate('Ymd-His') . '.zip');
        if (!is_string($temp) || $temp === '') {
            throw new \RuntimeException('A temporary file could not be created for the portable export.');
        }
        @unlink($temp);
        $zipPath = $temp . '.zip';

        $siteSettings = SiteSettingsRepository::get();
        $header = TemplateRepository::get(TemplateRepository::HEADER);
        $footer = TemplateRepository::get(TemplateRepository::FOOTER);
        $pages = self::pages();
        $events = self::contentRecords(EventRepository::POST_TYPE, [EventRepository::class, 'get']);
        $vehicles = self::contentRecords(VehicleRepository::POST_TYPE, [VehicleRepository::class, 'get']);
        $galleries = self::contentRecords(GalleryRepository::POST_TYPE, [GalleryRepository::class, 'get']);
        $menus = self::menus();

        $mediaIds = [];
        foreach ([(int) ($siteSettings['logoId'] ?? 0), (int) ($siteSettings['siteIconId'] ?? 0)] as $mediaId) {
            self::collectMediaId($mediaIds, $mediaId);
        }
        self::collectDocumentMedia($mediaIds, $header);
        self::collectDocumentMedia($mediaIds, $footer);

        foreach ($pages as $page) {
            self::collectDocumentMedia($mediaIds, is_array($page['layout'] ?? null) ? $page['layout'] : []);
            self::collectMediaId($mediaIds, (int) ($page['featuredImageSourceId'] ?? 0));
            self::collectContentMedia($mediaIds, (string) ($page['content'] ?? ''));
        }
        foreach ($events as $record) {
            self::collectMediaId($mediaIds, (int) ($record['featuredImageSourceId'] ?? 0));
            self::collectContentMedia($mediaIds, (string) ($record['content'] ?? ''));
        }
        foreach ($vehicles as $record) {
            self::collectMediaId($mediaIds, (int) ($record['featuredImageSourceId'] ?? 0));
            self::collectContentMedia($mediaIds, (string) ($record['content'] ?? ''));
        }
        foreach ($galleries as $record) {
            self::collectMediaId($mediaIds, (int) ($record['featuredImageSourceId'] ?? 0));
            foreach ((array) ($record['imageIds'] ?? []) as $imageId) {
                self::collectMediaId($mediaIds, (int) $imageId);
            }
            self::collectContentMedia($mediaIds, (string) ($record['content'] ?? ''));
        }

        ksort($mediaIds, SORT_NUMERIC);
        $media = self::mediaRecords(array_keys($mediaIds));
        $mediaPayload = [];
        foreach (array_values($media) as $record) {
            $publicRecord = $record;
            unset($publicRecord['_filePath']);
            $mediaPayload[] = $publicRecord;
        }

        $site = [
            'source' => [
                'homeUrl' => home_url('/'),
                'siteUrl' => site_url('/'),
                'name' => (string) get_bloginfo('name'),
            ],
            'settings' => [
                'siteIdentity' => [
                    'siteTitle' => (string) ($siteSettings['siteTitle'] ?? ''),
                    'tagline' => (string) ($siteSettings['tagline'] ?? ''),
                    'organizationName' => (string) ($siteSettings['organizationName'] ?? ''),
                    'contactEmail' => (string) ($siteSettings['contactEmail'] ?? ''),
                    'contactPhone' => (string) ($siteSettings['contactPhone'] ?? ''),
                    'logoSourceId' => (int) ($siteSettings['logoId'] ?? 0),
                    'siteIconSourceId' => (int) ($siteSettings['siteIconId'] ?? 0),
                ],
                'reading' => [
                    'showOnFront' => (string) get_option('show_on_front', 'posts'),
                    'frontPageSourceId' => (int) get_option('page_on_front', 0),
                    'postsPageSourceId' => (int) get_option('page_for_posts', 0),
                ],
                'permalinkStructure' => (string) get_option('permalink_structure', ''),
            ],
        ];

        $payloads = [
            'site.json' => PortablePackage::json($site),
            'content/pages.json' => PortablePackage::json(['items' => $pages]),
            'content/events.json' => PortablePackage::json(['items' => $events]),
            'content/vehicles.json' => PortablePackage::json(['items' => $vehicles]),
            'content/galleries.json' => PortablePackage::json(['items' => $galleries]),
            'content/menus.json' => PortablePackage::json(['items' => $menus]),
            'templates/header.json' => PortablePackage::json(['document' => $header]),
            'templates/footer.json' => PortablePackage::json(['document' => $footer]),
            'settings/site-design.json' => PortablePackage::json(['settings' => SiteDesignRepository::get()]),
            'media/index.json' => PortablePackage::json(['items' => $mediaPayload]),
        ];

        $zip = new \ZipArchive();
        $opened = $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if ($opened !== true) {
            throw new \RuntimeException('Portable ZIP could not be created.');
        }

        $fileRecords = [];
        try {
            foreach ($payloads as $path => $content) {
                if (!PortablePackage::safePath($path) || !$zip->addFromString($path, $content)) {
                    throw new \RuntimeException('Portable JSON could not be added: ' . $path);
                }
                $fileRecords[] = [
                    'path' => $path,
                    'size' => strlen($content),
                    'sha256' => hash('sha256', $content),
                ];
            }

            foreach ($media as $record) {
                $archivePath = (string) ($record['archivePath'] ?? '');
                $filePath = (string) ($record['_filePath'] ?? '');
                if (!PortablePackage::safePath($archivePath) || !is_file($filePath) || !is_readable($filePath)) {
                    throw new \RuntimeException('Referenced media file cannot be exported: ' . $archivePath);
                }
                if (!$zip->addFile($filePath, $archivePath)) {
                    throw new \RuntimeException('Referenced media file could not be added: ' . $archivePath);
                }
                $fileRecords[] = [
                    'path' => $archivePath,
                    'size' => (int) ($record['size'] ?? 0),
                    'sha256' => (string) ($record['sha256'] ?? ''),
                ];
            }

            $manifest = [
                'format' => PortablePackage::FORMAT,
                'schemaVersion' => PortablePackage::SCHEMA_VERSION,
                'managerVersion' => VDM_VERSION,
                'createdAt' => gmdate('c'),
                'source' => [
                    'homeUrl' => home_url('/'),
                    'siteUrl' => site_url('/'),
                    'name' => (string) get_bloginfo('name'),
                ],
                'files' => $fileRecords,
                'contentSha256' => PortablePackage::contentHash($fileRecords),
            ];
            if (!$zip->addFromString('manifest.json', PortablePackage::json($manifest))) {
                throw new \RuntimeException('Portable manifest could not be added.');
            }
        } catch (\Throwable $exception) {
            $zip->close();
            @unlink($zipPath);
            throw $exception;
        }

        if (!$zip->close()) {
            @unlink($zipPath);
            throw new \RuntimeException('Portable ZIP could not be finalized.');
        }

        $inspection = PortablePackage::inspect($zipPath);
        $summary = is_array($inspection['summary'] ?? null) ? $inspection['summary'] : [];
        $slug = sanitize_title((string) ($siteSettings['organizationName'] ?? ''));
        if ($slug === '') {
            $slug = sanitize_title((string) get_bloginfo('name'));
        }
        if ($slug === '') {
            $slug = 'site';
        }

        return [
            'path' => $zipPath,
            'filename' => 'visual-designer-' . $slug . '-site-' . gmdate('Ymd-His') . '.zip',
            'manifest' => $inspection['manifest'],
            'summary' => array_map('intval', $summary),
        ];
    }

    /** @return list<array<string,mixed>> */
    private static function pages(): array
    {
        $query = new \WP_Query([
            'post_type' => 'page',
            'post_status' => ['publish', 'draft', 'private', 'pending'],
            'posts_per_page' => -1,
            'orderby' => ['menu_order' => 'ASC', 'ID' => 'ASC'],
            'order' => 'ASC',
            'no_found_rows' => true,
        ]);

        $items = [];
        foreach ($query->posts as $post) {
            if (!$post instanceof \WP_Post) {
                continue;
            }
            $id = (int) $post->ID;
            $items[] = [
                'sourceId' => $id,
                'title' => (string) $post->post_title,
                'slug' => (string) $post->post_name,
                'path' => (string) get_page_uri($id),
                'parentSourceId' => (int) $post->post_parent,
                'status' => (string) $post->post_status,
                'menuOrder' => (int) $post->menu_order,
                'content' => (string) $post->post_content,
                'excerpt' => (string) $post->post_excerpt,
                'featuredImageSourceId' => (int) get_post_thumbnail_id($id),
                'layout' => LayoutRepository::get($id),
            ];
        }
        wp_reset_postdata();
        return $items;
    }

    /** @param callable(int):array<string,mixed> $reader
     *  @return list<array<string,mixed>>
     */
    private static function contentRecords(string $postType, callable $reader): array
    {
        $query = new \WP_Query([
            'post_type' => $postType,
            'post_status' => ['publish', 'draft', 'private', 'pending'],
            'posts_per_page' => -1,
            'orderby' => ['menu_order' => 'ASC', 'ID' => 'ASC'],
            'order' => 'ASC',
            'no_found_rows' => true,
        ]);

        $items = [];
        foreach ($query->posts as $post) {
            if (!$post instanceof \WP_Post) {
                continue;
            }
            $id = (int) $post->ID;
            $data = $reader($id);
            unset($data['id'], $data['permalink'], $data['image'], $data['cover'], $data['images']);
            $items[] = array_merge([
                'sourceId' => $id,
                'slug' => (string) $post->post_name,
                'status' => (string) $post->post_status,
                'menuOrder' => (int) $post->menu_order,
                'featuredImageSourceId' => (int) get_post_thumbnail_id($id),
            ], $data);
        }
        wp_reset_postdata();
        return $items;
    }

    /** @return list<array<string,mixed>> */
    private static function menus(): array
    {
        $menus = wp_get_nav_menus(['orderby' => 'name']);
        if (!is_array($menus)) {
            return [];
        }

        $items = [];
        foreach ($menus as $menu) {
            if (!$menu instanceof \WP_Term) {
                continue;
            }
            $menuItems = wp_get_nav_menu_items((int) $menu->term_id, ['post_status' => 'publish']);
            $records = [];
            foreach (is_array($menuItems) ? $menuItems : [] as $item) {
                if (!$item instanceof \WP_Post) {
                    continue;
                }
                $classes = is_array($item->classes ?? null) ? array_values(array_filter(array_map('sanitize_html_class', $item->classes))) : [];
                $records[] = [
                    'sourceId' => (int) $item->ID,
                    'parentSourceId' => (int) ($item->menu_item_parent ?? 0),
                    'title' => (string) ($item->title ?? ''),
                    'url' => (string) ($item->url ?? ''),
                    'target' => (string) ($item->target ?? ''),
                    'attrTitle' => (string) ($item->attr_title ?? ''),
                    'description' => (string) ($item->description ?? ''),
                    'classes' => $classes,
                    'xfn' => (string) ($item->xfn ?? ''),
                    'type' => (string) ($item->type ?? 'custom'),
                    'object' => (string) ($item->object ?? 'custom'),
                    'objectSourceId' => (int) ($item->object_id ?? 0),
                    'menuOrder' => (int) ($item->menu_order ?? 0),
                ];
            }
            $items[] = [
                'sourceId' => (int) $menu->term_id,
                'name' => (string) $menu->name,
                'slug' => (string) $menu->slug,
                'description' => (string) $menu->description,
                'items' => $records,
            ];
        }
        return $items;
    }

    /** @param list<int> $ids
     *  @return array<int,array<string,mixed>>
     */
    private static function mediaRecords(array $ids): array
    {
        $records = [];
        foreach ($ids as $id) {
            $id = absint($id);
            if ($id <= 0 || get_post_type($id) !== 'attachment') {
                continue;
            }
            $file = get_attached_file($id, true);
            if (!is_string($file) || !is_file($file) || !is_readable($file)) {
                throw new \RuntimeException('Referenced attachment #' . $id . ' has no readable original file.');
            }
            $size = filesize($file);
            if (!is_int($size) || $size < 0 || $size > PortablePackage::MAX_ENTRY_BYTES) {
                throw new \RuntimeException('Referenced attachment #' . $id . ' exceeds the allowed portable file size.');
            }
            $sha = hash_file('sha256', $file);
            if (!is_string($sha)) {
                throw new \RuntimeException('Referenced attachment #' . $id . ' could not be hashed.');
            }
            $filename = sanitize_file_name(wp_basename($file));
            if ($filename === '') {
                $filename = 'attachment-' . $id;
            }
            $archivePath = 'media/files/' . $id . '-' . $filename;
            $post = get_post($id);
            $records[$id] = [
                'sourceId' => $id,
                'archivePath' => $archivePath,
                'filename' => $filename,
                'mimeType' => (string) get_post_mime_type($id),
                'title' => $post instanceof \WP_Post ? (string) $post->post_title : '',
                'caption' => $post instanceof \WP_Post ? (string) $post->post_excerpt : '',
                'description' => $post instanceof \WP_Post ? (string) $post->post_content : '',
                'alt' => (string) get_post_meta($id, '_wp_attachment_image_alt', true),
                'sourceUrl' => (string) wp_get_attachment_url($id),
                'size' => $size,
                'sha256' => $sha,
                '_filePath' => $file,
            ];
        }

        return $records;
    }

    /** @param array<int,true> $ids */
    private static function collectMediaId(array &$ids, int $id): void
    {
        $id = absint($id);
        if ($id > 0 && get_post_type($id) === 'attachment') {
            $ids[$id] = true;
        }
    }

    /** @param array<int,true> $ids
     *  @param array<string,mixed> $document
     */
    private static function collectDocumentMedia(array &$ids, array $document): void
    {
        foreach (is_array($document['nodes'] ?? null) ? $document['nodes'] : [] as $node) {
            if (!is_array($node)) {
                continue;
            }
            if ((string) ($node['type'] ?? '') === NodeSchema::IMAGE) {
                $props = is_array($node['props'] ?? null) ? $node['props'] : [];
                self::collectMediaId($ids, absint($props['attachmentId'] ?? 0));
            }
        }
    }

    /** @param array<int,true> $ids */
    private static function collectContentMedia(array &$ids, string $content): void
    {
        if ($content === '') {
            return;
        }
        if (preg_match_all('/\bwp-image-(\d+)\b/', $content, $matches)) {
            foreach ($matches[1] as $id) {
                self::collectMediaId($ids, (int) $id);
            }
        }
        if (preg_match_all('/\b(?:src|href)=["\']([^"\']+)["\']/i', $content, $urlMatches)) {
            foreach ($urlMatches[1] as $url) {
                $id = attachment_url_to_postid(html_entity_decode((string) $url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                self::collectMediaId($ids, (int) $id);
            }
        }
    }

    private function __construct()
    {
    }
}
