<?php

declare(strict_types=1);

namespace VisualDesignerManager\Fields;

final class EventFieldRegistry
{
    public const OPTION = 'vdm_event_fields_v2';
    private const MAX_FIELDS = 80;

    /** @return list<array<string,mixed>> */
    public static function all(): array
    {
        $raw = get_option(self::OPTION, null);
        return is_array($raw) ? self::normalize($raw) : self::defaults();
    }

    /** @param array<int,mixed> $rows @return list<array<string,mixed>> */
    public static function normalize(array $rows): array
    {
        $out = [];
        $used = [];
        foreach (array_slice(array_values($rows), 0, self::MAX_FIELDS) as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $label = sanitize_text_field((string) ($row['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $id = sanitize_key((string) ($row['id'] ?? ''));
            if ($id === '') {
                $id = sanitize_key($label);
            }
            if ($id === '') {
                $id = 'event_field_' . ($index + 1);
            }
            $base = substr($id, 0, 54);
            $candidate = $base;
            $suffix = 2;
            while (isset($used[$candidate])) {
                $candidate = substr($base, 0, 48) . '_' . $suffix++;
            }
            $used[$candidate] = true;

            $type = strtolower((string) ($row['type'] ?? 'richtext'));
            if (!in_array($type, ['text', 'textarea', 'richtext', 'number', 'integer', 'boolean', 'date', 'datetime', 'url'], true)) {
                $type = 'text';
            }
            $out[] = [
                'id' => $candidate,
                'label' => $label,
                'type' => $type,
                'enabled' => array_key_exists('enabled', $row) ? (bool) $row['enabled'] : true,
                'required' => !empty($row['required']),
                'showCard' => !empty($row['showCard']),
                'showDetail' => array_key_exists('showDetail', $row) ? (bool) $row['showDetail'] : true,
                'order' => max(0, min(100000, (int) ($row['order'] ?? (($index + 1) * 10)))),
            ];
        }
        usort($out, static function (array $a, array $b): int {
            $cmp = ((int) $a['order']) <=> ((int) $b['order']);
            return $cmp !== 0 ? $cmp : strnatcasecmp((string) $a['label'], (string) $b['label']);
        });
        return array_values($out);
    }

    /** @param array<int,mixed> $rows */
    public static function save(array $rows): bool
    {
        return update_option(self::OPTION, self::normalize($rows), false);
    }

    /** @return array<string,array<string,mixed>> */
    public static function byId(): array
    {
        $out = [];
        foreach (self::all() as $row) {
            $out[(string) $row['id']] = $row;
        }
        return $out;
    }

    /** @return list<array<string,mixed>> */
    private static function defaults(): array
    {
        return self::normalize([
            ['id' => 'about', 'label' => 'Om arrangementet', 'type' => 'richtext', 'enabled' => true, 'required' => false, 'showCard' => false, 'showDetail' => true, 'order' => 10],
            ['id' => 'program', 'label' => 'Program', 'type' => 'richtext', 'enabled' => true, 'required' => false, 'showCard' => false, 'showDetail' => true, 'order' => 20],
            ['id' => 'practical', 'label' => 'Praktiske oplysninger', 'type' => 'richtext', 'enabled' => true, 'required' => false, 'showCard' => false, 'showDetail' => true, 'order' => 30],
        ]);
    }

    private function __construct()
    {
    }
}
