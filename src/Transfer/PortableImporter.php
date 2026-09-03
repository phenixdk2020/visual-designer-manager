<?php

declare(strict_types=1);

namespace VisualDesignerManager\Transfer;

use VisualDesignerManager\Events\EventRepository;
use VisualDesignerManager\Gallery\GalleryRepository;
use VisualDesignerManager\Model\LayoutDocument;
use VisualDesignerManager\Model\NodeSchema;
use VisualDesignerManager\Storage\LayoutRepository;
use VisualDesignerManager\Storage\SiteDesignRepository;
use VisualDesignerManager\Storage\SiteSettingsRepository;
use VisualDesignerManager\Storage\TemplateRepository;
use VisualDesignerManager\Vehicles\VehicleRepository;

final class PortableImporter
{
    private const SOURCE_ID_META = '_vdm_portable_source_id';
    private const SOURCE_SITE_META = '_vdm_portable_source_site';
    private const SOURCE_KIND_META = '_vdm_portable_source_kind';
    private const MEDIA_SHA_META = '_vdm_portable_sha256';

    /**
     * @return array{pages:int,events:int,vehicles:int,galleries:int,menus:int,media:int,mediaCreated:int,mediaReused:int}
     */
    public static function import(string $zipPath, int $userId): array
    {
        $inspection = PortablePackage::inspect($zipPath);
        $manifest = is_array($inspection['manifest'] ?? null) ? $inspection['manifest'] : [];

        $zip = new \ZipArchive();
        $opened = $zip->open($zipPath, \ZipArchive::RDONLY);
        if ($opened !== true) {
            throw new \RuntimeException('Portable ZIP could not be opened for import.');
        }

        $createdPosts = [];
        $createdMenuItems = [];
        $createdMenus = [];

        try {
            $site = PortablePackage::readJson($zip, 'site.json');
            $pageRecords = self::items($zip, 'content/pages.json');
            $eventRecords = self::items($zip, 'content/events.json');
            $vehicleRecords = self::items($zip, 'content/vehicles.json');
            $galleryRecords = self::items($zip, 'content/galleries.json');
            $menuRecords = self::items($zip, 'content/menus.json');
            $mediaRecords = self::items($zip, 'media/index.json');
            $headerPayload = PortablePackage::readJson($zip, 'templates/header.json');
            $footerPayload = PortablePackage::readJson($zip, 'templates/footer.json');
            $siteDesignPayload = PortablePackage::readJson($zip, 'settings/site-design.json');

            $header = is_array($headerPayload['document'] ?? null) ? $headerPayload['document'] : [];
            $footer = is_array($footerPayload['document'] ?? null) ? $footerPayload['document'] : [];
            $siteDesign = is_array($siteDesignPayload['settings'] ?? null) ? $siteDesignPayload['settings'] : [];

            self::validatePayloads(
                $manifest,
                $site,
                $pageRecords,
                $eventRecords,
                $vehicleRecords,
                $galleryRecords,
                $menuRecords,
                $mediaRecords,
                $header,
                $footer,
                $siteDesign
            );

            $sourceKey = self::sourceKey($site, $manifest);
            $source = is_array($site['source'] ?? null) ? $site['source'] : [];
            $sourceHome = (string) ($source['homeUrl'] ?? '');
            $sourceSite = (string) ($source['siteUrl'] ?? '');

            $mediaResult = self::importMedia($zip, $mediaRecords, $sourceKey, $createdPosts);
            $mediaMap = $mediaResult['map'];
            $mediaUrlMap = $mediaResult['urlMap'];

            $pageMap = self::importPages($pageRecords, $sourceKey, $mediaMap, $mediaUrlMap, $sourceHome, $sourceSite, $createdPosts);
            $eventMap = self::importContentType(
                EventRepository::POST_TYPE,
                'event',
                $eventRecords,
                $sourceKey,
                $mediaMap,
                $mediaUrlMap,
                $sourceHome,
                $sourceSite,
                $createdPosts,
                static function (int $postId, array $record): void {
                    EventRepository::save($postId, $record);
                }
            );
            $vehicleMap = self::importContentType(
                VehicleRepository::POST_TYPE,
                'vehicle',
                $vehicleRecords,
                $sourceKey,
                $mediaMap,
                $mediaUrlMap,
                $sourceHome,
                $sourceSite,
                $createdPosts,
                static function (int $postId, array $record): void {
                    VehicleRepository::save($postId, $record);
                }
            );
            $galleryMap = self::importGalleries(
                $galleryRecords,
                $sourceKey,
                $mediaMap,
                $mediaUrlMap,
                $sourceHome,
                $sourceSite,
                $createdPosts
            );

            $postMap = $pageMap + $eventMap + $vehicleMap + $galleryMap;
            $menuMap = self::importMenus(
                $menuRecords,
                $sourceKey,
                $postMap,
                $sourceHome,
                $sourceSite,
                $createdMenuItems,
                $createdMenus
            );

            self::applyLayouts(
                $pageRecords,
                $pageMap,
                $mediaMap,
                $menuMap,
                $mediaUrlMap,
                $sourceHome,
                $sourceSite,
                $userId
            );

            TemplateRepository::save(
                TemplateRepository::HEADER,
                self::remapDocument($header, $mediaMap, $menuMap, $mediaUrlMap, $sourceHome, $sourceSite),
                $userId
            );
            TemplateRepository::save(
                TemplateRepository::FOOTER,
                self::remapDocument($footer, $mediaMap, $menuMap, $mediaUrlMap, $sourceHome, $sourceSite),
                $userId
            );
            SiteDesignRepository::save($siteDesign);
            self::applySiteSettings($site, $pageMap, $mediaMap);

            update_option('vdm_plugin_version', VDM_VERSION, true);
            flush_rewrite_rules(false);

            return [
                'pages' => count($pageMap),
                'events' => count($eventMap),
                'vehicles' => count($vehicleMap),
                'galleries' => count($galleryMap),
                'menus' => count($menuMap),
                'media' => count($mediaMap),
                'mediaCreated' => (int) $mediaResult['created'],
                'mediaReused' => (int) $mediaResult['reused'],
            ];
        } catch (\Throwable $exception) {
            self::rollbackCreated($createdMenuItems, $createdMenus, $createdPosts);
            throw $exception;
        } finally {
            $zip->close();
        }
    }

