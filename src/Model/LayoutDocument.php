<?php

declare(strict_types=1);

namespace VisualDesignerManager\Model;

final class LayoutDocument
{
    public const SCHEMA_VERSION = 2;

    /** @return array{schemaVersion:int,nodes:list<array<string,mixed>>,settings:array<string,mixed>} */
    public static function empty(): array
    {
        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'nodes' => [],
            'settings' => [
                'rowPixelSize' => 8,
                'breakpoints' => Breakpoint::widths(),
            ],
        ];
    }

    /** @param array<string,mixed> $document */
    public static function isValid(array $document): bool
    {
        return ($document['schemaVersion'] ?? null) === self::SCHEMA_VERSION
            && isset($document['nodes'])
            && is_array($document['nodes'])
            && isset($document['settings'])
            && is_array($document['settings']);
    }

    private function __construct()
    {
    }
}
