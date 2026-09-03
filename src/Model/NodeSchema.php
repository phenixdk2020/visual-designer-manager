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
    public const EVENTS = 'events';
    public const VEHICLES = 'vehicles';
    public const GALLERIES = 'galleries';
    public const CONTACT_FORM = 'contact-form';
    public const MEMBERSHIP_FORM = 'membership-form';
    public const NAVIGATION = 'navigation';

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
            self::EVENTS,
            self::VEHICLES,
            self::GALLERIES,
            self::CONTACT_FORM,
            self::MEMBERSHIP_FORM,
            self::NAVIGATION,
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
            self::EVENTS => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 60],
            self::VEHICLES => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 60],
            self::GALLERIES => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 60],
            self::CONTACT_FORM => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 100],
            self::MEMBERSHIP_FORM => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 128],
            self::NAVIGATION => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 8],
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
                'radius' => 0,
                'borderWidth' => 0,
                'borderColor' => '#d0d0d0',
                'autoHeight' => true,
                'minHeightRows' => 36,
            ],
            self::CONTAINER => [
                'background' => 'transparent',
                'padding' => 16,
                'radius' => 0,
                'borderWidth' => 0,
                'borderColor' => '#d0d0d0',
                'autoHeight' => true,
                'minHeightRows' => 24,
            ],
            self::TEXT => [
                'content' => '<p>Tekst</p>',
                'color' => '#222222',
                'fontSize' => 18,
                'fontWeight' => 400,
                'lineHeight' => 1.5,
                'align' => 'left',
                'verticalAlign' => 'top',
                'background' => 'transparent',
                'padding' => 0,
                'radius' => 0,
            ],
            self::IMAGE => ['attachmentId' => 0, 'alt' => '', 'objectFit' => 'cover'],
            self::BUTTON => [
                'label' => 'Knap',
                'url' => '#',
                'target' => '_self',
                'align' => 'left',
                'background' => '#2f4858',
                'color' => '#ffffff',
                'radius' => 4,
                'paddingX' => 18,
                'paddingY' => 10,
                'fontSize' => 16,
                'fontWeight' => 600,
                'borderWidth' => 0,
                'borderColor' => '#2f4858',
            ],
            self::EVENTS => [
                'count' => 6,
                'showPast' => false,
                'columns' => 3,
                'gap' => 20,
                'padding' => 18,
                'radius' => 6,
                'cardBackground' => '#ffffff',
                'textColor' => '#222222',
                'headingColor' => '#222222',
                'accentColor' => '#2f4858',
                'showImage' => true,
                'showSummary' => true,
                'showFacts' => true,
            ],
            self::VEHICLES => [
                'count' => 12,
                'columns' => 3,
                'gap' => 20,
                'padding' => 18,
                'radius' => 6,
                'cardBackground' => '#ffffff',
                'textColor' => '#222222',
                'headingColor' => '#222222',
                'accentColor' => '#2f4858',
                'showImage' => true,
                'showSummary' => true,
                'showFacts' => true,
            ],
            self::GALLERIES => [
                'count' => 12,
                'columns' => 3,
                'gap' => 20,
                'padding' => 16,
                'radius' => 6,
                'cardBackground' => '#ffffff',
                'textColor' => '#222222',
                'headingColor' => '#222222',
                'accentColor' => '#2f4858',
                'showCover' => true,
                'showSummary' => true,
            ],
            self::CONTACT_FORM => [
                'columns' => 2,
                'gap' => 16,
                'padding' => 20,
                'radius' => 6,
                'background' => '#ffffff',
                'fieldBackground' => '#ffffff',
                'textColor' => '#222222',
                'labelColor' => '#222222',
                'borderColor' => '#d0d0d0',
                'accentColor' => '#2f4858',
                'buttonTextColor' => '#ffffff',
                'submitLabel' => 'Send besked',
                'successMessage' => 'Tak. Din henvendelse er sendt.',
                'showPhone' => true,
                'showSubject' => true,
                'showAddress' => false,
                'showMessage' => true,
                'messageRows' => 6,
                'requireConsent' => true,
                'consentText' => 'Jeg accepterer, at oplysningerne bruges til at besvare min henvendelse.',
            ],
            self::MEMBERSHIP_FORM => [
                'columns' => 2,
                'gap' => 16,
                'padding' => 20,
                'radius' => 6,
                'background' => '#ffffff',
                'fieldBackground' => '#ffffff',
                'textColor' => '#222222',
                'labelColor' => '#222222',
                'borderColor' => '#d0d0d0',
                'accentColor' => '#2f4858',
                'buttonTextColor' => '#ffffff',
                'submitLabel' => 'Send indmeldelse',
                'successMessage' => 'Tak. Din indmeldelse er sendt.',
                'showPhone' => true,
                'showSubject' => false,
                'showAddress' => true,
                'showMessage' => true,
                'messageRows' => 5,
                'requireConsent' => true,
                'consentText' => 'Jeg accepterer, at oplysningerne bruges til at behandle min indmeldelse.',
            ],
            self::NAVIGATION => [
                'menuId' => 0,
                'orientation' => 'horizontal',
                'align' => 'left',
                'gap' => 24,
                'fontSize' => 16,
                'fontWeight' => 600,
                'textColor' => '#222222',
                'hoverColor' => '#2271b1',
                'background' => 'transparent',
                'submenuBackground' => '#ffffff',
                'submenuTextColor' => '#222222',
                'toggleLabel' => 'Menu',
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
                'radius' => max(0, min(80, (int) ($props['radius'] ?? $defaults['radius']))),
                'borderWidth' => max(0, min(20, (int) ($props['borderWidth'] ?? $defaults['borderWidth']))),
                'borderColor' => self::color((string) ($props['borderColor'] ?? $defaults['borderColor']), (string) $defaults['borderColor']),
                'autoHeight' => !array_key_exists('autoHeight', $props) || (bool) $props['autoHeight'],
                'minHeightRows' => max(1, min(2000, (int) ($props['minHeightRows'] ?? $defaults['minHeightRows']))),
            ];
        }

        if ($type === self::TEXT) {
            $align = (string) ($props['align'] ?? $defaults['align']);
            if (!in_array($align, ['left', 'center', 'right'], true)) {
                $align = 'left';
            }
            $verticalAlign = (string) ($props['verticalAlign'] ?? $defaults['verticalAlign']);
            if (!in_array($verticalAlign, ['top', 'center', 'bottom'], true)) {
                $verticalAlign = 'top';
            }
            $fontWeight = (int) ($props['fontWeight'] ?? $defaults['fontWeight']);
            if (!in_array($fontWeight, [400, 500, 600, 700], true)) {
                $fontWeight = 400;
            }
            return [
                'content' => wp_kses_post((string) ($props['content'] ?? $defaults['content'])),
                'color' => self::color((string) ($props['color'] ?? $defaults['color']), (string) $defaults['color']),
                'fontSize' => max(8, min(120, (int) ($props['fontSize'] ?? $defaults['fontSize']))),
                'fontWeight' => $fontWeight,
                'lineHeight' => max(0.8, min(3.0, (float) ($props['lineHeight'] ?? $defaults['lineHeight']))),
                'align' => $align,
                'verticalAlign' => $verticalAlign,
                'background' => self::color((string) ($props['background'] ?? $defaults['background']), (string) $defaults['background']),
                'padding' => max(0, min(120, (int) ($props['padding'] ?? $defaults['padding']))),
                'radius' => max(0, min(80, (int) ($props['radius'] ?? $defaults['radius']))),
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
            $target = (string) ($props['target'] ?? $defaults['target']);
            if (!in_array($target, ['_self', '_blank'], true)) {
                $target = '_self';
            }

            $align = (string) ($props['align'] ?? $defaults['align']);
            if (!in_array($align, ['left', 'center', 'right', 'stretch'], true)) {
                $align = 'left';
            }

            $fontWeight = (int) ($props['fontWeight'] ?? $defaults['fontWeight']);
            if (!in_array($fontWeight, [400, 500, 600, 700], true)) {
                $fontWeight = 600;
            }

            return [
                'label' => sanitize_text_field((string) ($props['label'] ?? $defaults['label'])),
                'url' => esc_url_raw((string) ($props['url'] ?? $defaults['url'])),
                'target' => $target,
                'align' => $align,
                'background' => self::color((string) ($props['background'] ?? $defaults['background']), (string) $defaults['background']),
                'color' => self::color((string) ($props['color'] ?? $defaults['color']), (string) $defaults['color']),
                'radius' => max(0, min(80, (int) ($props['radius'] ?? $defaults['radius']))),
                'paddingX' => max(0, min(120, (int) ($props['paddingX'] ?? $defaults['paddingX']))),
                'paddingY' => max(0, min(80, (int) ($props['paddingY'] ?? $defaults['paddingY']))),
                'fontSize' => max(8, min(80, (int) ($props['fontSize'] ?? $defaults['fontSize']))),
                'fontWeight' => $fontWeight,
                'borderWidth' => max(0, min(20, (int) ($props['borderWidth'] ?? $defaults['borderWidth']))),
                'borderColor' => self::color((string) ($props['borderColor'] ?? $defaults['borderColor']), (string) $defaults['borderColor']),
            ];
        }

        if ($type === self::EVENTS) {
            return [
                'count' => max(1, min(24, (int) ($props['count'] ?? $defaults['count']))),
                'showPast' => !empty($props['showPast']),
                'columns' => max(1, min(4, (int) ($props['columns'] ?? $defaults['columns']))),
                'gap' => max(0, min(80, (int) ($props['gap'] ?? $defaults['gap']))),
                'padding' => max(0, min(80, (int) ($props['padding'] ?? $defaults['padding']))),
                'radius' => max(0, min(60, (int) ($props['radius'] ?? $defaults['radius']))),
                'cardBackground' => self::color((string) ($props['cardBackground'] ?? $defaults['cardBackground']), (string) $defaults['cardBackground']),
                'textColor' => self::color((string) ($props['textColor'] ?? $defaults['textColor']), (string) $defaults['textColor']),
                'headingColor' => self::color((string) ($props['headingColor'] ?? $defaults['headingColor']), (string) $defaults['headingColor']),
                'accentColor' => self::color((string) ($props['accentColor'] ?? $defaults['accentColor']), (string) $defaults['accentColor']),
                'showImage' => !array_key_exists('showImage', $props) || !empty($props['showImage']),
                'showSummary' => !array_key_exists('showSummary', $props) || !empty($props['showSummary']),
                'showFacts' => !array_key_exists('showFacts', $props) || !empty($props['showFacts']),
            ];
        }

        if ($type === self::VEHICLES) {
            return [
                'count' => max(1, min(50, (int) ($props['count'] ?? $defaults['count']))),
                'columns' => max(1, min(4, (int) ($props['columns'] ?? $defaults['columns']))),
                'gap' => max(0, min(80, (int) ($props['gap'] ?? $defaults['gap']))),
                'padding' => max(0, min(80, (int) ($props['padding'] ?? $defaults['padding']))),
                'radius' => max(0, min(60, (int) ($props['radius'] ?? $defaults['radius']))),
                'cardBackground' => self::color((string) ($props['cardBackground'] ?? $defaults['cardBackground']), (string) $defaults['cardBackground']),
                'textColor' => self::color((string) ($props['textColor'] ?? $defaults['textColor']), (string) $defaults['textColor']),
                'headingColor' => self::color((string) ($props['headingColor'] ?? $defaults['headingColor']), (string) $defaults['headingColor']),
                'accentColor' => self::color((string) ($props['accentColor'] ?? $defaults['accentColor']), (string) $defaults['accentColor']),
                'showImage' => !array_key_exists('showImage', $props) || !empty($props['showImage']),
                'showSummary' => !array_key_exists('showSummary', $props) || !empty($props['showSummary']),
                'showFacts' => !array_key_exists('showFacts', $props) || !empty($props['showFacts']),
            ];
        }

        if ($type === self::GALLERIES) {
            return [
                'count' => max(1, min(50, (int) ($props['count'] ?? $defaults['count']))),
                'columns' => max(1, min(4, (int) ($props['columns'] ?? $defaults['columns']))),
                'gap' => max(0, min(80, (int) ($props['gap'] ?? $defaults['gap']))),
                'padding' => max(0, min(80, (int) ($props['padding'] ?? $defaults['padding']))),
                'radius' => max(0, min(60, (int) ($props['radius'] ?? $defaults['radius']))),
                'cardBackground' => self::color((string) ($props['cardBackground'] ?? $defaults['cardBackground']), (string) $defaults['cardBackground']),
                'textColor' => self::color((string) ($props['textColor'] ?? $defaults['textColor']), (string) $defaults['textColor']),
                'headingColor' => self::color((string) ($props['headingColor'] ?? $defaults['headingColor']), (string) $defaults['headingColor']),
                'accentColor' => self::color((string) ($props['accentColor'] ?? $defaults['accentColor']), (string) $defaults['accentColor']),
                'showCover' => !array_key_exists('showCover', $props) || !empty($props['showCover']),
                'showSummary' => !array_key_exists('showSummary', $props) || !empty($props['showSummary']),
            ];
        }

        if ($type === self::CONTACT_FORM || $type === self::MEMBERSHIP_FORM) {
            return [
                'columns' => max(1, min(2, (int) ($props['columns'] ?? $defaults['columns']))),
                'gap' => max(0, min(60, (int) ($props['gap'] ?? $defaults['gap']))),
                'padding' => max(0, min(80, (int) ($props['padding'] ?? $defaults['padding']))),
                'radius' => max(0, min(30, (int) ($props['radius'] ?? $defaults['radius']))),
                'background' => self::color((string) ($props['background'] ?? $defaults['background']), (string) $defaults['background']),
                'fieldBackground' => self::color((string) ($props['fieldBackground'] ?? $defaults['fieldBackground']), (string) $defaults['fieldBackground']),
                'textColor' => self::color((string) ($props['textColor'] ?? $defaults['textColor']), (string) $defaults['textColor']),
                'labelColor' => self::color((string) ($props['labelColor'] ?? $defaults['labelColor']), (string) $defaults['labelColor']),
                'borderColor' => self::color((string) ($props['borderColor'] ?? $defaults['borderColor']), (string) $defaults['borderColor']),
                'accentColor' => self::color((string) ($props['accentColor'] ?? $defaults['accentColor']), (string) $defaults['accentColor']),
                'buttonTextColor' => self::color((string) ($props['buttonTextColor'] ?? $defaults['buttonTextColor']), (string) $defaults['buttonTextColor']),
                'submitLabel' => sanitize_text_field((string) ($props['submitLabel'] ?? $defaults['submitLabel'])),
                'successMessage' => sanitize_text_field((string) ($props['successMessage'] ?? $defaults['successMessage'])),
                'showPhone' => !array_key_exists('showPhone', $props) || !empty($props['showPhone']),
                'showSubject' => !empty($props['showSubject']),
                'showAddress' => !empty($props['showAddress']),
                'showMessage' => !array_key_exists('showMessage', $props) || !empty($props['showMessage']),
                'messageRows' => max(3, min(12, (int) ($props['messageRows'] ?? $defaults['messageRows']))),
                'requireConsent' => !array_key_exists('requireConsent', $props) || !empty($props['requireConsent']),
                'consentText' => sanitize_text_field((string) ($props['consentText'] ?? $defaults['consentText'])),
            ];
        }

        if ($type === self::NAVIGATION) {
            $orientation = (string) ($props['orientation'] ?? $defaults['orientation']);
            if (!in_array($orientation, ['horizontal', 'vertical'], true)) {
                $orientation = 'horizontal';
            }
            $align = (string) ($props['align'] ?? $defaults['align']);
            if (!in_array($align, ['left', 'center', 'right'], true)) {
                $align = 'left';
            }
            $fontWeight = (int) ($props['fontWeight'] ?? $defaults['fontWeight']);
            if (!in_array($fontWeight, [400, 500, 600, 700], true)) {
                $fontWeight = 600;
            }
            $toggleLabel = sanitize_text_field((string) ($props['toggleLabel'] ?? $defaults['toggleLabel']));
            if ($toggleLabel === '') {
                $toggleLabel = 'Menu';
            }
            return [
                'menuId' => absint($props['menuId'] ?? 0),
                'orientation' => $orientation,
                'align' => $align,
                'gap' => max(0, min(80, (int) ($props['gap'] ?? $defaults['gap']))),
                'fontSize' => max(10, min(40, (int) ($props['fontSize'] ?? $defaults['fontSize']))),
                'fontWeight' => $fontWeight,
                'textColor' => self::color((string) ($props['textColor'] ?? $defaults['textColor']), (string) $defaults['textColor']),
                'hoverColor' => self::color((string) ($props['hoverColor'] ?? $defaults['hoverColor']), (string) $defaults['hoverColor']),
                'background' => self::color((string) ($props['background'] ?? $defaults['background']), (string) $defaults['background']),
                'submenuBackground' => self::color((string) ($props['submenuBackground'] ?? $defaults['submenuBackground']), (string) $defaults['submenuBackground']),
                'submenuTextColor' => self::color((string) ($props['submenuTextColor'] ?? $defaults['submenuTextColor']), (string) $defaults['submenuTextColor']),
                'toggleLabel' => $toggleLabel,
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
