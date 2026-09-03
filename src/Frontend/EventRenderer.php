<?php

declare(strict_types=1);

namespace VisualDesignerManager\Frontend;

use VisualDesignerManager\Events\EventRepository;

final class EventRenderer
{
    /** @param array<string,mixed> $props */
    public static function renderList(array $props): string
    {
        $count = max(1, min(24, (int) ($props['count'] ?? 6)));
        $events = EventRepository::query($count, !empty($props['showPast']));
        $columns = max(1, min(4, (int) ($props['columns'] ?? 3)));
        $gap = max(0, min(80, (int) ($props['gap'] ?? 20)));
        $padding = max(0, min(80, (int) ($props['padding'] ?? 18)));
        $radius = max(0, min(60, (int) ($props['radius'] ?? 6)));
        $cardBackground = self::color((string) ($props['cardBackground'] ?? '#ffffff'), '#ffffff');
        $textColor = self::color((string) ($props['textColor'] ?? '#222222'), '#222222');
        $headingColor = self::color((string) ($props['headingColor'] ?? '#222222'), '#222222');
        $accentColor = self::color((string) ($props['accentColor'] ?? '#2f4858'), '#2f4858');

        $style = implode(';', [
            '--vdm-events-columns:' . $columns,
            '--vdm-events-gap:' . $gap . 'px',
            '--vdm-event-padding:' . $padding . 'px',
            '--vdm-event-radius:' . $radius . 'px',
            '--vdm-event-card-bg:' . $cardBackground,
            '--vdm-event-text:' . $textColor,
            '--vdm-event-heading:' . $headingColor,
            '--vdm-event-accent:' . $accentColor,
        ]) . ';';

        if ($events === []) {
            return '<div class="vdm-events-empty">Ingen events fundet.</div>';
        }

        ob_start();
        echo '<div class="vdm-events" style="' . esc_attr($style) . '"><div class="vdm-events-grid">';
        foreach ($events as $event) {
            echo self::card($event, $props); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        echo '</div></div>';
        return (string) ob_get_clean();
    }

    public static function renderDetail(int $postId): string
    {
        $event = EventRepository::get($postId);
        ob_start();
        echo '<article class="vdm-event-detail">';
        echo '<h1 class="vdm-event-detail-title">' . esc_html((string) $event['title']) . '</h1>';
        echo self::facts($event, false); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        if ((string) $event['image'] !== '') {
            echo '<img class="vdm-event-detail-image" src="' . esc_url((string) $event['image']) . '" alt="' . esc_attr((string) $event['title']) . '">';
        }
        $content = (string) $event['content'];
        if ($content !== '') {
            $content = has_blocks($content) ? do_blocks($content) : wpautop($content);
            echo '<div class="vdm-event-detail-content">' . wp_kses_post(do_shortcode($content)) . '</div>';
        }
        echo '</article>';
        return (string) ob_get_clean();
    }

    /** @param array<string,mixed> $event
     *  @param array<string,mixed> $props
     */
    private static function card(array $event, array $props): string
    {
        ob_start();
        echo '<article class="vdm-event-card">';
        if (!empty($props['showImage']) && (string) $event['image'] !== '') {
            echo '<a class="vdm-event-image-link" href="' . esc_url((string) $event['permalink']) . '"><img class="vdm-event-card-image" src="' . esc_url((string) $event['image']) . '" alt="' . esc_attr((string) $event['title']) . '"></a>';
        }
        echo '<div class="vdm-event-card-body">';
        echo '<h3 class="vdm-event-card-title"><a href="' . esc_url((string) $event['permalink']) . '">' . esc_html((string) $event['title']) . '</a></h3>';
        if (!array_key_exists('showFacts', $props) || !empty($props['showFacts'])) {
            echo self::facts($event, true); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        if ((!array_key_exists('showSummary', $props) || !empty($props['showSummary'])) && (string) $event['summary'] !== '') {
            echo '<p class="vdm-event-summary">' . esc_html((string) $event['summary']) . '</p>';
        }
        echo '<a class="vdm-event-more" href="' . esc_url((string) $event['permalink']) . '">Læs mere</a>';
        echo '</div></article>';
        return (string) ob_get_clean();
    }

    /** @param array<string,mixed> $event */
    private static function facts(array $event, bool $compact): string
    {
        $items = [];
        if ((string) $event['startDate'] !== '') {
            $items['Dato'] = self::formatDate((string) $event['startDate']);
        }

        $time = self::formatTime((string) $event['startTime'], (string) $event['endTime']);
        if ($time !== '') {
            $items['Tid'] = $time;
        }
        if ((string) $event['location'] !== '') {
            $items['Sted'] = (string) $event['location'];
        }
        if (!$compact && (string) $event['address'] !== '') {
            $items['Adresse'] = (string) $event['address'];
        }
        if (!$compact && (string) $event['contact'] !== '') {
            $items['Kontakt'] = (string) $event['contact'];
        }

        if ($items === []) {
            return '';
        }

        $class = $compact ? 'vdm-event-facts is-compact' : 'vdm-event-facts';
        $html = '<dl class="' . esc_attr($class) . '">';
        foreach ($items as $label => $value) {
            $html .= '<div class="vdm-event-fact"><dt>' . esc_html($label) . '</dt><dd>' . esc_html($value) . '</dd></div>';
        }
        return $html . '</dl>';
    }

    private static function formatDate(string $date): string
    {
        $timestamp = strtotime($date . ' 12:00:00');
        return $timestamp ? wp_date('j. F Y', $timestamp) : $date;
    }

    private static function formatTime(string $start, string $end): string
    {
        if ($start === '') {
            return '';
        }
        return $end !== '' ? $start . '–' . $end : $start;
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
