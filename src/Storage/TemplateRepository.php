<?php

declare(strict_types=1);

namespace VisualDesignerManager\Storage;

use VisualDesignerManager\Model\LayoutDocument;

/**
 * Named Header/Footer template storage.
 *
 * V2 originally exposed one global Header and one global Footer document.  This
 * repository keeps those public slot methods as compatibility aliases while
 * adding stable named templates, independent histories and per-template
 * settings. Existing single-slot V2 data is migrated non-destructively into a
 * default named template the first time the repository is used.
 */
final class TemplateRepository
{
    public const HEADER = 'header';
    public const FOOTER = 'footer';

    private const REGISTRY_OPTION = 'vdm_template_registry_v3';
    private const MIGRATION_OPTION = 'vdm_template_registry_migrated_v3';
    private const MAX_HISTORY = 50;

    /** @return list<string> */
    public static function slots(): array
    {
        return [self::HEADER, self::FOOTER];
    }

    /** @return list<array<string,mixed>> */
    public static function all(string $slot): array
    {
        self::assertSlot($slot);
        self::ensureInitialized();
        $registry = self::registry();
        $rows = is_array($registry[$slot] ?? null) ? array_values($registry[$slot]) : [];
        usort($rows, static function (array $a, array $b): int {
            if (!empty($a['isDefault']) !== !empty($b['isDefault'])) {
                return !empty($a['isDefault']) ? -1 : 1;
            }
            return strnatcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });
        foreach ($rows as &$row) {
            $id = sanitize_key((string) ($row['id'] ?? ''));
            $row['version'] = $id !== '' ? self::versionTemplate($slot, $id) : 0;
            $row['usageCount'] = $id !== '' ? TemplateAssignmentRepository::usageCount($slot, $id) : 0;
        }
        unset($row);
        return $rows;
    }

    /** @return array<string,mixed>|null */
    public static function meta(string $slot, string $id): ?array
    {
        self::assertSlot($slot);
        self::ensureInitialized();
        $id = sanitize_key($id);
        foreach ((array) (self::registry()[$slot] ?? []) as $row) {
            if (is_array($row) && sanitize_key((string) ($row['id'] ?? '')) === $id) {
                return self::normalizeMeta($slot, $row);
            }
        }
        return null;
    }

    public static function exists(string $slot, string $id): bool
    {
        return self::meta($slot, $id) !== null;
    }

    public static function defaultId(string $slot): string
    {
        self::assertSlot($slot);
        self::ensureInitialized();
        $rows = (array) (self::registry()[$slot] ?? []);
        foreach ($rows as $row) {
            if (is_array($row) && !empty($row['isDefault'])) {
                return sanitize_key((string) ($row['id'] ?? ''));
            }
        }
        foreach ($rows as $row) {
            if (is_array($row) && !empty($row['active'])) {
                return sanitize_key((string) ($row['id'] ?? ''));
            }
        }
        return isset($rows[0]) && is_array($rows[0]) ? sanitize_key((string) ($rows[0]['id'] ?? '')) : '';
    }

    public static function create(string $slot, string $name): string
    {
        self::assertSlot($slot);
        self::ensureInitialized();
        $name = sanitize_text_field($name);
        if ($name === '') {
            $name = $slot === self::HEADER ? 'Ny Header' : 'Ny Footer';
        }
        $id = self::newId($slot);
        $registry = self::registry();
        $rows = is_array($registry[$slot] ?? null) ? array_values($registry[$slot]) : [];
        $rows[] = self::normalizeMeta($slot, [
            'id' => $id,
            'name' => $name,
            'active' => true,
            'isDefault' => $rows === [],
            'settings' => self::defaultSettings($slot),
            'createdUtc' => gmdate('c'),
            'updatedUtc' => gmdate('c'),
        ]);
        $registry[$slot] = $rows;
        self::saveRegistry($registry);
        update_option(self::documentKeyForId($slot, $id), LayoutDocument::empty(), false);
        update_option(self::versionKeyForId($slot, $id), 0, false);
        update_option(self::historyKeyForId($slot, $id), [], false);
        return $id;
    }

    public static function duplicate(string $slot, string $id, ?string $name = null): string
    {
        self::assertSlot($slot);
        self::ensureInitialized();
        $meta = self::meta($slot, $id);
        if ($meta === null) {
            throw new \InvalidArgumentException('Template findes ikke.');
        }
        $newId = self::create($slot, $name !== null && trim($name) !== '' ? $name : ((string) $meta['name'] . ' – kopi'));
        $document = self::getTemplate($slot, $id);
        if (($document['nodes'] ?? []) !== []) {
            self::saveTemplate($slot, $newId, $document, get_current_user_id());
        }
        self::updateMeta($slot, $newId, [
            'settings' => is_array($meta['settings'] ?? null) ? $meta['settings'] : self::defaultSettings($slot),
        ]);
        return $newId;
    }

