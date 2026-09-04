<?php

declare(strict_types=1);

namespace VisualDesignerManager\Transfer;

final class LegacyMediaRecovery
{
    private const MAX_RECOVERIES = 25;
    private const MAX_REMOTE_BYTES = 26214400;

    private function __construct()
    {
    }

    /**
     * Recover schema 1.0 image nodes that contain an uploads URL but no media ID.
     *
     * The recovery path is intentionally narrow:
     * - only image nodes are considered;
     * - only URLs below /wp-content/uploads/ are accepted;
     * - the package source host is tried first using the same uploads path;
     * - the original URL is only attempted through WordPress' safe HTTP client;
     * - downloaded data must validate as a non-SVG image and stay below the size cap.
     *
     * @param array<string,mixed> $site
     * @param array<string,mixed> $pagesPayload
     * @param array<string,mixed> $templatesPayload
     * @param list<array<string,mixed>> $existingMedia
     * @return array{
     *   pagesPayload:array<string,mixed>,
     *   templatesPayload:array<string,mixed>,
     *   items:list<array<string,mixed>>,
     *   tempFiles:array<string,string>,
     *   warnings:list<string>,
     *   recovered:int,
     *   unresolved:int
     * }
     */
    public static function recover(
        array $site,
        array $pagesPayload,
        array $templatesPayload,
        array $existingMedia
    ): array {
        $references = self::collectReferences($pagesPayload, $templatesPayload);
        if ($references === []) {
            return [
                'pagesPayload' => $pagesPayload,
                'templatesPayload' => $templatesPayload,
                'items' => [],
                'tempFiles' => [],
                'warnings' => [],
                'recovered' => 0,
                'unresolved' => 0,
            ];
        }

        $usedIds = [];
        $nextId = 1;
        foreach ($existingMedia as $record) {
            if (!is_array($record)) {
                continue;
            }
            $sourceId = absint($record['sourceId'] ?? 0);
            if ($sourceId > 0) {
                $usedIds[$sourceId] = true;
                $nextId = max($nextId, $sourceId + 1);
            }
        }

        $sourceBases = self::sourceBases($site);
        $items = [];
        $tempFiles = [];
        $warnings = [];
        $sourceIdsByUrl = [];
        $unresolved = 0;

        foreach ($references as $url => $reference) {
            if (count($items) >= self::MAX_RECOVERIES) {
                $unresolved++;
                $warnings[] = 'Legacy-media recovery stopped after the configured recovery limit was reached.';
                continue;
            }

            while (isset($usedIds[$nextId])) {
                $nextId++;
            }
            $sourceId = $nextId++;
            $usedIds[$sourceId] = true;

            try {
                $recovered = self::download((string) $url, $sourceBases, $sourceId, (string) ($reference['alt'] ?? ''));
                $items[] = $recovered['record'];
                $tempFiles[(string) $recovered['record']['archivePath']] = $recovered['tempPath'];
                $sourceIdsByUrl[(string) $url] = $sourceId;
                $warnings[] = 'Genfandt legacy-billede til lokal VDM-mediefil: ' . (string) $recovered['record']['filename'];
            } catch (\Throwable $exception) {
                $unresolved++;
                $warnings[] = 'Kunne ikke genfinde legacy-billede ' . self::displayName((string) $url) . ': ' . $exception->getMessage();
            }
        }

        if ($sourceIdsByUrl !== []) {
            $pagesPayload = self::rewritePages($pagesPayload, $sourceIdsByUrl);
            $templatesPayload = self::rewriteTemplates($templatesPayload, $sourceIdsByUrl);
        }

        return [
            'pagesPayload' => $pagesPayload,
            'templatesPayload' => $templatesPayload,
            'items' => $items,
            'tempFiles' => $tempFiles,
            'warnings' => array_values(array_unique($warnings)),
            'recovered' => count($items),
            'unresolved' => $unresolved,
        ];
    }

    /** @return array<string,array{alt:string}> */
    private static function collectReferences(array $pagesPayload, array $templatesPayload): array
    {
        $references = [];
        foreach ((array) ($pagesPayload['records'] ?? []) as $record) {
            if (!is_array($record)) {
                continue;
            }
            $designer = is_array($record['visualDesigner'] ?? null) ? $record['visualDesigner'] : [];
            $model = is_array($designer['model'] ?? null) ? $designer['model'] : [];
            self::collectModelReferences($model, $references);
        }
        foreach ((array) ($templatesPayload['records'] ?? []) as $record) {
            if (!is_array($record)) {
                continue;
            }
            $model = is_array($record['model'] ?? null) ? $record['model'] : [];
            self::collectModelReferences($model, $references);
        }
        return $references;
    }

