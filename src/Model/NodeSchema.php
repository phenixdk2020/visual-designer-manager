<?php

declare(strict_types=1);

namespace VisualDesignerManager\Model;

final class NodeSchema
{
    public const SECTION = 'section';
    public const CONTAINER = 'container';
    public const TEXT = 'text';
    public const IMAGE = 'image';
    public const BUTTON = 'button';
    public const SPACER = 'spacer';
    public const DIVIDER = 'divider';

    /** @return list<string> */
    public static function types(): array
    {
        return [
            self::SECTION,
            self::CONTAINER,
            self::TEXT,
            self::IMAGE,
            self::BUTTON,
            self::SPACER,
            self::DIVIDER,
        ];
    }

    /** @return array<string,mixed> */
    public static function create(string $type, ?string $parentId = null): array
    {
        if (!in_array($type, self::types(), true)) {
            throw new \InvalidArgumentException('Unknown VDM node type.');
        }

        return [
            'id' => wp_generate_uuid4(),
            'type' => $type,
            'parentId' => $parentId,
            'order' => 0,
            'props' => self::defaultProps($type),
            'responsive' => [
                Breakpoint::DESKTOP => self::defaultGeometry($type),
            ],
        ];
    }

    /** @param array<string,mixed> $node
     *  @return array<string,mixed>
     */
    public static function normalize(array $node): array
    {
        $type = sanitize_key((string) ($node['type'] ?? ''));
        if (!in_array($type, self::types(), true)) {
            throw new \InvalidArgumentException('Invalid VDM node type.');
        }

        $id = sanitize_key((string) ($node['id'] ?? ''));
        if ($id === '') {
            $id = wp_generate_uuid4();
        }

        $parentId = $node['parentId'] ?? null;
        if ($parentId !== null) {
            $parentId = sanitize_key((string) $parentId);
            if ($parentId === '') {
                $parentId = null;
            }
        }

        $responsive = [];
        $rawResponsive = is_array($node['responsive'] ?? null) ? $node['responsive'] : [];
        foreach (Breakpoint::ordered() as $breakpoint) {
            if (!isset($rawResponsive[$breakpoint]) || !is_array($rawResponsive[$breakpoint])) {
                continue;
            }
            $responsive[$breakpoint] = self::normalizeGeometry($rawResponsive[$breakpoint]);
        }

        if (!isset($responsive[Breakpoint::DESKTOP])) {
            $responsive[Breakpoint::DESKTOP] = self::defaultGeometry($type);
        }

        return [
            'id' => $id,
            'type' => $type,
            'parentId' => $parentId,
            'order' => max(0, (int) ($node['order'] ?? 0)),
            'props' => self::normalizeProps($type, is_array($node['props'] ?? null) ? $node['props'] : []),
            'responsive' => $responsive,
        ];
    }

    /** @param array<string,mixed> $geometry
     *  @return array{x:int,y:int,w:int,h:int}
     */
    public static function normalizeGeometry(array $geometry): array
    {
        $x = max(0, min(11, (int) ($geometry['x'] ?? 0)));
        $w = max(1, min(12 - $x, (int) ($geometry['w'] ?? 12)));

        return [
            'x' => $x,
            'y' => max(0, min(2000, (int) ($geometry['y'] ?? 0))),
            'w' => $w,
            'h' => max(1, min(2000, (int) ($geometry['h'] ?? 4))),
        ];
    }

    /** @return array{x:int,y:int,w:int,h:int} */
    private static function defaultGeometry(string $type): array
    {
        return match ($type) {
            self::SECTION => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 36],
            self::CONTAINER => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 24],
            self::TEXT => ['x' => 0, 'y' => 0, 'w' => 6, 'h' => 6],
            self::IMAGE => ['x' => 0, 'y' => 0, 'w' => 6, 'h' => 18],
            self::BUTTON => ['x' => 0, 'y' => 0, 'w' => 3, 'h' => 6],
            self::SPACER => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 4],
            self::DIVIDER => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 2],
            default => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 4],
        };
    }

    /** @return array<string,mixed> */
    private static function defaultProps(string $type): array
    {
        return match ($type) {
            self::SECTION => [
                'background' => '#ffffff',
                'padding' => 0,
                'autoHeight' => true,
                'minHeightRows' => 36,
            ],
            self::CONTAINER => [
                'background' => 'transparent',
                'padding' => 16,
                'autoHeight' => true,
                'minHeightRows' => 24,
            ],
            self::TEXT => ['content' => '<p>Tekst</p>', 'color' => '#222222', 'fontSize' => 18],
            self::IMAGE => ['attachmentId' => 0, 'alt' => '', 'objectFit' => 'cover'],
            self::BUTTON => [
                'label' => 'Knap',
                'url' => '#',
                'background' => '#2f4858',
                'color' => '#ffffff',
                'radius' => 4,
            ],
            self::DIVIDER => ['color' => '#d0d0d0', 'thickness' => 1],
            default => [],
        };
    }

    /** @param array<string,mixed> $props
     *  @return array<string,mixed>
     */
    private static function normalizeProps(string $type, array $props): array
    {
        $defaults = self::defaultProps($type);

        if ($type === self::SECTION || $type === self::CONTAINER) {
            return [
                'background' => self::color((string) ($props['background'] ?? $defaults['background']), (string) $defaults['background']),
                'padding' => max(0, min(120, (int) ($props['padding'] ?? $defaults['padding']))),
                'autoHeight' => !array_key_exists('autoHeight', $props) || (bool) $props['autoHeight'],
                'minHeightRows' => max(1, min(2000, (int) ($props['minHeightRows'] ?? $defaults['minHeightRows']))),
            ];
        }

        if ($type === self::TEXT) {
            return [
                'content' => wp_kses_post((string) ($props['content'] ?? $defaults['content'])),
                'color' => self::color((string) ($props['color'] ?? $defaults['color']), (string) $defaults['color']),
                'fontSize' => max(8, min(120, (int) ($props['fontSize'] ?? $defaults['fontSize']))),
            ];
        }

        if ($type === self::IMAGE) {
            $fit = (string) ($props['objectFit'] ?? $defaults['objectFit']);
            if (!in_array($fit, ['cover', 'contain'], true)) {
                $fit = 'cover';
            }
            return [
                'attachmentId' => absint($props['attachmentId'] ?? 0),
                'alt' => sanitize_text_field((string) ($props['alt'] ?? '')),
                'objectFit' => $fit,
            ];
        }

        if ($type === self::BUTTON) {
            return [
                'label' => sanitize_text_field((string) ($props['label'] ?? $defaults['label'])),
                'url' => esc_url_raw((string) ($props['url'] ?? $defaults['url'])),
                'background' => self::color((string) ($props['background'] ?? $defaults['background']), (string) $defaults['background']),
                'color' => self::color((string) ($props['color'] ?? $defaults['color']), (string) $defaults['color']),
                'radius' => max(0, min(80, (int) ($props['radius'] ?? $defaults['radius']))),
            ];
        }

        if ($type === self::DIVIDER) {
            return [
                'color' => self::color((string) ($props['color'] ?? $defaults['color']), (string) $defaults['color']),
                'thickness' => max(1, min(20, (int) ($props['thickness'] ?? $defaults['thickness']))),
            ];
        }

        return [];
    }

    private static function color(string $value, string $fallback): string
    {
        if ($value === 'transparent') {
            return 'transparent';
        }

        $color = sanitize_hex_color($value);
        return is_string($color) ? $color : $fallback;
    }

    private function __construct()
    {
    }
}