    public static function rename(string $slot, string $id, string $name): void
    {
        $name = sanitize_text_field($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Templatenavn må ikke være tomt.');
        }
        self::updateMeta($slot, $id, ['name' => $name]);
    }

    public static function setActive(string $slot, string $id, bool $active): void
    {
        $meta = self::meta($slot, $id);
        if ($meta === null) {
            throw new \InvalidArgumentException('Template findes ikke.');
        }
        if (!empty($meta['isDefault']) && !$active) {
            throw new \InvalidArgumentException('Standardtemplaten kan ikke deaktiveres. Vælg først en anden standard.');
        }
        self::updateMeta($slot, $id, ['active' => $active]);
    }

    public static function setDefault(string $slot, string $id): void
    {
        self::assertSlot($slot);
        self::ensureInitialized();
        if (!self::exists($slot, $id)) {
            throw new \InvalidArgumentException('Template findes ikke.');
        }
        $registry = self::registry();
        $rows = is_array($registry[$slot] ?? null) ? array_values($registry[$slot]) : [];
        foreach ($rows as &$row) {
            if (!is_array($row)) {
                continue;
            }
            $rowId = sanitize_key((string) ($row['id'] ?? ''));
            $row['isDefault'] = $rowId === sanitize_key($id);
            if ($row['isDefault']) {
                $row['active'] = true;
            }
            $row['updatedUtc'] = gmdate('c');
        }
        unset($row);
        $registry[$slot] = $rows;
        self::saveRegistry($registry);
        self::syncLegacyDefaultOptions($slot);
    }

    /** @param array<string,mixed> $settings */
    public static function saveSettings(string $slot, string $id, array $settings): void
    {
        $meta = self::meta($slot, $id);
        if ($meta === null) {
            throw new \InvalidArgumentException('Template findes ikke.');
        }
        $normalized = self::normalizeSettings($slot, $settings);
        self::updateMeta($slot, $id, ['settings' => $normalized]);
    }

    /** @return array<string,mixed> */
    public static function settings(string $slot, string $id): array
    {
        $meta = self::meta($slot, $id);
        return is_array($meta['settings'] ?? null)
            ? self::normalizeSettings($slot, $meta['settings'])
            : self::defaultSettings($slot);
    }

    /** Compatibility alias: returns the current default named template. */
    public static function get(string $slot): array
    {
        $id = self::defaultId($slot);
        return $id !== '' ? self::getTemplate($slot, $id) : LayoutDocument::empty();
    }

    public static function version(string $slot): int
    {
        $id = self::defaultId($slot);
        return $id !== '' ? self::versionTemplate($slot, $id) : 0;
    }

    /** Compatibility alias: saves the current default named template. */
    public static function save(string $slot, array $document, int $userId): array
    {
        $id = self::defaultId($slot);
        if ($id === '') {
            $id = self::create($slot, $slot === self::HEADER ? 'Header – Standard' : 'Footer – Standard');
            self::setDefault($slot, $id);
        }
        return self::saveTemplate($slot, $id, $document, $userId);
    }

    /** Compatibility alias: returns history for the current default. */
    public static function history(string $slot): array
    {
        $id = self::defaultId($slot);
        return $id !== '' ? self::historyTemplate($slot, $id) : [];
    }

    /** @return array<string,mixed> */
    public static function getTemplate(string $slot, string $id): array
    {
        self::assertSlot($slot);
        self::ensureInitialized();
        $id = sanitize_key($id);
        if ($id === '' || !self::exists($slot, $id)) {
            return LayoutDocument::empty();
        }
        $value = get_option(self::documentKeyForId($slot, $id), []);
        if (!is_array($value)) {
            return LayoutDocument::empty();
        }
        try {
            return LayoutDocument::normalize($value);
        } catch (\Throwable) {
            return LayoutDocument::empty();
        }
    }

    public static function versionTemplate(string $slot, string $id): int
    {
        self::assertSlot($slot);
        self::ensureInitialized();
        $id = sanitize_key($id);
        if ($id === '' || !self::exists($slot, $id)) {
            return 0;
        }
        return max(0, (int) get_option(self::versionKeyForId($slot, $id), 0));
    }

