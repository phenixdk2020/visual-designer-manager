<?php

declare(strict_types=1);

namespace VisualDesignerManager\Frontend;

use VisualDesignerManager\Model\Breakpoint;
use VisualDesignerManager\Model\LayoutDocument;
use VisualDesignerManager\Model\NodeSchema;

final class Renderer
{
    /** @param array<string,mixed> $document */
    public static function render(array $document): string
    {
        $document = LayoutDocument::normalize($document);
        $nodes = $document['nodes'];
        if ($nodes === []) {
            return '';
        }

        $children = [];
        foreach ($nodes as $node) {
            $key = $node['parentId'] ?? '__root__';
            $children[$key][] = $node;
        }

        ob_start();
        echo '<div class="vdm-layout" data-vdm-layout-schema="2">';
        foreach ($children['__root__'] ?? [] as $node) {
            echo self::node($node, $children); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        echo '</div>';
        return (string) ob_get_clean();
    }

    /** @param array<string,mixed> $node
     *  @param array<string,list<array<string,mixed>>> $children
     */
    private static function node(array $node, array $children): string
    {
        $type = (string) $node['type'];
        $id = (string) $node['id'];
        $classes = 'vdm-node vdm-node--' . sanitize_html_class($type);
        $style = self::geometryStyle($node) . self::propertyStyle($node);

        ob_start();
        echo '<div class="' . esc_attr($classes) . '" data-vdm-node-id="' . esc_attr($id) . '" data-vdm-node-type="' . esc_attr($type) . '" style="' . esc_attr($style) . '">';

        if ($type === NodeSchema::SECTION || $type === NodeSchema::CONTAINER) {
            echo '<div class="vdm-node-surface">';
            foreach ($children[$id] ?? [] as $child) {
                echo self::node($child, $children); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
            echo '</div>';
        } elseif ($type === NodeSchema::TEXT) {
            echo '<div class="vdm-text">' . wp_kses_post((string) ($node['props']['content'] ?? '')) . '</div>';
        } elseif ($type === NodeSchema::IMAGE) {
            $attachmentId = absint($node['props']['attachmentId'] ?? 0);
            $src = $attachmentId > 0 ? wp_get_attachment_image_url($attachmentId, 'large') : false;
            if (is_string($src) && $src !== '') {
                echo '<img class="vdm-image" src="' . esc_url($src) . '" alt="' . esc_attr((string) ($node['props']['alt'] ?? '')) . '">';
            } else {
                echo '<div class="vdm-image-placeholder">Billede</div>';
            }
        } elseif ($type === NodeSchema::BUTTON) {
            $target = (string) ($node['props']['target'] ?? '_self');
            $rel = $target === '_blank' ? ' rel="noopener noreferrer"' : '';
            echo '<a class="vdm-button" href="' . esc_url((string) ($node['props']['url'] ?? '#')) . '" target="' . esc_attr($target) . '"' . $rel . '>' . esc_html((string) ($node['props']['label'] ?? 'Knap')) . '</a>';
        } elseif ($type === NodeSchema::EVENTS) {
            echo EventRenderer::renderList(is_array($node['props'] ?? null) ? $node['props'] : []); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } elseif ($type === NodeSchema::VEHICLES) {
            echo VehicleRenderer::renderList(is_array($node['props'] ?? null) ? $node['props'] : []); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } elseif ($type === NodeSchema::GALLERIES) {
            echo GalleryRenderer::renderList(is_array($node['props'] ?? null) ? $node['props'] : []); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } elseif (in_array($type, [NodeSchema::CONTACT_FORM, NodeSchema::MEMBERSHIP_FORM], true)) {
            echo FormRenderer::render($type, is_array($node['props'] ?? null) ? $node['props'] : [], $id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } elseif ($type === NodeSchema::NAVIGATION) {
            echo NavigationRenderer::render(is_array($node['props'] ?? null) ? $node['props'] : [], $id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } elseif ($type === NodeSchema::DIVIDER) {
            echo '<hr class="vdm-divider">';
        }

        echo '</div>';
        return (string) ob_get_clean();
    }

    /** @param array<string,mixed> $node */
    private static function geometryStyle(array $node): string
    {
        $responsive = is_array($node['responsive'] ?? null) ? $node['responsive'] : [];
        $resolved = [];
        $last = ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 4];

        foreach (Breakpoint::ordered() as $breakpoint) {
            if (isset($responsive[$breakpoint]) && is_array($responsive[$breakpoint])) {
                $last = NodeSchema::normalizeGeometry($responsive[$breakpoint]);
            }
            $resolved[$breakpoint] = $last;
        }

        $prefixes = [
            Breakpoint::DESKTOP => 'd',
            Breakpoint::LAPTOP => 'l',
            Breakpoint::TABLET => 't',
            Breakpoint::MOBILE => 'm',
        ];

        $parts = [];
        foreach ($prefixes as $breakpoint => $prefix) {
            $geometry = $resolved[$breakpoint];
            foreach (['x', 'y', 'w', 'h'] as $key) {
                $parts[] = '--vdm-' . $prefix . '-' . $key . ':' . (int) $geometry[$key];
            }
        }

        return implode(';', $parts) . ';';
    }

    /** @param array<string,mixed> $node */
    private static function propertyStyle(array $node): string
    {
        $type = (string) $node['type'];
        $props = is_array($node['props'] ?? null) ? $node['props'] : [];
        $parts = [];

        if ($type === NodeSchema::SECTION || $type === NodeSchema::CONTAINER) {
            $parts[] = '--vdm-background:' . (string) ($props['background'] ?? 'transparent');
            $parts[] = '--vdm-padding:' . (int) ($props['padding'] ?? 0) . 'px';
            $parts[] = '--vdm-radius:' . (int) ($props['radius'] ?? 0) . 'px';
            $parts[] = '--vdm-border-width:' . (int) ($props['borderWidth'] ?? 0) . 'px';
            $parts[] = '--vdm-border-color:' . (string) ($props['borderColor'] ?? '#d0d0d0');
        } elseif ($type === NodeSchema::TEXT) {
            $vertical = match ((string) ($props['verticalAlign'] ?? 'top')) {
                'center' => 'center',
                'bottom' => 'flex-end',
                default => 'flex-start',
            };
            $parts[] = '--vdm-color:' . (string) ($props['color'] ?? '#222222');
            $parts[] = '--vdm-font-size:' . (int) ($props['fontSize'] ?? 18) . 'px';
            $parts[] = '--vdm-text-font-weight:' . (int) ($props['fontWeight'] ?? 400);
            $parts[] = '--vdm-text-line-height:' . (float) ($props['lineHeight'] ?? 1.5);
            $parts[] = '--vdm-text-align:' . (string) ($props['align'] ?? 'left');
            $parts[] = '--vdm-text-vertical-align:' . $vertical;
            $parts[] = '--vdm-text-background:' . (string) ($props['background'] ?? 'transparent');
            $parts[] = '--vdm-text-padding:' . (int) ($props['padding'] ?? 0) . 'px';
            $parts[] = '--vdm-text-radius:' . (int) ($props['radius'] ?? 0) . 'px';
        } elseif ($type === NodeSchema::IMAGE) {
            $parts[] = '--vdm-object-fit:' . (string) ($props['objectFit'] ?? 'cover');
        } elseif ($type === NodeSchema::BUTTON) {
            $align = (string) ($props['align'] ?? 'left');
            $justify = match ($align) {
                'center' => 'center',
                'right' => 'flex-end',
                default => 'flex-start',
            };
            $parts[] = '--vdm-button-background:' . (string) ($props['background'] ?? '#2f4858');
            $parts[] = '--vdm-button-color:' . (string) ($props['color'] ?? '#ffffff');
            $parts[] = '--vdm-button-radius:' . (int) ($props['radius'] ?? 4) . 'px';
            $parts[] = '--vdm-button-padding-x:' . (int) ($props['paddingX'] ?? 18) . 'px';
            $parts[] = '--vdm-button-padding-y:' . (int) ($props['paddingY'] ?? 10) . 'px';
            $parts[] = '--vdm-button-font-size:' . (int) ($props['fontSize'] ?? 16) . 'px';
            $parts[] = '--vdm-button-font-weight:' . (int) ($props['fontWeight'] ?? 600);
            $parts[] = '--vdm-button-border-width:' . (int) ($props['borderWidth'] ?? 0) . 'px';
            $parts[] = '--vdm-button-border-color:' . (string) ($props['borderColor'] ?? '#2f4858');
            $parts[] = '--vdm-button-justify:' . $justify;
            $parts[] = '--vdm-button-width:' . ($align === 'stretch' ? '100%' : 'auto');
        } elseif (in_array($type, [NodeSchema::CONTACT_FORM, NodeSchema::MEMBERSHIP_FORM], true)) {
            $parts[] = '--vdm-form-columns:' . (int) ($props['columns'] ?? 2);
            $parts[] = '--vdm-form-gap:' . (int) ($props['gap'] ?? 16) . 'px';
            $parts[] = '--vdm-form-padding:' . (int) ($props['padding'] ?? 20) . 'px';
            $parts[] = '--vdm-form-radius:' . (int) ($props['radius'] ?? 6) . 'px';
            $parts[] = '--vdm-form-background:' . (string) ($props['background'] ?? '#ffffff');
            $parts[] = '--vdm-form-field-background:' . (string) ($props['fieldBackground'] ?? '#ffffff');
            $parts[] = '--vdm-form-text:' . (string) ($props['textColor'] ?? '#222222');
            $parts[] = '--vdm-form-label:' . (string) ($props['labelColor'] ?? '#222222');
            $parts[] = '--vdm-form-border:' . (string) ($props['borderColor'] ?? '#d0d0d0');
            $parts[] = '--vdm-form-accent:' . (string) ($props['accentColor'] ?? '#2f4858');
            $parts[] = '--vdm-form-button-text:' . (string) ($props['buttonTextColor'] ?? '#ffffff');
        } elseif ($type === NodeSchema::NAVIGATION) {
            $align = (string) ($props['align'] ?? 'left');
            $justify = match ($align) {
                'center' => 'center',
                'right' => 'flex-end',
                default => 'flex-start',
            };
            $parts[] = '--vdm-navigation-gap:' . (int) ($props['gap'] ?? 24) . 'px';
            $parts[] = '--vdm-navigation-font-size:' . (int) ($props['fontSize'] ?? 16) . 'px';
            $parts[] = '--vdm-navigation-font-weight:' . (int) ($props['fontWeight'] ?? 600);
            $parts[] = '--vdm-navigation-text:' . (string) ($props['textColor'] ?? '#222222');
            $parts[] = '--vdm-navigation-hover:' . (string) ($props['hoverColor'] ?? '#2271b1');
            $parts[] = '--vdm-navigation-background:' . (string) ($props['background'] ?? 'transparent');
            $parts[] = '--vdm-navigation-submenu-background:' . (string) ($props['submenuBackground'] ?? '#ffffff');
            $parts[] = '--vdm-navigation-submenu-text:' . (string) ($props['submenuTextColor'] ?? '#222222');
            $parts[] = '--vdm-navigation-justify:' . $justify;
        } elseif ($type === NodeSchema::DIVIDER) {
            $parts[] = '--vdm-divider-color:' . (string) ($props['color'] ?? '#d0d0d0');
            $parts[] = '--vdm-divider-thickness:' . (int) ($props['thickness'] ?? 1) . 'px';
        }

        return $parts === [] ? '' : implode(';', $parts) . ';';
    }

    private function __construct()
    {
    }
}
