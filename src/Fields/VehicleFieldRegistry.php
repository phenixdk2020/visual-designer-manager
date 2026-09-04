<?php

declare(strict_types=1);

namespace VisualDesignerManager\Fields;

final class VehicleFieldRegistry
{
    public const OPTION = 'vdm_vehicle_fields_v2';
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
                $id = 'field_' . ($index + 1);
            }
            $base = substr($id, 0, 54);
            $candidate = $base;
            $suffix = 2;
            while (isset($used[$candidate])) {
                $candidate = substr($base, 0, 48) . '_' . $suffix++;
            }
            $used[$candidate] = true;

            $type = strtolower((string) ($row['type'] ?? 'text'));
            if (!in_array($type, ['text', 'textarea', 'richtext', 'number', 'integer', 'boolean', 'date'], true)) {
                $type = 'text';
            }
            $out[] = [
                'id' => $candidate,
                'label' => $label,
                'type' => $type,
                'unit' => sanitize_text_field((string) ($row['unit'] ?? '')),
                'enabled' => array_key_exists('enabled', $row) ? (bool) $row['enabled'] : true,
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

    /** @return list<array<string,mixed>> */
    private static function defaults(): array
    {
        return self::normalize([
            ['id' => 'manufacturer', 'label' => 'Producent', 'type' => 'text', 'unit' => '', 'enabled' => true, 'order' => 10],
            ['id' => 'model', 'label' => 'Model', 'type' => 'text', 'unit' => '', 'enabled' => true, 'order' => 20],
            ['id' => 'year', 'label' => 'Årgang', 'type' => 'integer', 'unit' => '', 'enabled' => true, 'order' => 30],
            ['id' => 'engine', 'label' => 'Motor', 'type' => 'text', 'unit' => '', 'enabled' => true, 'order' => 40],
            ['id' => 'weight', 'label' => 'Vægt', 'type' => 'text', 'unit' => '', 'enabled' => true, 'order' => 50],
            ['id' => 'crew', 'label' => 'Besætning', 'type' => 'text', 'unit' => '', 'enabled' => true, 'order' => 60],
        ]);
    }

    private function __construct()
    {
    }
}
