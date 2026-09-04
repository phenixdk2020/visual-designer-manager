<?php

declare(strict_types=1);

namespace VisualDesignerManager\Frontend;

use VisualDesignerManager\Fields\VehicleFieldRegistry;
use VisualDesignerManager\Vehicles\VehicleRepository;

final class VehicleRenderer
{
    /** @param array<string,mixed> $props */
    public static function renderList(array $props): string
    {
        $count = max(1, min(50, (int) ($props['count'] ?? 12)));
        $vehicles = VehicleRepository::query($count);
        $columns = max(1, min(4, (int) ($props['columns'] ?? 3)));
        $gap = max(0, min(80, (int) ($props['gap'] ?? 20)));
        $padding = max(0, min(80, (int) ($props['padding'] ?? 18)));
        $radius = max(0, min(60, (int) ($props['radius'] ?? 6)));
        $cardBackground = self::color((string) ($props['cardBackground'] ?? '#ffffff'), '#ffffff');
        $textColor = self::color((string) ($props['textColor'] ?? '#222222'), '#222222');
        $headingColor = self::color((string) ($props['headingColor'] ?? '#222222'), '#222222');
        $accentColor = self::color((string) ($props['accentColor'] ?? '#2f4858'), '#2f4858');

        $style = implode(';', [
            '--vdm-vehicles-columns:' . $columns,
            '--vdm-vehicles-gap:' . $gap . 'px',
            '--vdm-vehicle-padding:' . $padding . 'px',
            '--vdm-vehicle-radius:' . $radius . 'px',
            '--vdm-vehicle-card-bg:' . $cardBackground,
            '--vdm-vehicle-text:' . $textColor,
            '--vdm-vehicle-heading:' . $headingColor,
            '--vdm-vehicle-accent:' . $accentColor,
        ]) . ';';

        if ($vehicles === []) {
            return '<div class="vdm-vehicles-empty">Ingen køretøjer fundet.</div>';
        }

        ob_start();
        echo '<div class="vdm-vehicles" style="' . esc_attr($style) . '"><div class="vdm-vehicles-grid">';
        foreach ($vehicles as $vehicle) {
            echo self::card($vehicle, $props); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        echo '</div></div>';
        return (string) ob_get_clean();
    }

    public static function renderDetail(int $postId): string
    {
        $vehicle = VehicleRepository::get($postId);
        ob_start();
        echo '<article class="vdm-vehicle-detail">';
        echo '<h1 class="vdm-vehicle-detail-title">' . esc_html((string) $vehicle['title']) . '</h1>';
        echo '<div class="vdm-vehicle-detail-grid">';
        echo '<div class="vdm-vehicle-detail-media">';
        if ((string) $vehicle['image'] !== '') {
            echo '<img class="vdm-vehicle-detail-image" src="' . esc_url((string) $vehicle['image']) . '" alt="' . esc_attr((string) $vehicle['title']) . '">';
        } else {
            echo '<div class="vdm-vehicle-detail-placeholder">Intet billede</div>';
        }
        echo '</div>';
        echo '<div class="vdm-vehicle-detail-specs">' . self::facts($vehicle, false) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</div>';

        $content = (string) $vehicle['content'];
        if ($content !== '') {
            $content = has_blocks($content) ? do_blocks($content) : wpautop($content);
            echo '<div class="vdm-vehicle-detail-content">' . wp_kses_post(do_shortcode($content)) . '</div>';
        }
        echo '</article>';
        return (string) ob_get_clean();
    }

    /** @param array<string,mixed> $vehicle @param array<string,mixed> $props */
    private static function card(array $vehicle, array $props): string
    {
        ob_start();
        echo '<article class="vdm-vehicle-card">';
        if (!empty($props['showImage']) && (string) $vehicle['image'] !== '') {
            echo '<a class="vdm-vehicle-image-link" href="' . esc_url((string) $vehicle['permalink']) . '"><img class="vdm-vehicle-card-image" src="' . esc_url((string) $vehicle['image']) . '" alt="' . esc_attr((string) $vehicle['title']) . '"></a>';
        }
        echo '<div class="vdm-vehicle-card-body">';
        echo '<h3 class="vdm-vehicle-card-title"><a href="' . esc_url((string) $vehicle['permalink']) . '">' . esc_html((string) $vehicle['title']) . '</a></h3>';
        if (!array_key_exists('showFacts', $props) || !empty($props['showFacts'])) {
            echo self::facts($vehicle, true); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        if ((!array_key_exists('showSummary', $props) || !empty($props['showSummary'])) && (string) $vehicle['summary'] !== '') {
            echo '<p class="vdm-vehicle-summary">' . esc_html((string) $vehicle['summary']) . '</p>';
        }
        echo '<a class="vdm-vehicle-more" href="' . esc_url((string) $vehicle['permalink']) . '">Læs mere</a>';
        echo '</div></article>';
        return (string) ob_get_clean();
    }

    /** @param array<string,mixed> $vehicle */
    private static function facts(array $vehicle, bool $compact): string
    {
        $items = [];
        foreach (['type' => 'Type', 'country' => 'Oprindelsesland', 'status' => 'Status'] as $key => $label) {
            $value = (string) ($vehicle[$key] ?? '');
            if ($value !== '') {
                $items[$label] = $value;
            }
        }

        $custom = is_array($vehicle['customFields'] ?? null) ? $vehicle['customFields'] : [];
        $shown = 0;
        foreach (VehicleFieldRegistry::all() as $definition) {
            if (empty($definition['enabled'])) {
                continue;
            }
            $id = (string) $definition['id'];
            $value = (string) ($custom[$id] ?? ($vehicle[$id] ?? ''));
            if ($value === '') {
                continue;
            }
            if ($compact && $shown >= 3) {
                break;
            }
            $label = (string) $definition['label'];
            $unit = trim((string) ($definition['unit'] ?? ''));
            if ($unit !== '') {
                $label .= ' (' . $unit . ')';
            }
            $items[$label] = ((string) ($definition['type'] ?? '') === 'boolean' && $value === '1') ? 'Ja' : wp_strip_all_tags($value);
            $shown++;
        }

        if (!$compact) {
            foreach (['power' => 'Motorydelse', 'length' => 'Længde', 'width' => 'Bredde', 'height' => 'Højde'] as $key => $label) {
                $value = (string) ($vehicle[$key] ?? '');
                if ($value !== '') {
                    $items[$label] = $value;
                }
            }
            foreach ((array) ($vehicle['specs'] ?? []) as $spec) {
                if (!is_array($spec)) {
                    continue;
                }
                $label = (string) ($spec['label'] ?? '');
                $value = (string) ($spec['value'] ?? '');
                if ($label !== '' || $value !== '') {
                    $items[$label !== '' ? $label : 'Ekstra'] = $value;
                }
            }
        }

        if ($items === []) {
            return '';
        }

        $class = $compact ? 'vdm-vehicle-facts is-compact' : 'vdm-vehicle-facts';
        $html = '<dl class="' . esc_attr($class) . '">';
        foreach ($items as $label => $value) {
            $html .= '<div class="vdm-vehicle-fact"><dt>' . esc_html($label) . '</dt><dd>' . esc_html($value) . '</dd></div>';
        }
        return $html . '</dl>';
    }

    private static function color(string $value, string $fallback): string
    {
        $color = sanitize_hex_color($value);
        return is_string($color) ? $color : $fallback;
    }

    private function __construct()
    {
    }
}
