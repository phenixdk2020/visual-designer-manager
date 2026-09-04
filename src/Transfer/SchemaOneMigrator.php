<?php

declare(strict_types=1);

namespace VisualDesignerManager\Transfer;

use VisualDesignerManager\Model\NodeSchema;
use VisualDesignerManager\Storage\SiteDesignRepository;

final class SchemaOneMigrator
{
    private const SCHEMA = '1.0';
    private const REQUIRED = [
        'site.json',
        'pages/pages.json',
        'templates/templates.json',
        'modules/modules.json',
        'modules/custom-fields.json',
        'navigation/navigation.json',
        'media/media-index.json',
        'migration/legacy-map.json',
    ];

    /** @return array{path:string,inspection:array<string,mixed>,migration:array<string,mixed>} */
    public static function convert(string $sourceZip): array
    {
        $legacy = self::inspect($sourceZip);
        $zip = new \ZipArchive();
        if ($zip->open($sourceZip, \ZipArchive::RDONLY) !== true) {
            throw new \RuntimeException('Schema 1.0 package could not be reopened for conversion.');
        }

        $tempFiles = [];
        $target = '';
        try {
            $site = self::readJson($zip, 'site.json');
            $pagesPayload = self::readJson($zip, 'pages/pages.json');
            $templatesPayload = self::readJson($zip, 'templates/templates.json');
            $modulesPayload = self::readJson($zip, 'modules/modules.json');
            $customFieldsPayload = self::readJson($zip, 'modules/custom-fields.json');
            $navigationPayload = self::readJson($zip, 'navigation/navigation.json');
            $mediaPayload = self::readJson($zip, 'media/media-index.json');

            $recovery = LegacyMediaRecovery::recover(
                $site,
                $pagesPayload,
                $templatesPayload,
                (array) ($mediaPayload['records'] ?? [])
            );
            $pagesPayload = is_array($recovery['pagesPayload'] ?? null) ? $recovery['pagesPayload'] : $pagesPayload;
            $templatesPayload = is_array($recovery['templatesPayload'] ?? null) ? $recovery['templatesPayload'] : $templatesPayload;
            foreach ((array) ($recovery['tempFiles'] ?? []) as $recoveryTemp) {
                if (is_string($recoveryTemp) && $recoveryTemp !== '') {
                    $tempFiles[] = $recoveryTemp;
                }
            }

            $warnings = is_array($legacy['warnings'] ?? null) ? $legacy['warnings'] : [];
            $warnings = array_merge($warnings, (array) ($recovery['warnings'] ?? []));
            $catalog = self::moduleCatalog($modulesPayload);
            $pageRecords = (array) ($pagesPayload['records'] ?? []);
            $pageLinks = self::pageLinkMap($pageRecords, $site);
            $pages = self::convertPages($pageRecords, $catalog, $warnings, $pageLinks);
            [$events, $vehicles, $galleries] = self::convertModules((array) ($modulesPayload['records'] ?? []), $warnings);
            $menus = self::convertMenus((array) ($navigationPayload['menus'] ?? []));
            $header = self::convertTemplate($templatesPayload, 'header', $warnings, $pageLinks);
            $footer = self::convertTemplate($templatesPayload, 'footer', $warnings, $pageLinks);
            $media = self::convertMedia((array) ($mediaPayload['records'] ?? []), (array) ($legacy['manifestFiles'] ?? []), $warnings);
            foreach ((array) ($recovery['items'] ?? []) as $recoveredRecord) {
                if (is_array($recoveredRecord)) {
                    $media[] = $recoveredRecord;
                }
            }
            $recoveredTempFiles = is_array($recovery['tempFiles'] ?? null) ? $recovery['tempFiles'] : [];
            $nativeSite = self::convertSite($site);
            $siteDesign = self::convertSiteDesign($templatesPayload);
            $customFields = self::convertFieldDefinitions($customFieldsPayload);

            $payloads = [
                'site.json' => PortablePackage::json($nativeSite),
                'content/pages.json' => PortablePackage::json(['items' => $pages]),
                'content/events.json' => PortablePackage::json(['items' => $events]),
                'content/vehicles.json' => PortablePackage::json(['items' => $vehicles]),
                'content/galleries.json' => PortablePackage::json(['items' => $galleries]),
                'content/menus.json' => PortablePackage::json(['items' => $menus]),
                'templates/header.json' => PortablePackage::json(['document' => $header]),
                'templates/footer.json' => PortablePackage::json(['document' => $footer]),
                'settings/site-design.json' => PortablePackage::json(['settings' => $siteDesign]),
                'settings/custom-fields.json' => PortablePackage::json($customFields),
                'media/index.json' => PortablePackage::json(['items' => $media]),
            ];

            $temp = wp_tempnam('vdm-schema-one-converted.zip');
            if (!is_string($temp) || $temp === '') {
                throw new \RuntimeException('A temporary path could not be created for schema 1.0 conversion.');
            }
            @unlink($temp);
            $target = $temp . '.zip';
            $out = new \ZipArchive();
            if ($out->open($target, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Converted native V2 ZIP could not be created.');
            }

            $files = [];
            try {
                foreach ($payloads as $path => $content) {
                    if (!$out->addFromString($path, $content)) {
                        throw new \RuntimeException('Converted JSON could not be added: ' . $path);
                    }
                    $files[] = ['path' => $path, 'size' => strlen($content), 'sha256' => hash('sha256', $content)];
                }
                foreach ($media as $record) {
                    $archive = (string) ($record['archivePath'] ?? '');
                    if (isset($recoveredTempFiles[$archive])) {
                        $sourceTemp = (string) $recoveredTempFiles[$archive];
                        if ($sourceTemp === '' || !is_file($sourceTemp) || !is_readable($sourceTemp)) {
                            throw new \RuntimeException('Recovered media temporary file is unavailable: ' . $archive);
                        }
                        $actualSize = filesize($sourceTemp);
                        $actualSha = hash_file('sha256', $sourceTemp);
                        if (!is_int($actualSize)
                            || $actualSize !== (int) ($record['size'] ?? -1)
                            || !is_string($actualSha)
                            || !hash_equals(strtolower((string) ($record['sha256'] ?? '')), strtolower($actualSha))
                        ) {
                            throw new \RuntimeException('Recovered media changed before package assembly: ' . $archive);
                        }
                    } else {
                        $sourceTemp = self::copyEntryToTemp($zip, $archive, (int) ($record['size'] ?? 0), (string) ($record['sha256'] ?? ''));
                        $tempFiles[] = $sourceTemp;
                    }
                    if (!$out->addFile($sourceTemp, $archive)) {
                        throw new \RuntimeException('Converted media could not be added: ' . $archive);
                    }
                    $files[] = [
                        'path' => $archive,
                        'size' => (int) ($record['size'] ?? 0),
                        'sha256' => (string) ($record['sha256'] ?? ''),
                    ];
                }

                $source = is_array($nativeSite['source'] ?? null) ? $nativeSite['source'] : [];
                $manifest = [
                    'format' => PortablePackage::FORMAT,
                    'schemaVersion' => PortablePackage::SCHEMA_VERSION,
                    'managerVersion' => VDM_VERSION,
                    'createdAt' => gmdate('c'),
                    'source' => [
                        'homeUrl' => (string) ($source['homeUrl'] ?? ''),
                        'siteUrl' => (string) ($source['siteUrl'] ?? ''),
                        'name' => (string) ($source['name'] ?? ''),
                    ],
                    'files' => $files,
                    'contentSha256' => PortablePackage::contentHash($files),
                    'migration' => [
                        'sourceSchemaVersion' => self::SCHEMA,
                        'sourceManagerVersion' => (string) ($legacy['managerVersion'] ?? ''),
                    ],
                ];
                if (!$out->addFromString('manifest.json', PortablePackage::json($manifest))) {
                    throw new \RuntimeException('Converted manifest could not be added.');
                }
            } catch (\Throwable $exception) {
                $out->close();
                throw $exception;
            }
            if (!$out->close()) {
                throw new \RuntimeException('Converted native V2 ZIP could not be finalized.');
            }

            $inspection = PortablePackage::inspect($target);
            return [
                'path' => $target,
                'inspection' => $inspection,
                'migration' => [
                    'sourceSchemaVersion' => self::SCHEMA,
                    'sourceManagerVersion' => (string) ($legacy['managerVersion'] ?? ''),
                    'warnings' => array_values(array_unique(array_map('strval', $warnings))),
                    'recoveredMedia' => max(0, (int) ($recovery['recovered'] ?? 0)),
                    'unresolvedMedia' => max(0, (int) ($recovery['unresolved'] ?? 0)),
                    'countsBefore' => is_array($legacy['counts'] ?? null) ? $legacy['counts'] : [],
                    'countsAfter' => is_array($inspection['summary'] ?? null) ? $inspection['summary'] : [],
                ],
            ];
        } catch (\Throwable $exception) {
            if ($target !== '' && is_file($target)) {
                @unlink($target);
            }
            throw $exception;
        } finally {
            $zip->close();
            foreach ($tempFiles as $file) {
                if (is_string($file) && is_file($file)) {
                    @unlink($file);
                }
            }
        }
    }

    /** @return array<string,mixed> */
    public static function inspect(string $zipPath): array
    {
        if (!class_exists(\ZipArchive::class) || !is_file($zipPath) || !is_readable($zipPath)) {
            throw new \RuntimeException('Schema 1.0 ZIP is not readable.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::RDONLY) !== true) {
            throw new \RuntimeException('Schema 1.0 ZIP could not be opened.');
        }
        try {
            if ($zip->numFiles <= 0 || $zip->numFiles > PortablePackage::MAX_FILES + 32) {
                throw new \RuntimeException('Schema 1.0 ZIP contains an invalid number of entries.');
            }
            $actual = [];
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i, \ZipArchive::FL_UNCHANGED);
                if (!is_array($stat)) {
                    throw new \RuntimeException('Schema 1.0 ZIP entry metadata could not be read.');
                }
                $name = (string) ($stat['name'] ?? '');
                if (str_ends_with($name, '/')) {
                    continue;
                }
                if (!PortablePackage::safePath($name)) {
                    throw new \RuntimeException('Unsafe schema 1.0 ZIP path rejected: ' . $name);
                }
                $key = strtolower($name);
                if (isset($actual[$key])) {
                    throw new \RuntimeException('Duplicate schema 1.0 ZIP path rejected: ' . $name);
                }
                $size = max(0, (int) ($stat['size'] ?? 0));
                if ($size > PortablePackage::MAX_ENTRY_BYTES) {
                    throw new \RuntimeException('Schema 1.0 ZIP entry is too large: ' . $name);
                }
                $total += $size;
                if ($total > PortablePackage::MAX_TOTAL_BYTES) {
                    throw new \RuntimeException('Schema 1.0 ZIP exceeds the allowed uncompressed size.');
                }
                if (method_exists($zip, 'getExternalAttributesIndex')) {
                    $opsys = 0;
                    $attributes = 0;
                    if ($zip->getExternalAttributesIndex($i, $opsys, $attributes) && ((($attributes >> 16) & 0xF000) === 0xA000)) {
                        throw new \RuntimeException('Symbolic links are not allowed in schema 1.0 packages.');
                    }
                }
                $actual[$key] = ['path' => $name, 'size' => $size];
            }

            $manifest = self::readJson($zip, 'manifest.json', 2097152);
            if ((string) ($manifest['format'] ?? '') !== PortablePackage::FORMAT
                || (string) ($manifest['schemaVersion'] ?? '') !== self::SCHEMA
                || (string) ($manifest['exportType'] ?? '') !== 'site'
            ) {
                throw new \RuntimeException('ZIP is not a supported schema 1.0 site package.');
            }
            $files = is_array($manifest['files'] ?? null) ? $manifest['files'] : [];
            if ($files === [] || count($files) > PortablePackage::MAX_FILES) {
                throw new \RuntimeException('Schema 1.0 manifest file list is invalid.');
            }
            $listed = [];
            $manifestMap = [];
            foreach ($files as $file) {
                if (!is_array($file)) {
                    throw new \RuntimeException('Schema 1.0 manifest contains an invalid file record.');
                }
                $path = (string) ($file['path'] ?? '');
                $size = (int) ($file['size'] ?? -1);
                $sha = strtolower((string) ($file['sha256'] ?? ''));
                if (!PortablePackage::safePath($path) || $path === 'manifest.json' || $size < 0 || preg_match('/^[a-f0-9]{64}$/', $sha) !== 1) {
                    throw new \RuntimeException('Schema 1.0 manifest contains an unsafe file record.');
                }
                $key = strtolower($path);
                if (isset($listed[$key]) || !isset($actual[$key])) {
                    throw new \RuntimeException('Schema 1.0 manifest path is duplicated or missing: ' . $path);
                }
                if ((int) $actual[$key]['size'] !== $size) {
                    throw new \RuntimeException('Schema 1.0 size mismatch: ' . $path);
                }
                $calculated = self::hashEntry($zip, $path, $size);
                if (!hash_equals($sha, $calculated)) {
                    throw new \RuntimeException('Schema 1.0 SHA-256 mismatch: ' . $path);
                }
                $listed[$key] = true;
                $manifestMap[$path] = ['path' => $path, 'size' => $size, 'sha256' => $sha];
            }
            foreach ($actual as $key => $record) {
                if ($key !== 'manifest.json' && !isset($listed[$key])) {
                    throw new \RuntimeException('Unlisted schema 1.0 ZIP entry rejected: ' . (string) $record['path']);
                }
            }
            foreach (self::REQUIRED as $required) {
                if (!isset($listed[strtolower($required)])) {
                    throw new \RuntimeException('Schema 1.0 package is missing required file: ' . $required);
                }
            }
            $sorted = array_values($manifestMap);
            usort($sorted, static fn(array $a, array $b): int => strcmp($a['path'], $b['path']));
            $digestJson = wp_json_encode($sorted, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $expectedDigest = strtolower((string) ($manifest['contentSha256'] ?? ''));
            $digest = is_string($digestJson) ? hash('sha256', $digestJson) : '';
            if ($digest === '' || preg_match('/^[a-f0-9]{64}$/', $expectedDigest) !== 1 || !hash_equals($expectedDigest, $digest)) {
                throw new \RuntimeException('Schema 1.0 content digest is invalid.');
            }

            $pages = self::readJson($zip, 'pages/pages.json');
            $templates = self::readJson($zip, 'templates/templates.json');
            $modules = self::readJson($zip, 'modules/modules.json');
            $navigation = self::readJson($zip, 'navigation/navigation.json');
            $media = self::readJson($zip, 'media/media-index.json');
            return [
                'schemaVersion' => self::SCHEMA,
                'managerVersion' => (string) ($manifest['managerVersion'] ?? ''),
                'sourceSite' => (string) ($manifest['sourceSite'] ?? ''),
                'counts' => [
                    'pages' => count((array) ($pages['records'] ?? [])),
                    'templates' => count((array) ($templates['records'] ?? [])),
                    'modules' => count((array) ($modules['records'] ?? [])),
                    'menus' => count((array) ($navigation['menus'] ?? [])),
                    'media' => count((array) ($media['records'] ?? [])),
                ],
                'warnings' => [],
                'manifestFiles' => $manifestMap,
            ];
        } finally {
            $zip->close();
        }
    }

    /** @param array<string,mixed> $templates @return array<string,mixed> */
    private static function convertSiteDesign(array $templates): array
    {
        $design = SiteDesignRepository::defaults();
        $design['shellEnabled'] = true;
        $design['contentPadding'] = 0;
        foreach ((array) ($templates['records'] ?? []) as $record) {
            if (!is_array($record) || empty($record['active'])) {
                continue;
            }
            $settings = is_array($record['settings'] ?? null) ? $record['settings'] : [];
            $width = (int) ($settings['contentWidth'] ?? 0);
            if ($width >= 640 && $width <= 2400) {
                $design['maxWidth'] = $width;
                break;
            }
        }
        return SiteDesignRepository::normalize($design);
    }

    /** @param array<string,mixed> $payload @return array{vehicleFields:list<array<string,mixed>>,eventFields:list<array<string,mixed>>} */
    private static function convertFieldDefinitions(array $payload): array
    {
        $vehicles = [];
        foreach ((array) ($payload['vehicleFields'] ?? []) as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = sanitize_key((string) ($row['id'] ?? ''));
            $label = sanitize_text_field((string) ($row['label'] ?? ''));
            if ($id === '' || $label === '') {
                continue;
            }
            $vehicles[] = [
                'id' => $id,
                'label' => $label,
                'type' => sanitize_key((string) ($row['type'] ?? 'text')),
                'unit' => sanitize_text_field((string) ($row['unit'] ?? '')),
                'enabled' => array_key_exists('enabled', $row) ? (bool) $row['enabled'] : true,
                'order' => (int) ($row['order'] ?? (($index + 1) * 10)),
            ];
        }
        $events = [];
        foreach ((array) ($payload['eventFields'] ?? []) as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = sanitize_key((string) ($row['id'] ?? ''));
            $label = sanitize_text_field((string) ($row['label'] ?? ''));
            if ($id === '' || $label === '') {
                continue;
            }
            $events[] = [
                'id' => $id,
                'label' => $label,
                'type' => sanitize_key((string) ($row['type'] ?? 'text')),
                'enabled' => array_key_exists('enabled', $row) ? (bool) $row['enabled'] : true,
                'required' => !empty($row['required']),
                'showCard' => !empty($row['showCard']),
                'showDetail' => array_key_exists('showDetail', $row) ? (bool) $row['showDetail'] : true,
                'order' => (int) ($row['order'] ?? (($index + 1) * 10)),
            ];
        }
        return ['vehicleFields' => $vehicles, 'eventFields' => $events];
    }

    /** @param list<array<string,mixed>> $attributes @return array<string,string> */
    private static function attributeValues(array $attributes): array
    {
        $values = [];
        foreach ($attributes as $attribute) {
            if (!is_array($attribute) || empty($attribute['enabled'])) {
                continue;
            }
            $key = sanitize_key((string) ($attribute['key'] ?? ''));
            $value = wp_kses_post((string) ($attribute['value'] ?? ''));
            if ($key !== '' && $value !== '') {
                $values[$key] = $value;
            }
        }
        return $values;
    }

    /** @return array<string,mixed> */
    private static function convertSite(array $site): array
    {
        $source = is_array($site['source'] ?? null) ? $site['source'] : [];
        $settings = is_array($site['settings'] ?? null) ? $site['settings'] : [];
        $identity = is_array($settings['siteIdentity'] ?? null) ? $settings['siteIdentity'] : [];
        return [
            'source' => [
                'homeUrl' => esc_url_raw((string) ($source['homeUrl'] ?? '')),
                'siteUrl' => esc_url_raw((string) ($source['siteUrl'] ?? '')),
                'name' => sanitize_text_field((string) ($source['name'] ?? '')),
            ],
            'settings' => [
                'siteIdentity' => [
                    'siteTitle' => sanitize_text_field((string) ($identity['siteTitle'] ?? '')),
                    'tagline' => sanitize_text_field((string) ($identity['tagline'] ?? '')),
                    'organizationName' => sanitize_text_field((string) ($identity['organizationName'] ?? '')),
                    'contactEmail' => sanitize_email((string) ($identity['contactEmail'] ?? '')),
                    'contactPhone' => sanitize_text_field((string) ($identity['contactPhone'] ?? '')),
                    'logoSourceId' => absint($identity['customLogoSourceId'] ?? 0),
                    'siteIconSourceId' => absint($identity['siteIconSourceId'] ?? 0),
                ],
                'reading' => [
                    'showOnFront' => (string) ($settings['showOnFront'] ?? 'posts') === 'page' ? 'page' : 'posts',
                    'frontPageSourceId' => absint($settings['pageOnFrontSourceId'] ?? 0),
                    'postsPageSourceId' => absint($settings['pageForPostsSourceId'] ?? 0),
                ],
                'permalinkStructure' => sanitize_text_field((string) ($settings['permalinkStructure'] ?? '')),
            ],
        ];
    }

    /** @param list<array<string,mixed>> $records
     *  @param array<string,string> $catalog
     *  @param list<string> $warnings
     *  @return list<array<string,mixed>>
     */
    private static function convertPages(array $records, array $catalog, array &$warnings, array $pageLinks): array
    {
        $items = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }
            $sourceId = absint($record['sourceId'] ?? 0);
            if ($sourceId <= 0) {
                continue;
            }
            $layout = self::emptyDocument();
            $designer = is_array($record['visualDesigner'] ?? null) ? $record['visualDesigner'] : null;
            if ($designer !== null && is_array($designer['model'] ?? null)) {
                $model = $designer['model'];
                $legacyTypes = [];
                foreach ((array) ($model['nodes'] ?? []) as $node) {
                    if (is_array($node)) {
                        $legacyTypes[] = sanitize_key((string) ($node['type'] ?? ''));
                    }
                }
                if (array_intersect($legacyTypes, ['eventfacts', 'eventfield', 'eventimage', 'eventvalue', 'gallerydetail', 'vehicledetail']) !== []) {
                    $warnings[] = 'A previous-generation detail page was omitted because V2 renders detail views natively: ' . (string) ($record['title'] ?? $sourceId);
                    continue;
                }
                $model = self::canonicalizeLegacyLinks($model, $pageLinks);
                $layout = self::convertDocument($model, $warnings);
                $module = self::classifyModulePage($record, $catalog);
                if ($module !== '') {
                    self::injectModuleNode($layout, $module);
                }
            }
            $items[] = [
                'sourceId' => $sourceId,
                'title' => sanitize_text_field((string) ($record['title'] ?? '')),
                'slug' => sanitize_title((string) ($record['slug'] ?? '')),
                'path' => sanitize_text_field((string) ($record['path'] ?? '')),
                'parentSourceId' => absint($record['parentSourceId'] ?? 0),
                'status' => self::status((string) ($record['status'] ?? 'draft')),
                'menuOrder' => (int) ($record['menuOrder'] ?? 0),
                'content' => wp_kses_post((string) ($record['content'] ?? '')),
                'excerpt' => wp_kses_post((string) ($record['excerpt'] ?? '')),
                'featuredImageSourceId' => absint($record['featuredImageSourceId'] ?? 0),
                'layout' => $layout,
            ];
        }
        return $items;
    }

    /** @param list<array<string,mixed>> $records
     *  @param list<string> $warnings
     *  @return array{0:list<array<string,mixed>>,1:list<array<string,mixed>>,2:list<array<string,mixed>>}
     */
    private static function convertModules(array $records, array &$warnings): array
    {
        $events = [];
        $vehicles = [];
        $galleries = [];
        foreach ($records as $row) {
            if (!is_array($row) || !is_array($row['record'] ?? null)) {
                continue;
            }
            $kind = sanitize_key((string) ($row['module'] ?? ''));
            $record = $row['record'];
            $sourceId = absint($row['sourcePostId'] ?? 0);
            if ($sourceId <= 0) {
                $warnings[] = 'A previous-generation module record without a source post ID was omitted.';
                continue;
            }
            if ($kind === 'events') {
                $fields = is_array($record['fields'] ?? null) ? $record['fields'] : [];
                [$date, $startTime] = self::dateTimeParts((string) ($fields['start'] ?? ''));
                [, $endTime] = self::dateTimeParts((string) ($fields['end'] ?? ''));
                $events[] = [
                    'sourceId' => $sourceId,
                    'title' => sanitize_text_field((string) ($record['title'] ?? '')),
                    'slug' => sanitize_title((string) ($record['slug'] ?? '')),
                    'status' => self::status((string) ($record['status'] ?? 'draft')),
                    'menuOrder' => (int) ($record['sortOrder'] ?? 0),
                    'featuredImageSourceId' => absint($record['featuredMediaId'] ?? 0),
                    'startDate' => $date,
                    'startTime' => $startTime,
                    'endTime' => $endTime,
                    'location' => sanitize_text_field((string) ($fields['location'] ?? '')),
                    'address' => sanitize_text_field((string) ($fields['address'] ?? '')),
                    'contact' => sanitize_text_field((string) ($fields['contact'] ?? '')),
                    'summary' => sanitize_textarea_field((string) ($record['summary'] ?? '')),
                    'content' => self::moduleContent((string) ($fields['description'] ?? ''), (array) ($record['attributes'] ?? [])),
                    'customFields' => self::attributeValues((array) ($record['attributes'] ?? [])),
                ];
            } elseif ($kind === 'vehicles') {
                $fields = is_array($record['fields'] ?? null) ? $record['fields'] : [];
                [$fixed, $extra] = self::vehicleAttributes((array) ($record['attributes'] ?? []));
                $vehicles[] = array_merge([
                    'sourceId' => $sourceId,
                    'title' => sanitize_text_field((string) ($record['title'] ?? '')),
                    'slug' => sanitize_title((string) ($record['slug'] ?? '')),
                    'status' => self::status((string) ($record['status'] ?? 'draft')),
                    'menuOrder' => (int) ($record['sortOrder'] ?? 0),
                    'featuredImageSourceId' => absint($record['featuredMediaId'] ?? 0),
                    'type' => sanitize_text_field((string) ($fields['category'] ?? '')),
                    'summary' => sanitize_textarea_field((string) ($record['summary'] ?? '')),
                    'content' => wp_kses_post((string) ($fields['description'] ?? '')),
                    'specs' => $extra,
                    'customFields' => self::attributeValues((array) ($record['attributes'] ?? [])),
                ], $fixed);
            } elseif ($kind === 'galleries') {
                $fields = is_array($record['fields'] ?? null) ? $record['fields'] : [];
                $galleries[] = [
                    'sourceId' => $sourceId,
                    'title' => sanitize_text_field((string) ($record['title'] ?? '')),
                    'slug' => sanitize_title((string) ($record['slug'] ?? '')),
                    'status' => self::status((string) ($record['status'] ?? 'draft')),
                    'menuOrder' => (int) ($record['sortOrder'] ?? 0),
                    'featuredImageSourceId' => absint($record['featuredMediaId'] ?? 0),
                    'summary' => sanitize_textarea_field((string) ($record['summary'] ?? '')),
                    'content' => wp_kses_post((string) ($fields['description'] ?? '')),
                    'imageIds' => array_values(array_filter(array_map('absint', (array) ($fields['imageIds'] ?? [])))),
                ];
            }
        }
        return [$events, $vehicles, $galleries];
    }

    /** @param list<array<string,mixed>> $records
     *  @return list<array<string,mixed>>
     */
    private static function convertMenus(array $records): array
    {
        $menus = [];
        foreach ($records as $menu) {
            if (!is_array($menu) || absint($menu['sourceId'] ?? 0) <= 0) {
                continue;
            }
            $items = [];
            foreach ((array) ($menu['items'] ?? []) as $item) {
                if (!is_array($item) || absint($item['sourceId'] ?? 0) <= 0) {
                    continue;
                }
                $items[] = [
                    'sourceId' => absint($item['sourceId']),
                    'parentSourceId' => absint($item['parentSourceId'] ?? 0),
                    'title' => sanitize_text_field((string) ($item['title'] ?? '')),
                    'url' => esc_url_raw((string) ($item['url'] ?? '')),
                    'target' => (string) ($item['target'] ?? '') === '_blank' ? '_blank' : '',
                    'attrTitle' => sanitize_text_field((string) ($item['attrTitle'] ?? '')),
                    'description' => sanitize_textarea_field((string) ($item['description'] ?? '')),
                    'classes' => array_values(array_filter(array_map('sanitize_html_class', (array) ($item['classes'] ?? [])))),
                    'xfn' => sanitize_text_field((string) ($item['xfn'] ?? '')),
                    'type' => sanitize_key((string) ($item['type'] ?? 'custom')),
                    'object' => sanitize_key((string) ($item['object'] ?? 'custom')),
                    'objectSourceId' => absint($item['objectId'] ?? 0),
                    'menuOrder' => (int) ($item['order'] ?? 0),
                ];
            }
            $menus[] = [
                'sourceId' => absint($menu['sourceId']),
                'name' => sanitize_text_field((string) ($menu['name'] ?? '')),
                'slug' => sanitize_title((string) ($menu['slug'] ?? '')),
                'description' => '',
                'items' => $items,
            ];
        }
        return $menus;
    }

    /** @param array<string,mixed> $payload
     *  @param list<string> $warnings
     *  @return array<string,mixed>
     */
    private static function convertTemplate(array $payload, string $type, array &$warnings, array $pageLinks): array
    {
        $defaults = is_array($payload['defaults'] ?? null) ? $payload['defaults'] : [];
        $preferred = sanitize_key((string) ($defaults[$type] ?? ''));
        $candidate = null;
        foreach ((array) ($payload['records'] ?? []) as $record) {
            if (!is_array($record) || sanitize_key((string) ($record['type'] ?? '')) !== $type) {
                continue;
            }
            if ($preferred !== '' && sanitize_key((string) ($record['sourceId'] ?? '')) === $preferred) {
                $candidate = $record;
                break;
            }
            if ($candidate === null || !empty($record['active'])) {
                $candidate = $record;
            }
        }
        if (!is_array($candidate) || !is_array($candidate['model'] ?? null)) {
            $warnings[] = 'No previous-generation ' . $type . ' template could be converted.';
            return self::emptyDocument();
        }
        return self::convertDocument(self::canonicalizeLegacyLinks($candidate['model'], $pageLinks), $warnings);
    }

    /** @param list<array<string,mixed>> $records
     *  @param array<string,array{path:string,size:int,sha256:string}> $manifestFiles
     *  @param list<string> $warnings
     *  @return list<array<string,mixed>>
     */
    private static function convertMedia(array $records, array $manifestFiles, array &$warnings): array
    {
        $items = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }
            $sourceId = absint($record['sourceId'] ?? 0);
            $archive = (string) ($record['archiveFile'] ?? '');
            if ($sourceId <= 0 || $archive === '' || !PortablePackage::safePath($archive)) {
                if ($sourceId > 0) {
                    $warnings[] = 'Media #' . $sourceId . ' has no packaged original and could not be migrated.';
                }
                continue;
            }
            $manifest = $manifestFiles[$archive] ?? null;
            if (!is_array($manifest)) {
                throw new \RuntimeException('Schema 1.0 media file is missing from the manifest: ' . $archive);
            }
            $filename = sanitize_file_name(wp_basename((string) ($record['attachedFile'] ?? $archive)));
            if ($filename === '') {
                $filename = sanitize_file_name(wp_basename($archive));
            }
            $items[] = [
                'sourceId' => $sourceId,
                'archivePath' => $archive,
                'filename' => $filename,
                'mimeType' => sanitize_mime_type((string) ($record['mimeType'] ?? '')),
                'title' => sanitize_text_field((string) ($record['title'] ?? '')),
                'caption' => wp_kses_post((string) ($record['caption'] ?? '')),
                'description' => wp_kses_post((string) ($record['description'] ?? '')),
                'alt' => sanitize_text_field((string) ($record['alt'] ?? '')),
                'sourceUrl' => esc_url_raw((string) ($record['sourceUrl'] ?? '')),
                'size' => (int) $manifest['size'],
                'sha256' => (string) $manifest['sha256'],
            ];
        }
        return $items;
    }

    /** @param array<string,mixed> $model
     *  @param list<string> $warnings
     *  @return array<string,mixed>
     */
    private static function convertDocument(array $model, array &$warnings): array
    {
        $units = max(12, (int) ($model['units'] ?? 120));
        $nodes = [];
        foreach ((array) ($model['nodes'] ?? []) as $index => $node) {
            if (!is_array($node)) {
                continue;
            }
            $converted = self::convertNode($node, $units, (int) $index, $warnings);
            if ($converted !== null) {
                $nodes[] = $converted;
            }
        }
        $validIds = [];
        foreach ($nodes as $node) {
            $validIds[(string) $node['id']] = true;
        }
        foreach ($nodes as &$node) {
            $parent = $node['parentId'];
            if ($parent !== null && !isset($validIds[$parent])) {
                $node['parentId'] = null;
            }
        }
        unset($node);
        self::reconcileContainerHeights($nodes);
        return ['schemaVersion' => 2, 'nodes' => $nodes, 'settings' => ['rowPixelSize' => 8]];
    }

    /** @param array<string,mixed> $node
     *  @param list<string> $warnings
     *  @return array<string,mixed>|null
     */
    private static function convertNode(array $node, int $units, int $order, array &$warnings): ?array
    {
        $legacyType = sanitize_key((string) ($node['type'] ?? ''));
        $type = match ($legacyType) {
            'section' => NodeSchema::SECTION,
            'container' => NodeSchema::CONTAINER,
            'text', 'table', 'datalist', 'icon', 'badge' => NodeSchema::TEXT,
            'eventlist' => NodeSchema::EVENTS,
            'vehiclelist' => NodeSchema::VEHICLES,
            'gallerylist' => NodeSchema::GALLERIES,
            'image' => NodeSchema::IMAGE,
            'button' => NodeSchema::BUTTON,
            'spacer' => NodeSchema::SPACER,
            'divider' => NodeSchema::DIVIDER,
            'contactform' => NodeSchema::CONTACT_FORM,
            'membershipform' => NodeSchema::MEMBERSHIP_FORM,
            'menu' => NodeSchema::NAVIGATION,
            default => '',
        };
        if ($type === '') {
            $warnings[] = 'Unsupported previous-generation node type omitted: ' . $legacyType;
            return null;
        }
        $id = sanitize_key((string) ($node['id'] ?? ''));
        if ($id === '') {
            $id = 'migrated-' . substr(hash('sha256', $legacyType . ':' . $order), 0, 20);
        }
        $parent = sanitize_key((string) ($node['parentId'] ?? ''));
        $props = is_array($node['props'] ?? null) ? $node['props'] : [];
        $responsive = self::convertGeometry(is_array($node['geometry'] ?? null) ? $node['geometry'] : [], $units);
        if ($type === NodeSchema::CONTACT_FORM || $type === NodeSchema::MEMBERSHIP_FORM) {
            $minimumRows = $type === NodeSchema::MEMBERSHIP_FORM ? 128 : 100;
            foreach ($responsive as &$geometry) {
                $geometry['h'] = max($minimumRows, (int) ($geometry['h'] ?? 1));
            }
            unset($geometry);
        }
        return [
            'id' => $id,
            'type' => $type,
            'parentId' => $parent !== '' ? $parent : null,
            'order' => $order,
            'props' => self::convertProps($legacyType, $props, $warnings),
            'responsive' => $responsive,
        ];
    }

    /** @param array<string,mixed> $props
     *  @param list<string> $warnings
     *  @return array<string,mixed>
     */
    private static function convertProps(string $type, array $props, array &$warnings): array
    {
        $background = self::colorOrTransparent((string) ($props['background'] ?? ''), 'transparent');
        if ($type === 'section' || $type === 'container') {
            return [
                'background' => $background,
                'padding' => max(0, min(120, (int) ($props['padding'] ?? 0))),
                'radius' => max(0, min(80, (int) ($props['radius'] ?? 0))),
                'borderWidth' => max(0, min(20, (int) ($props['borderWidth'] ?? 0))),
                'borderColor' => self::color((string) ($props['borderColor'] ?? ''), '#d0d0d0'),
                'autoHeight' => true,
                'minHeightRows' => max(1, (int) ($props['minHeightRows'] ?? 1)),
            ];
        }
        if ($type === 'text') {
            $heading = sanitize_text_field((string) ($props['heading'] ?? ''));
            $level = in_array((string) ($props['headingLevel'] ?? ''), ['h1','h2','h3','h4','h5','h6'], true) ? (string) $props['headingLevel'] : 'h2';
            $body = wp_kses_post((string) ($props['text'] ?? ''));
            $content = ($heading !== '' ? '<' . $level . '>' . esc_html($heading) . '</' . $level . '>' : '') . ($body !== '' ? '<div>' . $body . '</div>' : '');
            return [
                'content' => $content !== '' ? $content : '<p></p>',
                'color' => self::color((string) ($props['textColor'] ?? ''), '#222222'),
                'fontSize' => max(8, min(120, (int) ($props['fontSize'] ?? 18))),
                'fontWeight' => self::fontWeight($props['fontWeight'] ?? 400),
                'lineHeight' => max(0.8, min(3.0, (float) ($props['lineHeight'] ?? 1.5))),
                'align' => in_array((string) ($props['align'] ?? ''), ['left','center','right'], true) ? (string) $props['align'] : 'left',
                'verticalAlign' => in_array((string) ($props['verticalAlign'] ?? ''), ['top','center','bottom'], true) ? (string) $props['verticalAlign'] : 'top',
                'background' => !empty($props['backgroundTransparent']) ? 'transparent' : self::colorOrTransparent((string) ($props['background'] ?? ''), 'transparent'),
                'padding' => max(0, min(120, (int) ($props['padding'] ?? 0))),
                'radius' => max(0, min(80, (int) ($props['radius'] ?? 0))),
            ];
        }
        if ($type === 'table') {
            $html = '<table><thead><tr>';
            foreach ((array) ($props['headers'] ?? []) as $value) { $html .= '<th>' . esc_html((string) $value) . '</th>'; }
            $html .= '</tr></thead><tbody>';
            foreach ((array) ($props['rows'] ?? []) as $row) { $html .= '<tr>'; foreach ((array) $row as $value) { $html .= '<td>' . esc_html((string) $value) . '</td>'; } $html .= '</tr>'; }
            $html .= '</tbody></table>';
            return ['content' => $html, 'color' => self::color((string) ($props['cellTextColor'] ?? ''), '#222222'), 'fontSize' => max(8, min(120, (int) ($props['fontSize'] ?? 14)))];
        }
        if ($type === 'datalist') {
            $html = '<dl>';
            foreach ((array) ($props['rows'] ?? []) as $row) { if (is_array($row)) { $html .= '<dt><strong>' . esc_html((string) ($row['label'] ?? '')) . '</strong></dt><dd>' . esc_html((string) ($row['value'] ?? '')) . '</dd>'; } }
            $html .= '</dl>';
            return ['content' => $html, 'color' => self::color((string) ($props['valueColor'] ?? ''), '#222222'), 'fontSize' => max(8, min(120, (int) ($props['fontSize'] ?? 15)))];
        }
        if ($type === 'icon') {
            return ['content' => '<p>◆</p>', 'color' => self::color((string) ($props['iconColor'] ?? ''), '#222222'), 'fontSize' => max(8, min(120, (int) ($props['iconSize'] ?? 24)))];
        }
        if ($type === 'badge') {
            $background = self::color((string) ($props['background'] ?? ''), '#c3ae83');
            $textColor = self::color((string) ($props['textColor'] ?? ''), '#222222');
            $paddingX = max(0, min(120, (int) ($props['paddingX'] ?? 12)));
            $paddingY = max(0, min(120, (int) ($props['paddingY'] ?? 5)));
            $radius = max(0, min(100, (int) ($props['radius'] ?? 20)));
            $html = '<span style="display:inline-block;background:' . esc_attr($background) . ';color:' . esc_attr($textColor) . ';padding:' . $paddingY . 'px ' . $paddingX . 'px;border-radius:' . $radius . 'px;font-weight:' . self::fontWeight($props['fontWeight'] ?? 700) . '">' . esc_html((string) ($props['text'] ?? '')) . '</span>';
            return ['content' => $html, 'color' => $textColor, 'fontSize' => max(8, min(120, (int) ($props['fontSize'] ?? 13)))];
        }
        if ($type === 'eventlist') {
            $filter = (string) ($props['dateFilter'] ?? 'upcoming');
            return [
                'count' => max(1, min(50, (int) ($props['limit'] ?? 12))),
                'showPast' => $filter !== 'upcoming',
                'columns' => max(1, min(4, (int) ($props['columns'] ?? 3))),
                'gap' => max(0, min(80, (int) ($props['cardGap'] ?? 18))),
                'padding' => max(0, min(80, (int) ($props['cardPadding'] ?? 12))),
                'radius' => max(0, min(60, (int) ($props['cardRadius'] ?? 4))),
                'cardBackground' => self::color((string) ($props['cardBackground'] ?? ''), '#ffffff'),
                'textColor' => self::color((string) ($props['textColor'] ?? ''), '#30382a'),
                'headingColor' => self::color((string) ($props['textColor'] ?? ''), '#30382a'),
                'accentColor' => self::color((string) ($props['accentColor'] ?? ''), '#c3ae83'),
                'showImage' => !array_key_exists('showImage', $props) || !empty($props['showImage']),
                'showSummary' => !array_key_exists('showSummary', $props) || !empty($props['showSummary']),
                'showFacts' => (!array_key_exists('showDate', $props) || !empty($props['showDate'])) || (!array_key_exists('showLocation', $props) || !empty($props['showLocation'])),
            ];
        }
        if ($type === 'vehiclelist') {
            return [
                'count' => max(1, min(100, (int) ($props['limit'] ?? 24))),
                'columns' => max(1, min(4, (int) ($props['columns'] ?? 3))),
                'gap' => max(0, min(80, (int) ($props['cardGap'] ?? 18))),
                'padding' => max(0, min(80, (int) ($props['cardPadding'] ?? 12))),
                'radius' => max(0, min(60, (int) ($props['cardRadius'] ?? 4))),
                'cardBackground' => self::color((string) ($props['cardBackground'] ?? ''), '#ffffff'),
                'textColor' => self::color((string) ($props['textColor'] ?? ''), '#30382a'),
                'headingColor' => self::color((string) ($props['textColor'] ?? ''), '#30382a'),
                'accentColor' => self::color((string) ($props['accentColor'] ?? ''), '#c3ae83'),
                'showImage' => !array_key_exists('showImage', $props) || !empty($props['showImage']),
                'showSummary' => !array_key_exists('showSummary', $props) || !empty($props['showSummary']),
                'showFacts' => true,
            ];
        }
        if ($type === 'gallerylist') {
            return [
                'count' => max(1, min(100, (int) ($props['limit'] ?? 24))),
                'columns' => max(1, min(4, (int) ($props['columns'] ?? 3))),
                'gap' => max(0, min(80, (int) ($props['cardGap'] ?? 18))),
                'padding' => max(0, min(80, (int) ($props['cardPadding'] ?? 12))),
                'radius' => max(0, min(60, (int) ($props['cardRadius'] ?? 4))),
                'cardBackground' => self::color((string) ($props['cardBackground'] ?? ''), '#ffffff'),
                'textColor' => self::color((string) ($props['textColor'] ?? ''), '#30382a'),
                'headingColor' => self::color((string) ($props['textColor'] ?? ''), '#30382a'),
                'accentColor' => self::color((string) ($props['accentColor'] ?? ''), '#c3ae83'),
                'showCover' => !array_key_exists('showImage', $props) || !empty($props['showImage']),
                'showSummary' => !array_key_exists('showSummary', $props) || !empty($props['showSummary']),
            ];
        }
        if ($type === 'image') {
            $mediaId = absint($props['mediaId'] ?? $props['attachmentId'] ?? 0);
            $url = trim((string) ($props['url'] ?? ''));
            if ($mediaId <= 0 && $url !== '') {
                $warnings[] = 'An image URL without a packaged media ID could not be migrated automatically.';
            }
            $legacyFit = strtolower((string) ($props['fit'] ?? $props['objectFit'] ?? 'cover'));
            $objectFit = in_array($legacyFit, ['contain', 'original'], true) ? 'contain' : 'cover';
            $positionX = strtolower((string) ($props['imageAlignX'] ?? 'center'));
            if (!in_array($positionX, ['left', 'center', 'right'], true)) {
                $positionX = 'center';
            }
            $positionY = strtolower((string) ($props['imageAlignY'] ?? 'center'));
            if (!in_array($positionY, ['top', 'center', 'bottom'], true)) {
                $positionY = 'center';
            }
            return [
                'attachmentId' => $mediaId,
                'alt' => sanitize_text_field((string) ($props['alt'] ?? '')),
                'objectFit' => $objectFit,
                'positionX' => $positionX,
                'positionY' => $positionY,
            ];
        }
        if ($type === 'button') {
            return [
                'label' => sanitize_text_field((string) ($props['text'] ?? 'Knap')),
                'url' => esc_url_raw((string) ($props['url'] ?? '#')),
                'target' => !empty($props['targetBlank']) ? '_blank' : '_self',
                'align' => 'stretch',
                'background' => self::color((string) ($props['background'] ?? ''), '#2f4858'),
                'color' => self::color((string) ($props['textColor'] ?? ''), '#ffffff'),
                'radius' => max(0, min(80, (int) ($props['radius'] ?? 4))),
                'paddingX' => max(0, min(120, (int) ($props['paddingX'] ?? 18))),
                'paddingY' => max(0, min(80, (int) ($props['paddingY'] ?? 10))),
                'fontSize' => max(8, min(80, (int) ($props['fontSize'] ?? 16))),
                'fontWeight' => self::fontWeight($props['fontWeight'] ?? 600),
                'borderWidth' => max(0, min(20, (int) ($props['borderWidth'] ?? 0))),
                'borderColor' => self::color((string) ($props['borderColor'] ?? $props['background'] ?? ''), '#2f4858'),
            ];
        }
        if ($type === 'divider') {
            return ['color' => self::color((string) ($props['lineColor'] ?? ''), '#d0d0d0'), 'thickness' => max(1, min(20, (int) ($props['lineWidth'] ?? 1)))];
        }
        if ($type === 'contactform' || $type === 'membershipform') {
            $membership = $type === 'membershipform';
            return [
                'heading' => sanitize_text_field((string) ($props['heading'] ?? ($membership ? 'Bliv medlem' : 'Kontakt os'))),
                'intro' => sanitize_textarea_field((string) ($props['intro'] ?? '')),
                'columns' => 2,
                'gap' => 16,
                'padding' => max(0, min(80, (int) ($props['padding'] ?? 20))),
                'radius' => max(0, min(30, (int) ($props['radius'] ?? 6))),
                'background' => self::color((string) ($props['background'] ?? ''), '#ffffff'),
                'fieldBackground' => self::color((string) ($props['fieldBackground'] ?? ''), '#ffffff'),
                'textColor' => self::color((string) ($props['textColor'] ?? ''), '#222222'),
                'labelColor' => self::color((string) ($props['textColor'] ?? ''), '#222222'),
                'borderColor' => '#d0d0d0',
                'accentColor' => self::color((string) ($props['accentColor'] ?? ''), '#2f4858'),
                'buttonTextColor' => '#ffffff',
                'submitLabel' => sanitize_text_field((string) ($props['buttonText'] ?? ($membership ? 'Send indmeldelse' : 'Send besked'))),
                'successMessage' => $membership ? 'Tak. Din indmeldelse er sendt.' : 'Tak. Din henvendelse er sendt.',
                'showPhone' => !array_key_exists('showPhone', $props) || !empty($props['showPhone']),
                'showSubject' => !$membership,
                'showAddress' => $membership,
                'showMessage' => true,
                'messageRows' => 5,
                'requireConsent' => !array_key_exists('requireConsent', $props) || !empty($props['requireConsent']),
                'consentText' => $membership ? 'Jeg accepterer, at oplysningerne bruges til at behandle min indmeldelse.' : 'Jeg accepterer, at oplysningerne bruges til at besvare min henvendelse.',
            ];
        }
        if ($type === 'menu') {
            return [
                'menuId' => absint($props['menuId'] ?? 0),
                'orientation' => (string) ($props['orientation'] ?? 'horizontal') === 'vertical' ? 'vertical' : 'horizontal',
                'align' => in_array((string) ($props['align'] ?? ''), ['left','center','right'], true) ? (string) $props['align'] : 'left',
                'gap' => max(0, min(80, (int) ($props['menuGap'] ?? $props['gap'] ?? 24))),
                'fontSize' => max(10, min(48, (int) ($props['fontSize'] ?? 16))),
                'fontWeight' => self::fontWeight($props['fontWeight'] ?? 600),
                'textColor' => self::color((string) ($props['textColor'] ?? ''), '#222222'),
                'hoverColor' => self::color((string) ($props['hoverTextColor'] ?? $props['hoverColor'] ?? ''), '#2271b1'),
                'background' => self::colorOrTransparent((string) ($props['background'] ?? ''), 'transparent'),
                'submenuBackground' => self::color((string) ($props['submenuBackground'] ?? ''), '#ffffff'),
                'submenuTextColor' => self::color((string) ($props['submenuTextColor'] ?? ''), '#222222'),
                'toggleLabel' => sanitize_text_field((string) ($props['toggleLabel'] ?? 'Menu')),
            ];
        }
        return [];
    }

    /** @param array<string,mixed> $geometry
     *  @return array<string,array{x:int,y:int,w:int,h:int}>
     */
    private static function convertGeometry(array $geometry, int $units): array
    {
        $desktopRaw = is_array($geometry['desktop'] ?? null) ? $geometry['desktop'] : [];
        $desktop = self::convertDeviceGeometry($desktopRaw, $units, ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 4]);
        $result = ['desktop' => $desktop];
        foreach (['laptop','tablet','mobile'] as $breakpoint) {
            $raw = is_array($geometry[$breakpoint] ?? null) ? $geometry[$breakpoint] : [];
            if ($raw === [] || !empty($raw['inheritDesktop'])) {
                $result[$breakpoint] = $desktop;
                continue;
            }
            $result[$breakpoint] = self::convertDeviceGeometry($raw, $units, $desktop);
        }
        return $result;
    }

    /** @param array<string,mixed> $raw
     *  @param array<string,int> $fallback
     *  @return array{x:int,y:int,w:int,h:int,fineX:int,fineW:int}
     */
    private static function convertDeviceGeometry(array $raw, int $units, array $fallback): array
    {
        if ($raw === []) {
            $x = max(0, min(11, (int) ($fallback['x'] ?? 0)));
            $w = max(1, min(12 - $x, (int) ($fallback['w'] ?? 12)));
            return array_merge($fallback, [
                'fineX' => (int) ($fallback['fineX'] ?? ($x * 10)),
                'fineW' => (int) ($fallback['fineW'] ?? ($w * 10)),
            ]);
        }
        $factor = $units / 12;
        $x = (int) round(((int) ($raw['x'] ?? 0)) / $factor);
        $w = (int) round(((int) ($raw['w'] ?? $units)) / $factor);
        $x = max(0, min(11, $x));
        $w = max(1, min(12 - $x, $w));

        $sourceX = max(0, min($units - 1, (int) ($raw['x'] ?? 0)));
        $sourceW = max(1, min($units - $sourceX, (int) ($raw['w'] ?? $units)));
        $fineX = max(0, min(119, (int) round(($sourceX * 120) / $units)));
        $fineW = max(1, min(120 - $fineX, (int) round(($sourceW * 120) / $units)));
        return [
            'x' => $x,
            'y' => max(0, (int) ($raw['y'] ?? 0)),
            'w' => $w,
            'h' => max(1, (int) ($raw['h'] ?? ($fallback['h'] ?? 4))),
            'fineX' => $fineX,
            'fineW' => $fineW,
        ];
    }

    /** @param array<string,mixed> $layout */
    private static function injectModuleNode(array &$layout, string $module): void
    {
        if (!in_array($module, [NodeSchema::EVENTS, NodeSchema::VEHICLES, NodeSchema::GALLERIES], true)) {
            return;
        }
        foreach ((array) ($layout['nodes'] ?? []) as $existing) {
            if (is_array($existing) && (string) ($existing['type'] ?? '') === $module) {
                return;
            }
        }
        $parentId = null;
        foreach ($layout['nodes'] as &$node) {
            if ($node['type'] === NodeSchema::SECTION && str_contains((string) $node['id'], 'between')) {
                $parentId = (string) $node['id'];
                $node['props']['minHeightRows'] = max(60, (int) ($node['props']['minHeightRows'] ?? 0));
                foreach ($node['responsive'] as &$g) { $g['h'] = max(60, (int) $g['h']); }
                unset($g);
                break;
            }
        }
        unset($node);
        if ($parentId === null) {
            return;
        }
        $layout['nodes'][] = [
            'id' => 'migrated-' . $module . '-list',
            'type' => $module,
            'parentId' => $parentId,
            'order' => count($layout['nodes']),
            'props' => $module === NodeSchema::EVENTS
                ? ['count'=>12,'showPast'=>true,'columns'=>3,'gap'=>20,'padding'=>18,'radius'=>6,'cardBackground'=>'#ffffff','textColor'=>'#222222','headingColor'=>'#222222','accentColor'=>'#2f4858','showImage'=>true,'showSummary'=>true,'showFacts'=>true]
                : ($module === NodeSchema::VEHICLES
                    ? ['count'=>24,'columns'=>3,'gap'=>20,'padding'=>18,'radius'=>6,'cardBackground'=>'#ffffff','textColor'=>'#222222','headingColor'=>'#222222','accentColor'=>'#2f4858','showImage'=>true,'showSummary'=>true,'showFacts'=>true]
                    : ['count'=>24,'columns'=>3,'gap'=>20,'padding'=>16,'radius'=>6,'cardBackground'=>'#ffffff','textColor'=>'#222222','headingColor'=>'#222222','accentColor'=>'#2f4858','showCover'=>true,'showSummary'=>true]),
            'responsive' => ['desktop'=>['x'=>0,'y'=>0,'w'=>12,'h'=>60], 'laptop'=>['x'=>0,'y'=>0,'w'=>12,'h'=>60], 'tablet'=>['x'=>0,'y'=>0,'w'=>12,'h'=>60], 'mobile'=>['x'=>0,'y'=>0,'w'=>12,'h'=>60]],
        ];
    }

    /**
     * @param list<array<string,mixed>> $records
     * @param array<string,mixed> $site
     * @return array{byId:array<int,string>,byPath:array<string,string>}
     */
    private static function pageLinkMap(array $records, array $site): array
    {
        $source = is_array($site['source'] ?? null) ? $site['source'] : [];
        $home = rtrim(esc_url_raw((string) ($source['homeUrl'] ?? '')), '/');
        $byId = [];
        $byPath = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }
            $sourceId = absint($record['sourceId'] ?? 0);
            if ($sourceId <= 0) {
                continue;
            }
            $path = trim((string) ($record['path'] ?? ''), '/');
            $sourceUrl = esc_url_raw((string) ($record['sourceUrl'] ?? ''));
            if ($sourceUrl === '' && $home !== '') {
                $sourceUrl = $path === '' ? $home . '/' : $home . '/' . $path . '/';
            }
            if ($sourceUrl === '') {
                continue;
            }
            $byId[$sourceId] = $sourceUrl;
            if ($path !== '') {
                $byPath[self::normalizedPagePath('/' . $path . '/')] = $sourceUrl;
            }
            $parsedPath = parse_url($sourceUrl, PHP_URL_PATH);
            $parsedQuery = parse_url($sourceUrl, PHP_URL_QUERY);
            if (is_string($parsedPath) && ($parsedQuery === null || $parsedQuery === '')) {
                $byPath[self::normalizedPagePath($parsedPath)] = $sourceUrl;
            }
        }
        return ['byId' => $byId, 'byPath' => $byPath];
    }

    /**
     * Canonicalize only links that resolve to pages included in the exported package.
     * This repairs stale source-host URLs without rewriting unrelated external links.
     *
     * @param array<string,mixed> $model
     * @param array{byId?:array<int,string>,byPath?:array<string,string>} $pageLinks
     * @return array<string,mixed>
     */
    private static function canonicalizeLegacyLinks(array $model, array $pageLinks): array
    {
        $byId = is_array($pageLinks['byId'] ?? null) ? $pageLinks['byId'] : [];
        $byPath = is_array($pageLinks['byPath'] ?? null) ? $pageLinks['byPath'] : [];
        $nodes = is_array($model['nodes'] ?? null) ? $model['nodes'] : [];
        foreach ($nodes as $index => $node) {
            if (!is_array($node) || sanitize_key((string) ($node['type'] ?? '')) !== 'button') {
                continue;
            }
            $props = is_array($node['props'] ?? null) ? $node['props'] : [];
            $target = '';
            if (sanitize_key((string) ($props['linkType'] ?? '')) === 'page') {
                $pageId = absint($props['pageId'] ?? 0);
                if ($pageId > 0 && isset($byId[$pageId])) {
                    $target = (string) $byId[$pageId];
                }
            }
            if ($target === '') {
                $url = trim((string) ($props['url'] ?? ''));
                if ($url !== '') {
                    $path = parse_url($url, PHP_URL_PATH);
                    if (is_string($path)) {
                        $key = self::normalizedPagePath($path);
                        if (isset($byPath[$key])) {
                            $target = (string) $byPath[$key];
                            $fragment = parse_url($url, PHP_URL_FRAGMENT);
                            if (is_string($fragment) && $fragment !== '') {
                                $target .= '#' . rawurlencode($fragment);
                            }
                        }
                    }
                }
            }
            if ($target !== '') {
                $props['url'] = $target;
                $props['linkType'] = 'url';
                $props['pageId'] = 0;
                $node['props'] = $props;
                $nodes[$index] = $node;
            }
        }
        $model['nodes'] = $nodes;
        return $model;
    }

    private static function normalizedPagePath(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : $path . '/';
    }

    /** @param list<array<string,mixed>> $nodes */
    private static function reconcileContainerHeights(array &$nodes): void
    {
        $indexById = [];
        foreach ($nodes as $index => $node) {
            $indexById[(string) ($node['id'] ?? '')] = $index;
        }
        $depth = static function (array $node) use (&$nodes, $indexById): int {
            $value = 0;
            $parentId = (string) ($node['parentId'] ?? '');
            $seen = [];
            while ($parentId !== '' && isset($indexById[$parentId]) && !isset($seen[$parentId])) {
                $seen[$parentId] = true;
                $value++;
                $parent = $nodes[$indexById[$parentId]];
                $parentId = (string) ($parent['parentId'] ?? '');
            }
            return $value;
        };
        $order = array_keys($nodes);
        usort($order, static fn(int $a, int $b): int => $depth($nodes[$b]) <=> $depth($nodes[$a]));

        foreach ($order as $index) {
            $node = $nodes[$index];
            if (!in_array((string) ($node['type'] ?? ''), [NodeSchema::SECTION, NodeSchema::CONTAINER], true)) {
                continue;
            }
            $id = (string) ($node['id'] ?? '');
            $padding = max(0, (int) ($node['props']['padding'] ?? 0));
            $paddingRows = (int) ceil(($padding * 2) / 8);
            $minimum = max(1, (int) ($node['props']['minHeightRows'] ?? 1));
            foreach (['desktop','laptop','tablet','mobile'] as $breakpoint) {
                $bottom = 0;
                foreach ($nodes as $child) {
                    if ((string) ($child['parentId'] ?? '') !== $id) {
                        continue;
                    }
                    $geometry = is_array($child['responsive'][$breakpoint] ?? null) ? $child['responsive'][$breakpoint] : [];
                    $bottom = max($bottom, (int) ($geometry['y'] ?? 0) + (int) ($geometry['h'] ?? 0));
                }
                $current = (int) ($nodes[$index]['responsive'][$breakpoint]['h'] ?? 1);
                $nodes[$index]['responsive'][$breakpoint]['h'] = max($current, $minimum, $bottom + $paddingRows);
            }
        }
    }

    /** @param array<string,mixed> $modules @return array<string,string> */
    private static function moduleCatalog(array $modules): array
    {
        $result = [];
        $catalog = is_array($modules['catalog'] ?? null) ? $modules['catalog'] : [];
        foreach ((array) ($catalog['modules'] ?? []) as $module) {
            if (!is_array($module)) { continue; }
            $key = sanitize_key((string) ($module['key'] ?? ''));
            if (in_array($key, ['events','vehicles','galleries'], true)) {
                $result[$key] = sanitize_text_field((string) ($module['label'] ?? $key));
            }
        }
        return $result;
    }

    /** @param array<string,mixed> $page @param array<string,string> $catalog */
    private static function classifyModulePage(array $page, array $catalog): string
    {
        $haystack = sanitize_title((string) ($page['slug'] ?? '') . '-' . (string) ($page['title'] ?? ''));
        foreach ($catalog as $key => $label) {
            if (str_contains($haystack, sanitize_title($key)) || str_contains($haystack, sanitize_title($label))) {
                return $key;
            }
        }
        return '';
    }

    /** @param list<array<string,mixed>> $attributes @return array{0:array<string,string>,1:list<array{label:string,value:string}>} */
    private static function vehicleAttributes(array $attributes): array
    {
        $fixedKeys = ['manufacturer','model','year','country','status','engine','power','weight','length','width','height','crew'];
        $fixed = array_fill_keys($fixedKeys, '');
        $extra = [];
        foreach ($attributes as $attr) {
            if (!is_array($attr) || empty($attr['enabled'])) { continue; }
            $key = sanitize_key((string) ($attr['key'] ?? ''));
            $value = sanitize_text_field((string) ($attr['value'] ?? ''));
            $label = sanitize_text_field((string) ($attr['label'] ?? $key));
            if (in_array($key, $fixedKeys, true)) { $fixed[$key] = $value; }
            elseif ($value !== '' || $label !== '') { $extra[] = ['label'=>$label,'value'=>$value]; }
        }
        return [$fixed, $extra];
    }

    /** @param list<array<string,mixed>> $attributes */
    private static function moduleContent(string $description, array $attributes): string
    {
        $html = wp_kses_post($description);
        foreach ($attributes as $attr) {
            if (!is_array($attr) || empty($attr['enabled'])) { continue; }
            $value = wp_kses_post((string) ($attr['value'] ?? ''));
            if ($value === '') { continue; }
            $label = sanitize_text_field((string) ($attr['label'] ?? ''));
            if ($label !== '') { $html .= '<h2>' . esc_html($label) . '</h2>'; }
            $html .= '<div>' . $value . '</div>';
        }
        return $html;
    }

    /** @return array{0:string,1:string} */
    private static function dateTimeParts(string $value): array
    {
        if (preg_match('/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2})/', trim($value), $match) === 1) {
            return [$match[1], $match[2]];
        }
        return ['', ''];
    }

    private static function status(string $value): string
    {
        $value = sanitize_key($value);
        return in_array($value, ['publish','draft','private','pending'], true) ? $value : 'draft';
    }

    private static function fontWeight(mixed $value): int
    {
        $weight = (int) $value;
        if ($weight <= 450) return 400;
        if ($weight <= 550) return 500;
        if ($weight <= 650) return 600;
        return 700;
    }

    private static function color(string $value, string $fallback): string
    {
        $color = sanitize_hex_color($value);
        return is_string($color) ? strtolower($color) : $fallback;
    }

    private static function colorOrTransparent(string $value, string $fallback): string
    {
        if ($value === '' || strtolower($value) === 'transparent') return 'transparent';
        return self::color($value, $fallback);
    }

    /** @return array{schemaVersion:int,nodes:list<array<string,mixed>>,settings:array<string,mixed>} */
    private static function emptyDocument(): array
    {
        return ['schemaVersion'=>2,'nodes'=>[],'settings'=>['rowPixelSize'=>8]];
    }

    private static function copyEntryToTemp(\ZipArchive $zip, string $path, int $size, string $sha): string
    {
        $temp = wp_tempnam(wp_basename($path));
        if (!is_string($temp) || $temp === '') throw new \RuntimeException('Temporary media conversion file could not be created.');
        $in = $zip->getStream($path);
        $out = fopen($temp, 'wb');
        if (!is_resource($in) || !is_resource($out)) {
            if (is_resource($in)) fclose($in);
            if (is_resource($out)) fclose($out);
            @unlink($temp);
            throw new \RuntimeException('Schema 1.0 media stream could not be opened.');
        }
        $hash = hash_init('sha256'); $bytes = 0;
        try {
            while (!feof($in)) {
                $chunk = fread($in, 1048576);
                if ($chunk === false) throw new \RuntimeException('Schema 1.0 media stream could not be read.');
                if ($chunk === '') continue;
                $bytes += strlen($chunk); hash_update($hash, $chunk);
                if (fwrite($out, $chunk) === false) throw new \RuntimeException('Schema 1.0 media temporary file could not be written.');
            }
        } finally { fclose($in); fclose($out); }
        if ($bytes !== $size || !hash_equals(strtolower($sha), hash_final($hash))) { @unlink($temp); throw new \RuntimeException('Schema 1.0 media changed during conversion.'); }
        return $temp;
    }

    /** @return array<string,mixed> */
    private static function readJson(\ZipArchive $zip, string $path, int $maxBytes = PortablePackage::MAX_JSON_BYTES): array
    {
        $stat = $zip->statName($path, \ZipArchive::FL_UNCHANGED);
        if (!is_array($stat) || (int) ($stat['size'] ?? 0) > $maxBytes) throw new \RuntimeException('Schema 1.0 JSON is missing or too large: ' . $path);
        $raw = $zip->getFromName($path, 0, \ZipArchive::FL_UNCHANGED);
        if (!is_string($raw)) throw new \RuntimeException('Schema 1.0 JSON could not be read: ' . $path);
        try { $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); }
        catch (\JsonException $e) { throw new \RuntimeException('Invalid schema 1.0 JSON in ' . $path . ': ' . $e->getMessage()); }
        if (!is_array($decoded)) throw new \RuntimeException('Schema 1.0 JSON root is invalid: ' . $path);
        return $decoded;
    }

    private static function hashEntry(\ZipArchive $zip, string $path, int $expectedSize): string
    {
        $stream = $zip->getStream($path);
        if (!is_resource($stream)) throw new \RuntimeException('Schema 1.0 file stream could not be opened: ' . $path);
        $hash = hash_init('sha256'); $bytes = 0;
        try {
            while (!feof($stream)) {
                $chunk = fread($stream, 1048576);
                if ($chunk === false) throw new \RuntimeException('Schema 1.0 file stream could not be read: ' . $path);
                if ($chunk === '') continue;
                $bytes += strlen($chunk); hash_update($hash, $chunk);
            }
        } finally { fclose($stream); }
        if ($bytes !== $expectedSize) throw new \RuntimeException('Schema 1.0 file stream size mismatch: ' . $path);
        return hash_final($hash);
    }

    private function __construct() {}
}
