<?php

declare(strict_types=1);

namespace VisualDesignerManager\Storage;

final class SiteDesignRepository
{
    public const OPTION = 'vdm_site_design_v2';

    /** @return array<string,mixed> */
    public static function defaults(): array
    {
        return [
            'shellEnabled' => false,
            'maxWidth' => 1440,
            'contentPadding' => 24,
            'pageBackground' => '#ffffff',
            'textColor' => '#222222',
            'headingColor' => '#222222',
            'linkColor' => '#2271b1',
            'baseFontSize' => 16,
            'fontFamily' => 'theme',
        ];
    }

    /** @return array<string,mixed> */
    public static function get(): array
    {
        $value = get_option(self::OPTION, []);
        return self::normalize(is_array($value) ? $value : []);
    }

    /** @param array<string,mixed> $value
     *  @return array<string,mixed>
     */
    public static function save(array $value): array
    {
        $normalized = self::normalize($value);
        update_option(self::OPTION, $normalized, false);
        return $normalized;
    }

    /** @param array<string,mixed> $value
     *  @return array<string,mixed>
     */
    public static function normalize(array $value): array
    {
        $defaults = self::defaults();
        $fontFamily = sanitize_key((string) ($value['fontFamily'] ?? $defaults['fontFamily']));
        if (!in_array($fontFamily, ['theme', 'system', 'serif'], true)) {
            $fontFamily = 'theme';
        }

        return [
            'shellEnabled' => !empty($value['shellEnabled']),
            'maxWidth' => max(640, min(2400, (int) ($value['maxWidth'] ?? $defaults['maxWidth']))),
            'contentPadding' => max(0, min(160, (int) ($value['contentPadding'] ?? $defaults['contentPadding']))),
            'pageBackground' => self::color((string) ($value['pageBackground'] ?? $defaults['pageBackground']), (string) $defaults['pageBackground']),
            'textColor' => self::color((string) ($value['textColor'] ?? $defaults['textColor']), (string) $defaults['textColor']),
            'headingColor' => self::color((string) ($value['headingColor'] ?? $defaults['headingColor']), (string) $defaults['headingColor']),
            'linkColor' => self::color((string) ($value['linkColor'] ?? $defaults['linkColor']), (string) $defaults['linkColor']),
            'baseFontSize' => max(12, min(28, (int) ($value['baseFontSize'] ?? $defaults['baseFontSize']))),
            'fontFamily' => $fontFamily,
        ];
    }

    /** @param array<string,mixed>|null $design */
    public static function cssVariables(?array $design = null): string
    {
        $design = self::normalize($design ?? self::get());
        $font = match ($design['fontFamily']) {
            'system' => 'system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif',
            'serif' => 'Georgia,Times New Roman,serif',
            default => 'inherit',
        };

        return implode(';', [
            '--vdm-site-max-width:' . (int) $design['maxWidth'] . 'px',
            '--vdm-site-padding:' . (int) $design['contentPadding'] . 'px',
            '--vdm-site-background:' . (string) $design['pageBackground'],
            '--vdm-site-text:' . (string) $design['textColor'],
            '--vdm-site-heading:' . (string) $design['headingColor'],
            '--vdm-site-link:' . (string) $design['linkColor'],
            '--vdm-site-font-size:' . (int) $design['baseFontSize'] . 'px',
            '--vdm-site-font:' . $font,
        ]) . ';';
    }

    private static function color(string $value, string $fallback): string
    {
        $color = sanitize_hex_color($value);
        return is_string($color) ? strtolower($color) : $fallback;
    }

    private function __construct()
    {
    }
}