    /** @return list<array<string,mixed>> */
    private static function items(\ZipArchive $zip, string $path): array
    {
        $payload = PortablePackage::readJson($zip, $path);
        $raw = $payload['items'] ?? [];
        if (!is_array($raw)) {
            throw new \RuntimeException('Portable item collection is invalid: ' . $path);
        }

        $items = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                throw new \RuntimeException('Portable item collection contains an invalid record: ' . $path);
            }
            $items[] = $item;
        }
        return $items;
    }

    /**
     * @param array<string,mixed> $manifest
     * @param array<string,mixed> $site
     * @param list<array<string,mixed>> $pages
     * @param list<array<string,mixed>> $events
     * @param list<array<string,mixed>> $vehicles
     * @param list<array<string,mixed>> $galleries
     * @param list<array<string,mixed>> $menus
     * @param list<array<string,mixed>> $media
     * @param array<string,mixed> $header
     * @param array<string,mixed> $footer
     * @param array<string,mixed> $siteDesign
     */
    private static function validatePayloads(
        array $manifest,
        array $site,
        array $pages,
        array $events,
        array $vehicles,
        array $galleries,
        array $menus,
        array $media,
        array $header,
        array $footer,
        array $siteDesign
    ): void {
        if ((string) ($manifest['schemaVersion'] ?? '') !== PortablePackage::SCHEMA_VERSION) {
            throw new \RuntimeException('Portable schema changed after validation.');
        }

        $settings = is_array($site['settings'] ?? null) ? $site['settings'] : [];
        if (!is_array($site['source'] ?? null) || !is_array($settings['siteIdentity'] ?? null)) {
            throw new \RuntimeException('Portable site identity is missing.');
        }

        self::validateSourceIds($pages, 'page');
        self::validateSourceIds($events, 'event');
        self::validateSourceIds($vehicles, 'vehicle');
        self::validateSourceIds($galleries, 'gallery');
        self::validateSourceIds($menus, 'menu');
        self::validateSourceIds($media, 'media');

        foreach ($pages as $page) {
            if (!is_array($page['layout'] ?? null)) {
                throw new \RuntimeException('Portable page layout is invalid.');
            }
            LayoutDocument::normalize($page['layout']);
        }
        LayoutDocument::normalize($header);
        LayoutDocument::normalize($footer);
        SiteDesignRepository::normalize($siteDesign);

        $manifestPaths = [];
        foreach (is_array($manifest['files'] ?? null) ? $manifest['files'] : [] as $file) {
            if (is_array($file)) {
                $manifestPaths[strtolower((string) ($file['path'] ?? ''))] = $file;
            }
        }

        foreach ($media as $record) {
            $path = (string) ($record['archivePath'] ?? '');
            $size = (int) ($record['size'] ?? -1);
            $sha = strtolower((string) ($record['sha256'] ?? ''));
            if (!PortablePackage::safePath($path) || !str_starts_with($path, 'media/files/')) {
                throw new \RuntimeException('Portable media archive path is invalid.');
            }
            $manifestRecord = $manifestPaths[strtolower($path)] ?? null;
            if (!is_array($manifestRecord)
                || $size < 0
                || $size !== (int) ($manifestRecord['size'] ?? -2)
                || preg_match('/^[a-f0-9]{64}$/', $sha) !== 1
                || !hash_equals($sha, strtolower((string) ($manifestRecord['sha256'] ?? '')))
            ) {
                throw new \RuntimeException('Portable media manifest record is invalid.');
            }
        }

        foreach ($menus as $menu) {
            $items = $menu['items'] ?? [];
            if (!is_array($items)) {
                throw new \RuntimeException('Portable menu items are invalid.');
            }
            self::validateSourceIds(array_values(array_filter($items, 'is_array')), 'menu-item');
        }
    }

    /** @param list<array<string,mixed>> $records */
    private static function validateSourceIds(array $records, string $kind): void
    {
        $seen = [];
        foreach ($records as $record) {
            $sourceId = (int) ($record['sourceId'] ?? 0);
            if ($sourceId <= 0 || isset($seen[$sourceId])) {
                throw new \RuntimeException('Portable ' . $kind . ' source ID is invalid or duplicated.');
            }
            $seen[$sourceId] = true;
        }
    }

    /** @param array<string,mixed> $site
     *  @param array<string,mixed> $manifest
     */
    private static function sourceKey(array $site, array $manifest): string
    {
        $source = is_array($site['source'] ?? null) ? $site['source'] : [];
        $home = strtolower(rtrim((string) ($source['homeUrl'] ?? ''), '/'));
        $siteUrl = strtolower(rtrim((string) ($source['siteUrl'] ?? ''), '/'));
        if ($home === '' && $siteUrl === '') {
            $manifestSource = is_array($manifest['source'] ?? null) ? $manifest['source'] : [];
            $home = strtolower(rtrim((string) ($manifestSource['homeUrl'] ?? ''), '/'));
            $siteUrl = strtolower(rtrim((string) ($manifestSource['siteUrl'] ?? ''), '/'));
        }
        return hash('sha256', $home . "\n" . $siteUrl);
    }

    /**
     * @param list<array<string,mixed>> $records
     * @param list<int> $createdPosts
     * @return array{map:array<int,int>,urlMap:array<string,string>,created:int,reused:int}
     */
    private static function importMedia(\ZipArchive $zip, array $records, string $sourceKey, array &$createdPosts): array
    {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $map = [];
        $urlMap = [];
        $created = 0;
        $reused = 0;

        foreach ($records as $record) {
            $sourceId = (int) ($record['sourceId'] ?? 0);
            $sha = strtolower((string) ($record['sha256'] ?? ''));
            $mime = sanitize_mime_type((string) ($record['mimeType'] ?? ''));
            $existing = self::findAttachmentByHash($sha, $mime);
            if ($existing > 0) {
                $map[$sourceId] = $existing;
                $sourceUrl = esc_url_raw((string) ($record['sourceUrl'] ?? ''));
                $targetUrl = wp_get_attachment_url($existing);
                if ($sourceUrl !== '' && is_string($targetUrl) && $targetUrl !== '') {
                    $urlMap[$sourceUrl] = $targetUrl;
                }
                $reused++;
                continue;
            }

            $attachmentId = self::createAttachment($zip, $record, $sourceKey);
            $createdPosts[] = $attachmentId;
            $created++;
            $map[$sourceId] = $attachmentId;

            $sourceUrl = esc_url_raw((string) ($record['sourceUrl'] ?? ''));
            $targetUrl = wp_get_attachment_url($attachmentId);
            if ($sourceUrl !== '' && is_string($targetUrl) && $targetUrl !== '') {
                $urlMap[$sourceUrl] = $targetUrl;
            }
        }

        return ['map' => $map, 'urlMap' => $urlMap, 'created' => $created, 'reused' => $reused];
    }

    private static function findAttachmentByHash(string $sha, string $mime): int
    {
        if (preg_match('/^[a-f0-9]{64}$/', $sha) !== 1) {
            return 0;
        }

        $query = new \WP_Query([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => 5,
            'fields' => 'ids',
            'meta_key' => self::MEDIA_SHA_META,
            'meta_value' => $sha,
            'no_found_rows' => true,
        ]);
        foreach ($query->posts as $postId) {
            $id = (int) $postId;
            if ($id <= 0) {
                continue;
            }
            if (str_starts_with($mime, 'image/') && !wp_attachment_is_image($id)) {
                continue;
            }
            return $id;
        }
        return 0;
    }

    /** @param array<string,mixed> $record */
    private static function createAttachment(\ZipArchive $zip, array $record, string $sourceKey): int
    {
        $sourceId = (int) ($record['sourceId'] ?? 0);
        $archivePath = (string) ($record['archivePath'] ?? '');
        $filename = sanitize_file_name((string) ($record['filename'] ?? ''));
        $size = (int) ($record['size'] ?? -1);
        $sha = strtolower((string) ($record['sha256'] ?? ''));
        $declaredMime = sanitize_mime_type((string) ($record['mimeType'] ?? ''));

        if ($filename === '' || $size < 0 || preg_match('/^[a-f0-9]{64}$/', $sha) !== 1) {
            throw new \RuntimeException('Portable media record is incomplete.');
        }

        $fileType = wp_check_filetype($filename);
        $extensionMime = sanitize_mime_type((string) ($fileType['type'] ?? ''));
        if ($extensionMime === '' || ($declaredMime !== '' && $declaredMime !== $extensionMime)) {
            throw new \RuntimeException('Portable media file type is not allowed: ' . $filename);
        }

        $temp = wp_tempnam($filename);
        if (!is_string($temp) || $temp === '') {
            throw new \RuntimeException('A temporary media file could not be created.');
        }

        try {
            $stream = $zip->getStream($archivePath);
            if (!is_resource($stream)) {
                throw new \RuntimeException('Portable media stream could not be opened.');
            }
            $output = fopen($temp, 'wb');
            if (!is_resource($output)) {
                fclose($stream);
                throw new \RuntimeException('Portable media temporary file could not be opened.');
            }

            $hash = hash_init('sha256');
            $bytes = 0;
            try {
                while (!feof($stream)) {
                    $chunk = fread($stream, 1048576);
                    if ($chunk === false) {
                        throw new \RuntimeException('Portable media stream could not be read.');
                    }
                    if ($chunk === '') {
                        continue;
                    }
                    $bytes += strlen($chunk);
                    if ($bytes > PortablePackage::MAX_ENTRY_BYTES || fwrite($output, $chunk) !== strlen($chunk)) {
                        throw new \RuntimeException('Portable media extraction failed.');
                    }
                    hash_update($hash, $chunk);
                }
            } finally {
                fclose($stream);
                fclose($output);
            }

            if ($bytes !== $size || !hash_equals($sha, hash_final($hash))) {
                throw new \RuntimeException('Portable media failed extraction integrity validation.');
            }

            $sideload = wp_handle_sideload([
                'name' => $filename,
                'type' => $extensionMime,
                'tmp_name' => $temp,
                'error' => 0,
                'size' => $size,
            ], ['test_form' => false]);
            if (!is_array($sideload) || isset($sideload['error'])) {
                throw new \RuntimeException('Portable media could not be stored: ' . (string) ($sideload['error'] ?? 'unknown error'));
            }
            $temp = '';

            $attachment = [
                'post_mime_type' => sanitize_mime_type((string) ($sideload['type'] ?? $extensionMime)),
                'post_title' => sanitize_text_field((string) ($record['title'] ?? pathinfo($filename, PATHINFO_FILENAME))),
                'post_excerpt' => sanitize_textarea_field((string) ($record['caption'] ?? '')),
                'post_content' => wp_kses_post((string) ($record['description'] ?? '')),
                'post_status' => 'inherit',
            ];
            $attachmentId = wp_insert_attachment($attachment, (string) $sideload['file'], 0, true);
            if (is_wp_error($attachmentId)) {
                @unlink((string) $sideload['file']);
                throw new \RuntimeException('Portable attachment could not be created: ' . $attachmentId->get_error_message());
            }

            $attachmentId = (int) $attachmentId;
            $metadata = wp_generate_attachment_metadata($attachmentId, (string) $sideload['file']);
            if (is_array($metadata)) {
                wp_update_attachment_metadata($attachmentId, $metadata);
            }
            update_post_meta($attachmentId, '_wp_attachment_image_alt', sanitize_text_field((string) ($record['alt'] ?? '')));
            update_post_meta($attachmentId, self::MEDIA_SHA_META, $sha);
            self::markPost($attachmentId, $sourceKey, $sourceId, 'media');
            return $attachmentId;
        } finally {
            if ($temp !== '' && is_file($temp)) {
                @unlink($temp);
            }
        }
    }

    /**
     * @param list<array<string,mixed>> $records
     * @param array<int,int> $mediaMap
     * @param array<string,string> $mediaUrlMap
     * @param list<int> $createdPosts
     * @return array<int,int>
     */
    private static function importPages(
        array $records,
        string $sourceKey,
        array $mediaMap,
        array $mediaUrlMap,
        string $sourceHome,
        string $sourceSite,
        array &$createdPosts
    ): array {
        $map = [];
        foreach ($records as $record) {
            $sourceId = (int) $record['sourceId'];
            $rawPath = trim((string) ($record['path'] ?? ''), '/');
            $pathParts = $rawPath === '' ? [] : array_values(array_filter(array_map('sanitize_title', explode('/', $rawPath)), static fn(string $part): bool => $part !== ''));
            $path = implode('/', $pathParts);
            $existing = self::findPostBySource('page', $sourceKey, $sourceId);
            if ($existing <= 0 && $path !== '') {
                $page = get_page_by_path($path, OBJECT, 'page');
                if ($page instanceof \WP_Post) {
                    $existing = (int) $page->ID;
                }
            }

            $args = [
                'post_type' => 'page',
                'post_title' => sanitize_text_field((string) ($record['title'] ?? '')),
                'post_name' => sanitize_title((string) ($record['slug'] ?? '')),
                'post_status' => self::status((string) ($record['status'] ?? 'draft')),
                'menu_order' => (int) ($record['menuOrder'] ?? 0),
                'post_parent' => 0,
                'post_content' => self::remapContent((string) ($record['content'] ?? ''), $mediaMap, $mediaUrlMap, $sourceHome, $sourceSite),
                'post_excerpt' => sanitize_textarea_field((string) ($record['excerpt'] ?? '')),
            ];
            $targetId = self::upsertPost($existing, $args, $createdPosts);
            self::markPost($targetId, $sourceKey, $sourceId, 'page');
            self::featuredImage($targetId, (int) ($record['featuredImageSourceId'] ?? 0), $mediaMap);
            $map[$sourceId] = $targetId;
        }

        foreach ($records as $record) {
            $sourceId = (int) $record['sourceId'];
            $targetId = $map[$sourceId] ?? 0;
            $parentSourceId = (int) ($record['parentSourceId'] ?? 0);
            $parentId = $parentSourceId > 0 ? (int) ($map[$parentSourceId] ?? 0) : 0;
            if ($targetId > 0) {
                $result = wp_update_post(['ID' => $targetId, 'post_parent' => $parentId], true);
                if (is_wp_error($result)) {
                    throw new \RuntimeException('Portable page parent could not be updated: ' . $result->get_error_message());
                }
            }
        }

        return $map;
    }

    /**
     * @param list<array<string,mixed>> $records
     * @param array<int,int> $mediaMap
     * @param array<string,string> $mediaUrlMap
     * @param list<int> $createdPosts
     * @param callable(int,array<string,mixed>):void $repositorySave
     * @return array<int,int>
     */
    private static function importContentType(
        string $postType,
        string $kind,
        array $records,
        string $sourceKey,
        array $mediaMap,
        array $mediaUrlMap,
        string $sourceHome,
        string $sourceSite,
        array &$createdPosts,
        callable $repositorySave
    ): array {
        $map = [];
        foreach ($records as $record) {
            $sourceId = (int) $record['sourceId'];
            $existing = self::findPostBySource($postType, $sourceKey, $sourceId);
            if ($existing <= 0) {
                $slug = sanitize_title((string) ($record['slug'] ?? ''));
                if ($slug !== '') {
                    $post = get_page_by_path($slug, OBJECT, $postType);
                    if ($post instanceof \WP_Post) {
                        $existing = (int) $post->ID;
                    }
                }
            }

            $args = [
                'post_type' => $postType,
                'post_title' => sanitize_text_field((string) ($record['title'] ?? '')),
                'post_name' => sanitize_title((string) ($record['slug'] ?? '')),
                'post_status' => self::status((string) ($record['status'] ?? 'draft')),
                'menu_order' => (int) ($record['menuOrder'] ?? 0),
                'post_content' => self::remapContent((string) ($record['content'] ?? ''), $mediaMap, $mediaUrlMap, $sourceHome, $sourceSite),
            ];
            $targetId = self::upsertPost($existing, $args, $createdPosts);
            self::markPost($targetId, $sourceKey, $sourceId, $kind);
            self::featuredImage($targetId, (int) ($record['featuredImageSourceId'] ?? 0), $mediaMap);
            $repositorySave($targetId, $record);
            $map[$sourceId] = $targetId;
        }
        return $map;
    }

    /**
     * @param list<array<string,mixed>> $records
     * @param array<int,int> $mediaMap
     * @param array<string,string> $mediaUrlMap
     * @param list<int> $createdPosts
     * @return array<int,int>
     */
    private static function importGalleries(
        array $records,
        string $sourceKey,
        array $mediaMap,
        array $mediaUrlMap,
        string $sourceHome,
        string $sourceSite,
        array &$createdPosts
    ): array {
        $map = [];
        foreach ($records as $record) {
            $sourceId = (int) $record['sourceId'];
            $existing = self::findPostBySource(GalleryRepository::POST_TYPE, $sourceKey, $sourceId);
            if ($existing <= 0) {
                $slug = sanitize_title((string) ($record['slug'] ?? ''));
                if ($slug !== '') {
                    $post = get_page_by_path($slug, OBJECT, GalleryRepository::POST_TYPE);
                    if ($post instanceof \WP_Post) {
                        $existing = (int) $post->ID;
                    }
                }
            }

            $args = [
                'post_type' => GalleryRepository::POST_TYPE,
                'post_title' => sanitize_text_field((string) ($record['title'] ?? '')),
                'post_name' => sanitize_title((string) ($record['slug'] ?? '')),
                'post_status' => self::status((string) ($record['status'] ?? 'draft')),
                'menu_order' => (int) ($record['menuOrder'] ?? 0),
                'post_content' => self::remapContent((string) ($record['content'] ?? ''), $mediaMap, $mediaUrlMap, $sourceHome, $sourceSite),
            ];
            $targetId = self::upsertPost($existing, $args, $createdPosts);
            self::markPost($targetId, $sourceKey, $sourceId, 'gallery');
            self::featuredImage($targetId, (int) ($record['featuredImageSourceId'] ?? 0), $mediaMap);

            $imageIds = [];
            foreach (is_array($record['imageIds'] ?? null) ? $record['imageIds'] : [] as $imageSourceId) {
                $targetImageId = (int) ($mediaMap[(int) $imageSourceId] ?? 0);
                if ($targetImageId > 0) {
                    $imageIds[] = $targetImageId;
                }
            }
            $galleryData = $record;
            $galleryData['imageIds'] = $imageIds;
            GalleryRepository::save($targetId, $galleryData);
            $map[$sourceId] = $targetId;
        }
        return $map;
    }

    /** @param array<string,mixed> $args
     *  @param list<int> $createdPosts
     */
    private static function upsertPost(int $existingId, array $args, array &$createdPosts): int
    {
        if ($existingId > 0) {
            $args['ID'] = $existingId;
            $result = wp_update_post($args, true);
        } else {
            $result = wp_insert_post($args, true);
        }
        if (is_wp_error($result)) {
            throw new \RuntimeException('Portable content could not be stored: ' . $result->get_error_message());
        }
        $postId = (int) $result;
        if ($existingId <= 0) {
            $createdPosts[] = $postId;
        }
        return $postId;
    }

    private static function findPostBySource(string $postType, string $sourceKey, int $sourceId): int
    {
        $query = new \WP_Query([
            'post_type' => $postType,
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'meta_query' => [
                'relation' => 'AND',
                ['key' => self::SOURCE_SITE_META, 'value' => $sourceKey],
                ['key' => self::SOURCE_ID_META, 'value' => (string) $sourceId],
            ],
        ]);
        return isset($query->posts[0]) ? (int) $query->posts[0] : 0;
    }

    private static function markPost(int $postId, string $sourceKey, int $sourceId, string $kind): void
    {
        update_post_meta($postId, self::SOURCE_SITE_META, $sourceKey);
        update_post_meta($postId, self::SOURCE_ID_META, $sourceId);
        update_post_meta($postId, self::SOURCE_KIND_META, sanitize_key($kind));
    }

    /** @param array<int,int> $mediaMap */
    private static function featuredImage(int $postId, int $sourceId, array $mediaMap): void
    {
        $targetId = $sourceId > 0 ? (int) ($mediaMap[$sourceId] ?? 0) : 0;
        if ($targetId > 0 && wp_attachment_is_image($targetId)) {
            set_post_thumbnail($postId, $targetId);
        } else {
            delete_post_thumbnail($postId);
        }
    }

    /**
     * @param list<array<string,mixed>> $menus
     * @param array<int,int> $postMap
     * @param list<int> $createdMenuItems
     * @param list<int> $createdMenus
     * @return array<int,int>
     */
    private static function importMenus(
        array $menus,
        string $sourceKey,
        array $postMap,
        string $sourceHome,
        string $sourceSite,
        array &$createdMenuItems,
        array &$createdMenus
    ): array {
        $menuMap = [];

        foreach ($menus as $menu) {
            $sourceId = (int) $menu['sourceId'];
            $name = sanitize_text_field((string) ($menu['name'] ?? ''));
            if ($name === '') {
                $name = 'Imported menu ' . $sourceId;
            }

            $menuId = self::findMenuBySource($sourceKey, $sourceId);
            if ($menuId <= 0) {
                $existing = wp_get_nav_menu_object(sanitize_title((string) ($menu['slug'] ?? '')));
                if (!$existing instanceof \WP_Term) {
                    $existing = wp_get_nav_menu_object($name);
                }
                if ($existing instanceof \WP_Term) {
                    $menuId = (int) $existing->term_id;
                }
            }
            if ($menuId <= 0) {
                $created = wp_create_nav_menu($name);
                if (is_wp_error($created)) {
                    throw new \RuntimeException('Portable menu could not be created: ' . $created->get_error_message());
                }
                $menuId = (int) $created;
                $createdMenus[] = $menuId;
            }

            $updatedTerm = wp_update_term($menuId, 'nav_menu', [
                'name' => $name,
                'description' => sanitize_textarea_field((string) ($menu['description'] ?? '')),
            ]);
            if (is_wp_error($updatedTerm)) {
                throw new \RuntimeException('Portable menu could not be updated: ' . $updatedTerm->get_error_message());
            }
            update_term_meta($menuId, self::SOURCE_SITE_META, $sourceKey);
            update_term_meta($menuId, self::SOURCE_ID_META, $sourceId);
            $menuMap[$sourceId] = $menuId;

            $items = is_array($menu['items'] ?? null) ? array_values(array_filter($menu['items'], 'is_array')) : [];
            $itemMap = [];
            foreach ($items as $item) {
                $itemSourceId = (int) ($item['sourceId'] ?? 0);
                $existingItem = self::findMenuItemBySource($menuId, $sourceKey, $itemSourceId);
                $args = self::menuItemArgs($item, $postMap, $sourceHome, $sourceSite, 0);
                $targetItem = wp_update_nav_menu_item($menuId, $existingItem, $args);
                if (is_wp_error($targetItem)) {
                    throw new \RuntimeException('Portable menu item could not be stored: ' . $targetItem->get_error_message());
                }
                $targetItem = (int) $targetItem;
                if ($existingItem <= 0) {
                    $createdMenuItems[] = $targetItem;
                }
                self::markPost($targetItem, $sourceKey, $itemSourceId, 'menu-item');
                $itemMap[$itemSourceId] = $targetItem;
            }

            foreach ($items as $item) {
                $itemSourceId = (int) ($item['sourceId'] ?? 0);
                $targetItem = (int) ($itemMap[$itemSourceId] ?? 0);
                if ($targetItem <= 0) {
                    continue;
                }
                $parentSourceId = (int) ($item['parentSourceId'] ?? 0);
                $parentTargetId = $parentSourceId > 0 ? (int) ($itemMap[$parentSourceId] ?? 0) : 0;
                $args = self::menuItemArgs($item, $postMap, $sourceHome, $sourceSite, $parentTargetId);
                $result = wp_update_nav_menu_item($menuId, $targetItem, $args);
                if (is_wp_error($result)) {
                    throw new \RuntimeException('Portable menu hierarchy could not be stored: ' . $result->get_error_message());
                }
            }
        }

        return $menuMap;
    }

    private static function findMenuBySource(string $sourceKey, int $sourceId): int
    {
        $terms = get_terms([
            'taxonomy' => 'nav_menu',
            'hide_empty' => false,
            'number' => 1,
            'meta_query' => [
                'relation' => 'AND',
                ['key' => self::SOURCE_SITE_META, 'value' => $sourceKey],
                ['key' => self::SOURCE_ID_META, 'value' => (string) $sourceId],
            ],
        ]);
        if (is_wp_error($terms) || !isset($terms[0]) || !$terms[0] instanceof \WP_Term) {
            return 0;
        }
        return (int) $terms[0]->term_id;
    }

    private static function findMenuItemBySource(int $menuId, string $sourceKey, int $sourceId): int
    {
        $items = wp_get_nav_menu_items($menuId, ['post_status' => 'publish,draft']);
        foreach (is_array($items) ? $items : [] as $item) {
            if (!$item instanceof \WP_Post) {
                continue;
            }
            if ((string) get_post_meta((int) $item->ID, self::SOURCE_SITE_META, true) === $sourceKey
                && (int) get_post_meta((int) $item->ID, self::SOURCE_ID_META, true) === $sourceId
            ) {
                return (int) $item->ID;
            }
        }
        return 0;
    }

    /** @param array<string,mixed> $item
     *  @param array<int,int> $postMap
     *  @return array<string,mixed>
     */
    private static function menuItemArgs(array $item, array $postMap, string $sourceHome, string $sourceSite, int $parentId): array
    {
        $classes = is_array($item['classes'] ?? null) ? array_values(array_filter(array_map('sanitize_html_class', $item['classes']))) : [];
        $args = [
            'menu-item-title' => sanitize_text_field((string) ($item['title'] ?? '')),
            'menu-item-description' => sanitize_textarea_field((string) ($item['description'] ?? '')),
            'menu-item-attr-title' => sanitize_text_field((string) ($item['attrTitle'] ?? '')),
            'menu-item-target' => (string) ($item['target'] ?? '') === '_blank' ? '_blank' : '',
            'menu-item-classes' => implode(' ', $classes),
            'menu-item-xfn' => sanitize_text_field((string) ($item['xfn'] ?? '')),
            'menu-item-position' => max(0, (int) ($item['menuOrder'] ?? 0)),
            'menu-item-parent-id' => max(0, $parentId),
            'menu-item-status' => 'publish',
        ];

        $type = sanitize_key((string) ($item['type'] ?? 'custom'));
        $object = sanitize_key((string) ($item['object'] ?? 'custom'));
        $sourceObjectId = (int) ($item['objectSourceId'] ?? 0);
        $targetObjectId = (int) ($postMap[$sourceObjectId] ?? 0);

        if ($type === 'post_type' && $targetObjectId > 0) {
            $args['menu-item-type'] = 'post_type';
            $args['menu-item-object'] = $object;
            $args['menu-item-object-id'] = $targetObjectId;
        } elseif ($type === 'post_type_archive' && $object !== '') {
            $args['menu-item-type'] = 'post_type_archive';
            $args['menu-item-object'] = $object;
            $args['menu-item-object-id'] = 0;
        } else {
            $args['menu-item-type'] = 'custom';
            $args['menu-item-object'] = 'custom';
            $args['menu-item-object-id'] = 0;
            $args['menu-item-url'] = self::remapUrl((string) ($item['url'] ?? ''), $sourceHome, $sourceSite);
        }

        return $args;
    }

    /**
     * @param list<array<string,mixed>> $records
     * @param array<int,int> $pageMap
     * @param array<int,int> $mediaMap
     * @param array<int,int> $menuMap
     * @param array<string,string> $mediaUrlMap
     */
    private static function applyLayouts(
        array $records,
        array $pageMap,
        array $mediaMap,
        array $menuMap,
        array $mediaUrlMap,
        string $sourceHome,
        string $sourceSite,
        int $userId
    ): void {
        foreach ($records as $record) {
            $sourceId = (int) ($record['sourceId'] ?? 0);
            $targetId = (int) ($pageMap[$sourceId] ?? 0);
            $layout = is_array($record['layout'] ?? null) ? $record['layout'] : [];
            if ($targetId <= 0) {
                throw new \RuntimeException('Portable page layout target is missing.');
            }
            LayoutRepository::save(
                $targetId,
                self::remapDocument($layout, $mediaMap, $menuMap, $mediaUrlMap, $sourceHome, $sourceSite),
                $userId
            );
        }
    }

    /** @param array<string,mixed> $document
     *  @param array<int,int> $mediaMap
     *  @param array<int,int> $menuMap
     *  @param array<string,string> $mediaUrlMap
     *  @return array<string,mixed>
     */
    private static function remapDocument(
        array $document,
        array $mediaMap,
        array $menuMap,
        array $mediaUrlMap,
        string $sourceHome,
        string $sourceSite
    ): array {
        $nodes = is_array($document['nodes'] ?? null) ? $document['nodes'] : [];
        foreach ($nodes as $index => $node) {
            if (!is_array($node)) {
                continue;
            }
            $type = (string) ($node['type'] ?? '');
            $props = is_array($node['props'] ?? null) ? $node['props'] : [];
            if ($type === NodeSchema::IMAGE) {
                $sourceId = (int) ($props['attachmentId'] ?? 0);
                $props['attachmentId'] = $sourceId > 0 ? (int) ($mediaMap[$sourceId] ?? 0) : 0;
            } elseif ($type === NodeSchema::NAVIGATION) {
                $sourceId = (int) ($props['menuId'] ?? 0);
                $props['menuId'] = $sourceId > 0 ? (int) ($menuMap[$sourceId] ?? 0) : 0;
            } elseif ($type === NodeSchema::TEXT) {
                $props['content'] = self::remapContent((string) ($props['content'] ?? ''), $mediaMap, $mediaUrlMap, $sourceHome, $sourceSite);
            } elseif ($type === NodeSchema::BUTTON) {
                $props['url'] = self::remapUrl((string) ($props['url'] ?? ''), $sourceHome, $sourceSite);
            }
            $node['props'] = $props;
            $nodes[$index] = $node;
        }
        $document['nodes'] = $nodes;
        return LayoutDocument::normalize($document);
    }

    /** @param array<int,int> $mediaMap
     *  @param array<string,string> $mediaUrlMap
     */
    private static function remapContent(string $content, array $mediaMap, array $mediaUrlMap, string $sourceHome, string $sourceSite): string
    {
        foreach ($mediaMap as $sourceId => $targetId) {
            $content = preg_replace('/\bwp-image-' . preg_quote((string) $sourceId, '/') . '\b/', 'wp-image-' . $targetId, $content) ?? $content;
        }
        foreach ($mediaUrlMap as $sourceUrl => $targetUrl) {
            if ($sourceUrl !== '') {
                $content = str_replace($sourceUrl, $targetUrl, $content);
            }
        }
        $content = self::remapInternalUrls($content, $sourceHome, $sourceSite);
        return wp_kses_post($content);
    }

    private static function remapUrl(string $url, string $sourceHome, string $sourceSite): string
    {
        $url = self::remapInternalUrls($url, $sourceHome, $sourceSite);
        return esc_url_raw($url);
    }

    private static function remapInternalUrls(string $value, string $sourceHome, string $sourceSite): string
    {
        $pairs = [];
        foreach ([[$sourceHome, home_url('/')], [$sourceSite, site_url('/')]] as [$source, $target]) {
            $source = rtrim((string) $source, '/');
            $target = rtrim((string) $target, '/');
            if ($source !== '' && $target !== '' && $source !== $target) {
                $pairs[$source] = $target;
            }
        }
        if ($pairs !== []) {
            $value = str_replace(array_keys($pairs), array_values($pairs), $value);
        }
        return $value;
    }

    /** @param array<string,mixed> $site
     *  @param array<int,int> $pageMap
     *  @param array<int,int> $mediaMap
     */
    private static function applySiteSettings(array $site, array $pageMap, array $mediaMap): void
    {
        $settings = is_array($site['settings'] ?? null) ? $site['settings'] : [];
        $identity = is_array($settings['siteIdentity'] ?? null) ? $settings['siteIdentity'] : [];
        SiteSettingsRepository::save([
            'siteTitle' => (string) ($identity['siteTitle'] ?? ''),
            'tagline' => (string) ($identity['tagline'] ?? ''),
            'organizationName' => (string) ($identity['organizationName'] ?? ''),
            'contactEmail' => (string) ($identity['contactEmail'] ?? ''),
            'contactPhone' => (string) ($identity['contactPhone'] ?? ''),
            'logoId' => (int) ($mediaMap[(int) ($identity['logoSourceId'] ?? 0)] ?? 0),
            'siteIconId' => (int) ($mediaMap[(int) ($identity['siteIconSourceId'] ?? 0)] ?? 0),
        ]);

        $reading = is_array($settings['reading'] ?? null) ? $settings['reading'] : [];
        $showOnFront = (string) ($reading['showOnFront'] ?? 'posts');
        $showOnFront = in_array($showOnFront, ['posts', 'page'], true) ? $showOnFront : 'posts';
        $frontSourceId = (int) ($reading['frontPageSourceId'] ?? 0);
        $postsSourceId = (int) ($reading['postsPageSourceId'] ?? 0);
        update_option('show_on_front', $showOnFront, true);
        update_option('page_on_front', $frontSourceId > 0 ? (int) ($pageMap[$frontSourceId] ?? 0) : 0, true);
        update_option('page_for_posts', $postsSourceId > 0 ? (int) ($pageMap[$postsSourceId] ?? 0) : 0, true);

        $permalink = (string) ($settings['permalinkStructure'] ?? '');
        if (strlen($permalink) <= 255 && !str_contains($permalink, '<') && !str_contains($permalink, '>')) {
            update_option('permalink_structure', $permalink, true);
        }
    }

    private static function status(string $status): string
    {
        $status = sanitize_key($status);
        return in_array($status, ['publish', 'draft', 'private', 'pending'], true) ? $status : 'draft';
    }

    /** @param list<int> $createdMenuItems
     *  @param list<int> $createdMenus
     *  @param list<int> $createdPosts
     */
    private static function rollbackCreated(array $createdMenuItems, array $createdMenus, array $createdPosts): void
    {
        foreach (array_reverse(array_values(array_unique($createdMenuItems))) as $postId) {
            wp_delete_post((int) $postId, true);
        }
        foreach (array_reverse(array_values(array_unique($createdMenus))) as $menuId) {
            wp_delete_nav_menu((int) $menuId);
        }
        foreach (array_reverse(array_values(array_unique($createdPosts))) as $postId) {
            wp_delete_post((int) $postId, true);
        }
    }

    private function __construct()
    {
    }
}
