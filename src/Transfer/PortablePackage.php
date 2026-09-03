<?php

declare(strict_types=1);

namespace VisualDesignerManager\Transfer;

final class PortablePackage
{
    public const FORMAT = 'Visual Designer Manager Portable Site';
    public const SCHEMA_VERSION = '2.0';
    public const MAX_FILES = 5000;
    public const MAX_ENTRY_BYTES = 268435456;
    public const MAX_TOTAL_BYTES = 1610612736;
    public const MAX_JSON_BYTES = 67108864;

    /** @return list<string> */
    public static function requiredPaths(): array
    {
        return [
            'site.json',
            'content/pages.json',
            'content/events.json',
            'content/vehicles.json',
            'content/galleries.json',
            'content/menus.json',
            'templates/header.json',
            'templates/footer.json',
            'settings/site-design.json',
            'media/index.json',
        ];
    }

    /** @param mixed $value */
    public static function json(mixed $value): string
    {
        $json = wp_json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            throw new \RuntimeException('Portable package JSON could not be encoded.');
        }
        return $json . "\n";
    }

    /** @param list<array{path:string,size:int,sha256:string}> $files */
    public static function contentHash(array $files): string
    {
        usort($files, static fn(array $a, array $b): int => strcmp($a['path'], $b['path']));
        $context = hash_init('sha256');
        foreach ($files as $file) {
            hash_update($context, $file['path'] . "\0" . $file['sha256'] . "\0" . (string) $file['size'] . "\n");
        }
        return hash_final($context);
    }

    public static function safePath(string $path): bool
    {
        if ($path === '' || str_contains($path, "\0") || str_contains($path, '\\')) {
            return false;
        }
        if ($path[0] === '/' || preg_match('/^[A-Za-z]:/', $path) === 1) {
            return false;
        }
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                return false;
            }
        }
        return true;
    }

    /** @return array{manifest:array<string,mixed>,summary:array<string,int>,warnings:list<string>} */
    public static function inspect(string $zipPath): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('PHP ZipArchive is required for VDM portable packages.');
        }
        if (!is_file($zipPath) || !is_readable($zipPath)) {
            throw new \RuntimeException('Portable package file is not readable.');
        }

        $zip = new \ZipArchive();
        $opened = $zip->open($zipPath, \ZipArchive::RDONLY);
        if ($opened !== true) {
            throw new \RuntimeException('Portable ZIP could not be opened.');
        }

        try {
            if ($zip->numFiles <= 0 || $zip->numFiles > self::MAX_FILES + 32) {
                throw new \RuntimeException('Portable ZIP contains an invalid number of entries.');
            }

            $actual = [];
            $totalSize = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i, \ZipArchive::FL_UNCHANGED);
                if (!is_array($stat)) {
                    throw new \RuntimeException('Portable ZIP entry metadata could not be read.');
                }
                $name = (string) ($stat['name'] ?? '');
                if (str_ends_with($name, '/')) {
                    continue;
                }
                if (!self::safePath($name)) {
                    throw new \RuntimeException('Unsafe ZIP path rejected: ' . $name);
                }
                $folded = strtolower($name);
                if (isset($actual[$folded])) {
                    throw new \RuntimeException('Duplicate ZIP path rejected: ' . $name);
                }
                $size = max(0, (int) ($stat['size'] ?? 0));
                if ($size > self::MAX_ENTRY_BYTES) {
                    throw new \RuntimeException('ZIP entry exceeds the allowed size: ' . $name);
                }
                $totalSize += $size;
                if ($totalSize > self::MAX_TOTAL_BYTES) {
                    throw new \RuntimeException('Portable ZIP exceeds the allowed total uncompressed size.');
                }

                if (method_exists($zip, 'getExternalAttributesIndex')) {
                    $opsys = 0;
                    $attributes = 0;
                    if ($zip->getExternalAttributesIndex($i, $opsys, $attributes)) {
                        $mode = ($attributes >> 16) & 0xF000;
                        if ($mode === 0xA000) {
                            throw new \RuntimeException('Symbolic links are not allowed in portable ZIP packages.');
                        }
                    }
                }
                $actual[$folded] = ['path' => $name, 'size' => $size];
            }

            if (!isset($actual['manifest.json'])) {
                throw new \RuntimeException('Portable package is missing manifest.json.');
            }

            $manifest = self::readJson($zip, 'manifest.json', 2097152);
            if ((string) ($manifest['format'] ?? '') !== self::FORMAT) {
                throw new \RuntimeException('ZIP is not a Visual Designer Manager Portable Site package.');
            }
            $schema = (string) ($manifest['schemaVersion'] ?? '');
            if ($schema !== self::SCHEMA_VERSION) {
                if ($schema === '1.0') {
                    throw new \RuntimeException('Portable schema 1.0 requires the controlled RC migration importer; beta.3 accepts native V2 schema 2.0 packages.');
                }
                throw new \RuntimeException('Unsupported portable schema version: ' . $schema);
            }

            $rawFiles = is_array($manifest['files'] ?? null) ? $manifest['files'] : [];
            if ($rawFiles === [] || count($rawFiles) > self::MAX_FILES) {
                throw new \RuntimeException('Portable manifest file list is invalid.');
            }

            $listed = [];
            $normalizedFiles = [];
            foreach ($rawFiles as $rawFile) {
                if (!is_array($rawFile)) {
                    throw new \RuntimeException('Portable manifest contains an invalid file record.');
                }
                $path = (string) ($rawFile['path'] ?? '');
                $size = (int) ($rawFile['size'] ?? -1);
                $sha = strtolower((string) ($rawFile['sha256'] ?? ''));
                if (!self::safePath($path) || $path === 'manifest.json' || $size < 0 || $size > self::MAX_ENTRY_BYTES || preg_match('/^[a-f0-9]{64}$/', $sha) !== 1) {
                    throw new \RuntimeException('Portable manifest contains an unsafe file record.');
                }
                $folded = strtolower($path);
                if (isset($listed[$folded])) {
                    throw new \RuntimeException('Portable manifest contains duplicate file paths.');
                }
                $listed[$folded] = true;
                if (!isset($actual[$folded])) {
                    throw new \RuntimeException('Manifest file is missing from ZIP: ' . $path);
                }
                if ((int) $actual[$folded]['size'] !== $size) {
                    throw new \RuntimeException('Size mismatch for portable file: ' . $path);
                }
                $calculated = self::hashEntry($zip, $path, $size);
                if (!hash_equals($sha, $calculated)) {
                    throw new \RuntimeException('SHA-256 mismatch for portable file: ' . $path);
                }
                $normalizedFiles[] = ['path' => $path, 'size' => $size, 'sha256' => $sha];
            }

            foreach ($actual as $folded => $record) {
                if ($folded === 'manifest.json') {
                    continue;
                }
                if (!isset($listed[$folded])) {
                    throw new \RuntimeException('Unlisted ZIP entry rejected: ' . (string) $record['path']);
                }
            }
            foreach (self::requiredPaths() as $required) {
                if (!isset($listed[strtolower($required)])) {
                    throw new \RuntimeException('Portable package is missing required file: ' . $required);
                }
            }

            $contentHash = strtolower((string) ($manifest['contentSha256'] ?? ''));
            if (preg_match('/^[a-f0-9]{64}$/', $contentHash) !== 1 || !hash_equals($contentHash, self::contentHash($normalizedFiles))) {
                throw new \RuntimeException('Portable package content digest is invalid.');
            }

            $pages = self::readJson($zip, 'content/pages.json');
            $events = self::readJson($zip, 'content/events.json');
            $vehicles = self::readJson($zip, 'content/vehicles.json');
            $galleries = self::readJson($zip, 'content/galleries.json');
            $menus = self::readJson($zip, 'content/menus.json');
            $media = self::readJson($zip, 'media/index.json');

            $summary = [
                'pages' => is_array($pages['items'] ?? null) ? count($pages['items']) : 0,
                'events' => is_array($events['items'] ?? null) ? count($events['items']) : 0,
                'vehicles' => is_array($vehicles['items'] ?? null) ? count($vehicles['items']) : 0,
                'galleries' => is_array($galleries['items'] ?? null) ? count($galleries['items']) : 0,
                'menus' => is_array($menus['items'] ?? null) ? count($menus['items']) : 0,
                'media' => is_array($media['items'] ?? null) ? count($media['items']) : 0,
            ];

            return [
                'manifest' => $manifest,
                'summary' => $summary,
                'warnings' => [],
            ];
        } finally {
            $zip->close();
        }
    }

    /** @return array<string,mixed> */
    public static function readJson(\ZipArchive $zip, string $path, int $maxBytes = self::MAX_JSON_BYTES): array
    {
        $stat = $zip->statName($path, \ZipArchive::FL_UNCHANGED);
        if (!is_array($stat)) {
            throw new \RuntimeException('Portable JSON file is missing: ' . $path);
        }
        $size = (int) ($stat['size'] ?? 0);
        if ($size < 0 || $size > $maxBytes) {
            throw new \RuntimeException('Portable JSON file is too large: ' . $path);
        }
        $value = $zip->getFromName($path, 0, \ZipArchive::FL_UNCHANGED);
        if (!is_string($value)) {
            throw new \RuntimeException('Portable JSON file could not be read: ' . $path);
        }
        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('Invalid JSON in portable file ' . $path . ': ' . $exception->getMessage());
        }
        if (!is_array($decoded)) {
            throw new \RuntimeException('Portable JSON root must be an object: ' . $path);
        }
        return $decoded;
    }

    private static function hashEntry(\ZipArchive $zip, string $path, int $expectedSize): string
    {
        $stream = $zip->getStream($path);
        if (!is_resource($stream)) {
            throw new \RuntimeException('Portable file stream could not be opened: ' . $path);
        }
        $context = hash_init('sha256');
        $bytes = 0;
        try {
            while (!feof($stream)) {
                $chunk = fread($stream, 1048576);
                if ($chunk === false) {
                    throw new \RuntimeException('Portable file stream could not be read: ' . $path);
                }
                if ($chunk === '') {
                    continue;
                }
                $bytes += strlen($chunk);
                if ($bytes > self::MAX_ENTRY_BYTES) {
                    throw new \RuntimeException('Portable file exceeds allowed size: ' . $path);
                }
                hash_update($context, $chunk);
            }
        } finally {
            fclose($stream);
        }
        if ($bytes !== $expectedSize) {
            throw new \RuntimeException('Portable file stream size mismatch: ' . $path);
        }
        return hash_final($context);
    }

    private function __construct()
    {
    }
}
