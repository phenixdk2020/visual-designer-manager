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

    /** @param array<string,mixed> $document
     *  @return array{schemaVersion:int,nodes:list<array<string,mixed>>,settings:array<string,mixed>}
     */
    public static function normalize(array $document): array
    {
        $rawNodes = is_array($document['nodes'] ?? null) ? $document['nodes'] : [];
        $nodes = [];
        foreach ($rawNodes as $node) {
            if (!is_array($node)) {
                continue;
            }
            $nodes[] = NodeSchema::normalize($node);
        }

        usort($nodes, static fn(array $a, array $b): int => ((int) $a['order']) <=> ((int) $b['order']));
        Hierarchy::assertValid($nodes);

        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'nodes' => $nodes,
            'settings' => [
                'rowPixelSize' => 8,
                'breakpoints' => Breakpoint::widths(),
            ],
        ];
    }

    /** @param array<string,mixed> $document */
    public static function isValid(array $document): bool
    {
        try {
            self::normalize($document);
            return ($document['schemaVersion'] ?? self::SCHEMA_VERSION) === self::SCHEMA_VERSION;
        } catch (\Throwable) {
            return false;
        }
    }

    private function __construct()
    {
    }
}
