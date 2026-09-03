<?php

declare(strict_types=1);

namespace VisualDesignerManager\Storage;

use VisualDesignerManager\Model\LayoutDocument;

final class TemplateRepository
{
    public const HEADER = 'header';
    public const FOOTER = 'footer';

    /** @return list<string> */
    public static function slots(): array
    {
        return [self::HEADER, self::FOOTER];
    }

    /** @return array<string,mixed> */
    public static function get(string $slot): array
    {
        self::assertSlot($slot);
        $value = get_option(self::documentKey($slot), []);
        if (!is_array($value)) {
            return LayoutDocument::empty();
        }

        try {
            return LayoutDocument::normalize($value);
        } catch (\Throwable) {
            return LayoutDocument::empty();
        }
    }

    public static function version(string $slot): int
    {
        self::assertSlot($slot);
        return max(0, (int) get_option(self::versionKey($slot), 0));
    }

    /** @param array<string,mixed> $document
     *  @return array{document:array<string,mixed>,version:int}
     */
    public static function save(string $slot, array $document, int $userId): array
    {
        self::assertSlot($slot);
        $normalized = LayoutDocument::normalize($document);
        $current = self::get($slot);
        $currentVersion = self::version($slot);

        if (wp_json_encode($current) === wp_json_encode($normalized)) {
            return ['document' => $normalized, 'version' => $currentVersion];
        }

        $history = get_option(self::historyKey($slot), []);
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
            update_option(self::historyKey($slot), array_slice($history, 0, 50), false);
        }

        $nextVersion = $currentVersion + 1;
        update_option(self::documentKey($slot), $normalized, false);
        update_option(self::versionKey($slot), $nextVersion, false);

        return ['document' => $normalized, 'version' => $nextVersion];
    }

    /** @return list<array<string,mixed>> */
    public static function history(string $slot): array
    {
        self::assertSlot($slot);
        $value = get_option(self::historyKey($slot), []);
        return is_array($value) ? array_values($value) : [];
    }

    private static function assertSlot(string $slot): void
    {
        if (!in_array($slot, self::slots(), true)) {
            throw new \InvalidArgumentException('Invalid VDM template slot.');
        }
    }

    private static function documentKey(string $slot): string
    {
        return 'vdm_template_' . $slot . '_v2';
    }

    private static function versionKey(string $slot): string
    {
        return 'vdm_template_' . $slot . '_version_v2';
    }

    private static function historyKey(string $slot): string
    {
        return 'vdm_template_' . $slot . '_history_v2';
    }

    private function __construct()
    {
    }
}
