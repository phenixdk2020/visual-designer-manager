<?php

declare(strict_types=1);

namespace VisualDesignerManager\Frontend;

use VisualDesignerManager\Gallery\GalleryRepository;

final class GalleryRenderer
{
    /** @param array<string,mixed> $props */
    public static function renderList(array $props): string
    {
        $count = max(1, min(50, (int) ($props['count'] ?? 12)));
        $albums = GalleryRepository::query($count);
        $columns = max(1, min(4, (int) ($props['columns'] ?? 3)));
        $gap = max(0, min(80, (int) ($props['gap'] ?? 20)));
        $padding = max(0, min(80, (int) ($props['padding'] ?? 16)));
        $radius = max(0, min(60, (int) ($props['radius'] ?? 6)));
        $cardBackground = self::color((string) ($props['cardBackground'] ?? '#ffffff'), '#ffffff');
        $textColor = self::color((string) ($props['textColor'] ?? '#222222'), '#222222');
        $headingColor = self::color((string) ($props['headingColor'] ?? '#222222'), '#222222');
        $accentColor = self::color((string) ($props['accentColor'] ?? '#2f4858'), '#2f4858');

        $style = implode(';', [
            '--vdm-gallery-columns:' . $columns,
            '--vdm-gallery-gap:' . $gap . 'px',
            '--vdm-gallery-padding:' . $padding . 'px',
            '--vdm-gallery-radius:' . $radius . 'px',
            '--vdm-gallery-card-bg:' . $cardBackground,
            '--vdm-gallery-text:' . $textColor,
            '--vdm-gallery-heading:' . $headingColor,
            '--vdm-gallery-accent:' . $accentColor,
        ]) . ';';

        if ($albums === []) {
            return '<div class="vdm-gallery-empty">Ingen albummer fundet.</div>';
        }

        ob_start();
        echo '<div class="vdm-gallery-list" style="' . esc_attr($style) . '"><div class="vdm-gallery-albums">';
        foreach ($albums as $album) {
            echo self::albumCard($album, $props); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        echo '</div></div>';
        return (string) ob_get_clean();
    }

    public static function renderDetail(int $postId): string
    {
        $album = GalleryRepository::get($postId);
        ob_start();
        echo '<article class="vdm-gallery-detail">';
        echo '<h1 class="vdm-gallery-detail-title">' . esc_html((string) $album['title']) . '</h1>';

        $content = (string) $album['content'];
        if ($content !== '') {
            $content = has_blocks($content) ? do_blocks($content) : wpautop($content);
            echo '<div class="vdm-gallery-detail-content">' . wp_kses_post(do_shortcode($content)) . '</div>';
        }

        $images = is_array($album['images'] ?? null) ? $album['images'] : [];
        if ($images === []) {
            echo '<div class="vdm-gallery-empty">Albummet indeholder endnu ingen billeder.</div>';
        } else {
            echo '<div class="vdm-gallery-images">';
            foreach ($images as $image) {
                if (!is_array($image)) {
                    continue;
                }
                $url = (string) ($image['url'] ?? '');
                $thumb = (string) ($image['thumb'] ?? $url);
                if ($url === '') {
                    continue;
                }
                echo '<figure class="vdm-gallery-image">';
                echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">';
                echo '<img src="' . esc_url($thumb) . '" alt="' . esc_attr((string) ($image['alt'] ?? '')) . '" loading="lazy">';
                echo '</a>';
                $caption = (string) ($image['caption'] ?? '');
                if ($caption !== '') {
                    echo '<figcaption>' . esc_html($caption) . '</figcaption>';
                }
                echo '</figure>';
            }
            echo '</div>';
        }
        echo '</article>';
        return (string) ob_get_clean();
    }

    /** @param array<string,mixed> $album
     *  @param array<string,mixed> $props
     */
    private static function albumCard(array $album, array $props): string
    {
        ob_start();
        echo '<article class="vdm-gallery-album-card">';
        if (!empty($props['showCover']) && (string) $album['cover'] !== '') {
            echo '<a class="vdm-gallery-cover-link" href="' . esc_url((string) $album['permalink']) . '"><img class="vdm-gallery-cover" src="' . esc_url((string) $album['cover']) . '" alt="' . esc_attr((string) $album['title']) . '"></a>';
        }
        echo '<div class="vdm-gallery-album-body">';
        echo '<h3 class="vdm-gallery-album-title"><a href="' . esc_url((string) $album['permalink']) . '">' . esc_html((string) $album['title']) . '</a></h3>';
        echo '<p class="vdm-gallery-count">' . esc_html((string) count((array) ($album['imageIds'] ?? []))) . ' billeder</p>';
        if ((!array_key_exists('showSummary', $props) || !empty($props['showSummary'])) && (string) $album['summary'] !== '') {
            echo '<p class="vdm-gallery-summary">' . esc_html((string) $album['summary']) . '</p>';
        }
        echo '<a class="vdm-gallery-more" href="' . esc_url((string) $album['permalink']) . '">Åbn album</a>';
        echo '</div></article>';
        return (string) ob_get_clean();
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