    /** @param array<string,mixed> $document
     *  @return array{document:array<string,mixed>,version:int}
     */
    public static function saveTemplate(string $slot, string $id, array $document, int $userId): array
    {
        self::assertSlot($slot);
        self::ensureInitialized();
        $id = sanitize_key($id);
        if ($id === '' || !self::exists($slot, $id)) {
            throw new \InvalidArgumentException('Template findes ikke.');
        }

        $normalized = LayoutDocument::normalize($document);
        $current = self::getTemplate($slot, $id);
        $currentVersion = self::versionTemplate($slot, $id);
        if (wp_json_encode($current) === wp_json_encode($normalized)) {
            return ['document' => $normalized, 'version' => $currentVersion];
        }

        $history = get_option(self::historyKeyForId($slot, $id), []);
        if (!is_array($history)) {
            $history = [];
        }
        if (($current['nodes'] ?? []) !== []) {
            array_unshift($history, [
                'version' => $currentVersion,
                'savedAt' => gmdate('c'),
                'savedBy' => $userId,
                'document' => $current,
            ]);
            update_option(self::historyKeyForId($slot, $id), array_slice($history, 0, self::MAX_HISTORY), false);
        }

        $nextVersion = $currentVersion + 1;
        update_option(self::documentKeyForId($slot, $id), $normalized, false);
        update_option(self::versionKeyForId($slot, $id), $nextVersion, false);
        self::updateMeta($slot, $id, ['updatedUtc' => gmdate('c')]);

        if ($id === self::defaultId($slot)) {
            self::syncLegacyDefaultOptions($slot);
        }
        return ['document' => $normalized, 'version' => $nextVersion];
    }

    /** @return list<array<string,mixed>> */
    public static function historyTemplate(string $slot, string $id): array
    {
        self::assertSlot($slot);
        self::ensureInitialized();
        $id = sanitize_key($id);
        if ($id === '' || !self::exists($slot, $id)) {
            return [];
        }
        $value = get_option(self::historyKeyForId($slot, $id), []);
        return is_array($value) ? array_values($value) : [];
    }

    /** @param array<string,mixed> $patch */
    private static function updateMeta(string $slot, string $id, array $patch): void
    {
        self::assertSlot($slot);
        self::ensureInitialized();
        $id = sanitize_key($id);
        $registry = self::registry();
        $rows = is_array($registry[$slot] ?? null) ? array_values($registry[$slot]) : [];
        $found = false;
        foreach ($rows as &$row) {
            if (!is_array($row) || sanitize_key((string) ($row['id'] ?? '')) !== $id) {
                continue;
            }
            $row = self::normalizeMeta($slot, array_merge($row, $patch, ['updatedUtc' => gmdate('c')]));
            $found = true;
            break;
        }
        unset($row);
        if (!$found) {
            throw new \InvalidArgumentException('Template findes ikke.');
        }
        $registry[$slot] = $rows;
        self::saveRegistry($registry);
    }

    private static function ensureInitialized(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $registry = get_option(self::REGISTRY_OPTION, null);
        if (is_array($registry) && isset($registry[self::HEADER], $registry[self::FOOTER])) {
            return;
        }

        $newRegistry = [self::HEADER => [], self::FOOTER => []];
        foreach (self::slots() as $slot) {
            $id = self::newId($slot);
            $name = $slot === self::HEADER ? 'Header – Standard' : 'Footer – Standard';
            $legacyDocument = get_option(self::legacyDocumentKey($slot), []);
            $legacyVersion = max(0, (int) get_option(self::legacyVersionKey($slot), 0));
            $legacyHistory = get_option(self::legacyHistoryKey($slot), []);
            if (!is_array($legacyDocument)) {
                $legacyDocument = [];
            }
            if (!is_array($legacyHistory)) {
                $legacyHistory = [];
            }
            try {
                $legacyDocument = LayoutDocument::normalize($legacyDocument);
            } catch (\Throwable) {
                $legacyDocument = LayoutDocument::empty();
            }

            $newRegistry[$slot][] = self::normalizeMeta($slot, [
                'id' => $id,
                'name' => $name,
                'active' => true,
                'isDefault' => true,
                'settings' => self::defaultSettings($slot),
                'createdUtc' => gmdate('c'),
                'updatedUtc' => gmdate('c'),
            ]);
            update_option(self::documentKeyForId($slot, $id), $legacyDocument, false);
            update_option(self::versionKeyForId($slot, $id), $legacyVersion, false);
            update_option(self::historyKeyForId($slot, $id), array_slice($legacyHistory, 0, self::MAX_HISTORY), false);
        }
        update_option(self::REGISTRY_OPTION, $newRegistry, false);
        update_option(self::MIGRATION_OPTION, [
            'migratedUtc' => gmdate('c'),
            'from' => 'single-slot-v2',
            'to' => 'named-template-v3',
        ], false);
    }