    /** @param array<string,array{alt:string}> $references */
    private static function collectModelReferences(array $model, array &$references): void
    {
        foreach ((array) ($model['nodes'] ?? []) as $node) {
            if (!is_array($node) || sanitize_key((string) ($node['type'] ?? '')) !== 'image') {
                continue;
            }
            $props = is_array($node['props'] ?? null) ? $node['props'] : [];
            $mediaId = absint($props['mediaId'] ?? $props['attachmentId'] ?? 0);
            if ($mediaId > 0) {
                continue;
            }
            $url = self::eligibleUrl((string) ($props['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            if (!isset($references[$url])) {
                $references[$url] = ['alt' => sanitize_text_field((string) ($props['alt'] ?? ''))];
            }
        }
    }

    /** @return list<string> */
    private static function sourceBases(array $site): array
    {
        $source = is_array($site['source'] ?? null) ? $site['source'] : [];
        $bases = [];
        foreach (['homeUrl', 'siteUrl'] as $key) {
            $url = esc_url_raw((string) ($source[$key] ?? ''));
            if ($url === '') {
                continue;
            }
            $parts = wp_parse_url($url);
            if (!is_array($parts) || empty($parts['host'])) {
                continue;
            }
            $scheme = strtolower((string) ($parts['scheme'] ?? 'https')) === 'http' ? 'http' : 'https';
            $host = strtolower((string) $parts['host']);
            $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
            $base = $scheme . '://' . $host . $port;
            if (!in_array($base, $bases, true)) {
                $bases[] = $base;
            }
        }
        return $bases;
    }

    private static function eligibleUrl(string $url): string
    {
        $url = esc_url_raw(trim($url));
        if ($url === '') {
            return '';
        }
        $parts = wp_parse_url($url);
        if (!is_array($parts)) {
            return '';
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $path = (string) ($parts['path'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true) || !str_starts_with($path, '/wp-content/uploads/')) {
            return '';
        }
        $filename = sanitize_file_name(wp_basename($path));
        if ($filename === '') {
            return '';
        }
        $fileType = wp_check_filetype($filename);
        $mime = sanitize_mime_type((string) ($fileType['type'] ?? ''));
        if ($mime === '' || !str_starts_with($mime, 'image/') || $mime === 'image/svg+xml') {
            return '';
        }
        return $url;
    }

    /**
     * @param list<string> $sourceBases
     * @return array{record:array<string,mixed>,tempPath:string}
     */
    private static function download(string $originalUrl, array $sourceBases, int $sourceId, string $alt): array
    {
        $parts = wp_parse_url($originalUrl);
        if (!is_array($parts)) {
            throw new \RuntimeException('URL kunne ikke fortolkes.');
        }
        $path = (string) ($parts['path'] ?? '');
        $filename = sanitize_file_name(wp_basename($path));
        if ($filename === '') {
            throw new \RuntimeException('Filnavn mangler.');
        }

        $candidates = [];
        foreach ($sourceBases as $base) {
            $candidate = rtrim($base, '/') . '/' . ltrim($path, '/');
            if (!in_array($candidate, $candidates, true)) {
                $candidates[] = $candidate;
            }
        }
        if (!in_array($originalUrl, $candidates, true)) {
            $candidates[] = $originalUrl;
        }

        $lastError = 'ingen gyldig kilde svarede';
        foreach ($candidates as $candidate) {
            $validated = wp_http_validate_url($candidate);
            if (!is_string($validated) || $validated === '') {
                $lastError = 'URL blev afvist af WordPress sikkerhedsvalidering';
                continue;
            }

            $temp = wp_tempnam($filename);
            if (!is_string($temp) || $temp === '') {
                throw new \RuntimeException('Midlertidig fil kunne ikke oprettes.');
            }

            $response = wp_safe_remote_get($validated, [
                'timeout' => 20,
                'redirection' => 3,
                'stream' => true,
                'filename' => $temp,
                'limit_response_size' => self::MAX_REMOTE_BYTES + 1,
                'headers' => ['Accept' => 'image/*'],
                'user-agent' => 'Visual Designer Manager/' . VDM_VERSION,
            ]);
            if (is_wp_error($response)) {
                $lastError = $response->get_error_message();
                @unlink($temp);
                continue;
            }

            $code = (int) wp_remote_retrieve_response_code($response);
            if ($code < 200 || $code >= 300) {
                $lastError = 'HTTP ' . $code;
                @unlink($temp);
                continue;
            }

            $size = filesize($temp);
            if (!is_int($size) || $size <= 0 || $size > self::MAX_REMOTE_BYTES) {
                $lastError = 'filstørrelse er ugyldig eller for stor';
                @unlink($temp);
                continue;
            }

            $checked = wp_check_filetype_and_ext($temp, $filename);
            $mime = sanitize_mime_type((string) ($checked['type'] ?? ''));
            if ($mime === '') {
                $fallback = wp_check_filetype($filename);
                $mime = sanitize_mime_type((string) ($fallback['type'] ?? ''));
            }
            if ($mime === '' || !str_starts_with($mime, 'image/') || $mime === 'image/svg+xml') {
                $lastError = 'downloadet fil er ikke en tilladt billedtype';
                @unlink($temp);
                continue;
            }

            $headerMime = strtolower(trim((string) wp_remote_retrieve_header($response, 'content-type')));
            if (str_contains($headerMime, ';')) {
                $headerMime = trim((string) strstr($headerMime, ';', true));
            }
            if ($headerMime !== '' && (!str_starts_with($headerMime, 'image/') || $headerMime === 'image/svg+xml')) {
                $lastError = 'serveren returnerede ikke en tilladt billedtype';
                @unlink($temp);
                continue;
            }

            $sha = hash_file('sha256', $temp);
            if (!is_string($sha) || preg_match('/^[a-f0-9]{64}$/', $sha) !== 1) {
                @unlink($temp);
                throw new \RuntimeException('SHA-256 kunne ikke beregnes.');
            }

            $archivePath = 'media/files/recovered/' . $sourceId . '-' . $filename;
            return [
                'record' => [
                    'sourceId' => $sourceId,
                    'archivePath' => $archivePath,
                    'filename' => $filename,
                    'mimeType' => $mime,
                    'title' => sanitize_text_field(pathinfo($filename, PATHINFO_FILENAME)),
                    'caption' => '',
                    'description' => '',
                    'alt' => $alt,
                    'sourceUrl' => esc_url_raw($originalUrl),
                    'size' => $size,
                    'sha256' => $sha,
                ],
                'tempPath' => $temp,
            ];
        }

        throw new \RuntimeException($lastError);
    }

    /** @param array<string,int> $sourceIdsByUrl */
    private static function rewritePages(array $payload, array $sourceIdsByUrl): array
    {
        $records = is_array($payload['records'] ?? null) ? $payload['records'] : [];
        foreach ($records as $index => $record) {
            if (!is_array($record)) {
                continue;
            }
            $designer = is_array($record['visualDesigner'] ?? null) ? $record['visualDesigner'] : null;
            if ($designer === null || !is_array($designer['model'] ?? null)) {
                continue;
            }
            $designer['model'] = self::rewriteModel($designer['model'], $sourceIdsByUrl);
            $record['visualDesigner'] = $designer;
            $records[$index] = $record;
        }
        $payload['records'] = $records;
        return $payload;
    }

    /** @param array<string,int> $sourceIdsByUrl */
    private static function rewriteTemplates(array $payload, array $sourceIdsByUrl): array
    {
        $records = is_array($payload['records'] ?? null) ? $payload['records'] : [];
        foreach ($records as $index => $record) {
            if (!is_array($record) || !is_array($record['model'] ?? null)) {
                continue;
            }
            $record['model'] = self::rewriteModel($record['model'], $sourceIdsByUrl);
            $records[$index] = $record;
        }
        $payload['records'] = $records;
        return $payload;
    }

    /** @param array<string,int> $sourceIdsByUrl */
    private static function rewriteModel(array $model, array $sourceIdsByUrl): array
    {
        $nodes = is_array($model['nodes'] ?? null) ? $model['nodes'] : [];
        foreach ($nodes as $index => $node) {
            if (!is_array($node) || sanitize_key((string) ($node['type'] ?? '')) !== 'image') {
                continue;
            }
            $props = is_array($node['props'] ?? null) ? $node['props'] : [];
            if (absint($props['mediaId'] ?? $props['attachmentId'] ?? 0) > 0) {
                continue;
            }
            $url = self::eligibleUrl((string) ($props['url'] ?? ''));
            if ($url === '' || !isset($sourceIdsByUrl[$url])) {
                continue;
            }
            $props['mediaId'] = (int) $sourceIdsByUrl[$url];
            $node['props'] = $props;
            $nodes[$index] = $node;
        }
        $model['nodes'] = $nodes;
        return $model;
    }

    private static function displayName(string $url): string
    {
        $path = (string) (wp_parse_url($url, PHP_URL_PATH) ?? '');
        $filename = sanitize_file_name(wp_basename($path));
        return $filename !== '' ? $filename : 'ukendt billede';
    }
}