    /** @return array<string,list<array<string,mixed>>> */
    private static function registry(): array
    {
        $value = get_option(self::REGISTRY_OPTION, []);
        $registry = [self::HEADER => [], self::FOOTER => []];
        if (is_array($value)) {
            foreach (self::slots() as $slot) {
                $registry[$slot] = is_array($value[$slot] ?? null) ? array_values($value[$slot]) : [];
            }
        }
        return $registry;
    }

    /** @param array<string,mixed> $registry */
    private static function saveRegistry(array $registry): void
    {
        update_option(self::REGISTRY_OPTION, $registry, false);
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private static function normalizeMeta(string $slot, array $row): array
    {
        $id = sanitize_key((string) ($row['id'] ?? ''));
        $name = sanitize_text_field((string) ($row['name'] ?? ''));
        return [
            'id' => $id,
            'slot' => $slot,
            'name' => $name !== '' ? $name : ($slot === self::HEADER ? 'Header' : 'Footer'),
            'active' => !array_key_exists('active', $row) || (bool) $row['active'],
            'isDefault' => !empty($row['isDefault']),
            'settings' => self::normalizeSettings($slot, is_array($row['settings'] ?? null) ? $row['settings'] : []),
            'createdUtc' => sanitize_text_field((string) ($row['createdUtc'] ?? gmdate('c'))),
            'updatedUtc' => sanitize_text_field((string) ($row['updatedUtc'] ?? gmdate('c'))),
        ];
    }

    /** @param array<string,mixed> $settings @return array<string,mixed> */
    private static function normalizeSettings(string $slot, array $settings): array
    {
        $defaults = self::defaultSettings($slot);
        return [
            'sticky' => $slot === self::HEADER ? !empty($settings['sticky']) : false,
            'overlay' => $slot === self::HEADER ? !empty($settings['overlay']) : false,
            'contentWidth' => max(320, min(2400, (int) ($settings['contentWidth'] ?? $defaults['contentWidth']))),
        ];
    }

    /** @return array<string,mixed> */
    private static function defaultSettings(string $slot): array
    {
        return [
            'sticky' => false,
            'overlay' => false,
            'contentWidth' => 1440,
        ];
    }

    private static function newId(string $slot): string
    {
        do {
            $id = sanitize_key($slot . '-' . substr(str_replace('-', '', wp_generate_uuid4()), 0, 12));
        } while ($id === '' || self::existsWithoutInit($slot, $id));
        return $id;
    }

    private static function existsWithoutInit(string $slot, string $id): bool
    {
        $value = get_option(self::REGISTRY_OPTION, []);
        if (!is_array($value) || !is_array($value[$slot] ?? null)) {
            return false;
        }
        foreach ($value[$slot] as $row) {
            if (is_array($row) && sanitize_key((string) ($row['id'] ?? '')) === $id) {
                return true;
            }
        }
        return false;
    }

    private static function syncLegacyDefaultOptions(string $slot): void
    {
        $id = self::defaultId($slot);
        if ($id === '') {
            return;
        }
        update_option(self::legacyDocumentKey($slot), self::getTemplate($slot, $id), false);
        update_option(self::legacyVersionKey($slot), self::versionTemplate($slot, $id), false);
        update_option(self::legacyHistoryKey($slot), self::historyTemplate($slot, $id), false);
    }

    private static function documentKeyForId(string $slot, string $id): string
    {
        return 'vdm_template_' . $slot . '_' . sanitize_key($id) . '_v3';
    }

    private static function versionKeyForId(string $slot, string $id): string
    {
        return 'vdm_template_' . $slot . '_' . sanitize_key($id) . '_version_v3';
    }

    private static function historyKeyForId(string $slot, string $id): string
    {
        return 'vdm_template_' . $slot . '_' . sanitize_key($id) . '_history_v3';
    }

    private static function legacyDocumentKey(string $slot): string
    {
        return 'vdm_template_' . $slot . '_v2';
    }

    private static function legacyVersionKey(string $slot): string
    {
        return 'vdm_template_' . $slot . '_version_v2';
    }

    private static function legacyHistoryKey(string $slot): string
    {
        return 'vdm_template_' . $slot . '_history_v2';
    }

    private static function assertSlot(string $slot): void
    {
        if (!in_array($slot, self::slots(), true)) {
            throw new \InvalidArgumentException('Invalid VDM template slot.');
        }
    }

    private function __construct()
    {
    }
}
