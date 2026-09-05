<?php

declare(strict_types=1);

namespace VisualDesignerManager\Frontend;

use VisualDesignerManager\Events\EventRepository;
use VisualDesignerManager\Fields\EventFieldRegistry;
use VisualDesignerManager\Gallery\GalleryRepository;
use VisualDesignerManager\Model\Breakpoint;
use VisualDesignerManager\Model\LayoutDocument;
use VisualDesignerManager\Model\NodeSchema;
use VisualDesignerManager\Vehicles\VehicleRepository;

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

    /** @param array<string,mixed> $node @param array<string,list<array<string,mixed>>> $children */
    private static function node(array $node, array $children): string
    {
        $type = (string) $node['type'];
        $id = (string) $node['id'];
        $props = is_array($node['props'] ?? null) ? $node['props'] : [];
        $floating = $type === NodeSchema::BUTTON && (string) ($props['mode'] ?? 'normal') === 'floating';
        $classes = 'vdm-node vdm-node--' . sanitize_html_class($type) . ($floating ? ' is-floating' : '');
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
            echo '<div class="vdm-text">' . wp_kses_post((string) ($props['content'] ?? '')) . '</div>';
        } elseif ($type === NodeSchema::IMAGE) {
            echo self::image($props); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } elseif ($type === NodeSchema::BUTTON) {
            $href = self::linkUrl($props);
            $target = (string) ($props['target'] ?? '_self');
            $rel = $target === '_blank' ? ' rel="noopener noreferrer"' : '';
            echo '<a class="vdm-button" href="' . esc_url($href) . '" target="' . esc_attr($target) . '"' . $rel . '>' . esc_html((string) ($props['label'] ?? 'Knap')) . '</a>';
        } elseif ($type === NodeSchema::LINK) {
            $href = self::linkUrl($props);
            $target = (string) ($props['target'] ?? '_self');
            $rel = $target === '_blank' ? ' rel="noopener noreferrer"' : '';
            echo '<a class="vdm-link" href="' . esc_url($href) . '" target="' . esc_attr($target) . '"' . $rel . '>' . esc_html((string) ($props['label'] ?? 'Link')) . '</a>';
        } elseif ($type === NodeSchema::ICON) {
            echo '<span class="vdm-icon"' . ((string) ($props['ariaLabel'] ?? '') !== '' ? ' role="img" aria-label="' . esc_attr((string) $props['ariaLabel']) . '"' : ' aria-hidden="true"') . '>' . esc_html((string) ($props['symbol'] ?? '★')) . '</span>';
        } elseif ($type === NodeSchema::BADGE) {
            echo '<span class="vdm-badge">' . esc_html((string) ($props['label'] ?? 'Badge')) . '</span>';
        } elseif ($type === NodeSchema::DATA_LIST) {
            echo self::dataList($props); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } elseif ($type === NodeSchema::TABLE) {
            echo self::table($props); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } elseif ($type === NodeSchema::EVENTS) {
            echo EventRenderer::renderList($props); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } elseif ($type === NodeSchema::EVENT_VALUE) {
            echo self::eventValue($props); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } elseif ($type === NodeSchema::EVENT_IMAGE) {
            echo self::eventImage($props); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } elseif ($type === NodeSchema::EVENT_FIELD) {
            echo self::eventField($props); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } elseif ($type === NodeSchema::EVENT_FACTS) {
            echo self::eventFacts($props); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } elseif ($type === NodeSchema::VEHICLES) {
            echo VehicleRenderer::renderList($props); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } elseif ($type === NodeSchema::VEHICLE_DETAIL) {
            echo self::vehicleDetail(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } elseif ($type === NodeSchema::GALLERIES) {
            echo GalleryRenderer::renderList($props); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } elseif ($type === NodeSchema::GALLERY_DETAIL) {
            echo self::galleryDetail(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } elseif (in_array($type, [NodeSchema::CONTACT_FORM, NodeSchema::MEMBERSHIP_FORM], true)) {
            echo FormRenderer::render($type, $props, $id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } elseif ($type === NodeSchema::NAVIGATION) {
            echo NavigationRenderer::render($props, $id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } elseif ($type === NodeSchema::DIVIDER) {
            echo '<hr class="vdm-divider">';
        }

        echo '</div>';
        return (string) ob_get_clean();
    }

    /** @param array<string,mixed> $props */
    private static function image(array $props): string
    {
        $attachmentId = absint($props['attachmentId'] ?? 0);
        $src = $attachmentId > 0 ? wp_get_attachment_image_url($attachmentId, 'large') : false;
        if (is_string($src) && $src !== '') {
            return '<img class="vdm-image" src="' . esc_url($src) . '" alt="' . esc_attr((string) ($props['alt'] ?? '')) . '">';
        }
        return '<div class="vdm-image-placeholder">Billede</div>';
    }

    /** @param array<string,mixed> $props */
    private static function linkUrl(array $props): string
    {
        $type = (string) ($props['linkType'] ?? 'url');
        if ($type === 'page') {
            $url = get_permalink(absint($props['pageId'] ?? 0));
            return is_string($url) && $url !== '' ? $url : '#';
        }
        if ($type === 'anchor') {
            $anchor = ltrim(sanitize_title((string) ($props['anchor'] ?? '')), '#');
            return $anchor !== '' ? '#' . $anchor : '#';
        }
        if ($type === 'email') {
            $email = sanitize_email((string) ($props['email'] ?? ''));
            return $email !== '' ? 'mailto:' . $email : '#';
        }
        if ($type === 'tel') {
            $phone = preg_replace('/[^0-9+*#]/', '', (string) ($props['phone'] ?? '')) ?? '';
            return $phone !== '' ? 'tel:' . $phone : '#';
        }
        $url = esc_url_raw((string) ($props['url'] ?? '#'));
        return $url !== '' ? $url : '#';
    }

    /** @param array<string,mixed> $props */
    private static function dataList(array $props): string
    {
        $items = is_array($props['items'] ?? null) ? $props['items'] : [];
        if ($items === []) {
            return '<div class="vdm-data-list-empty">Ingen data</div>';
        }
        $html = '<dl class="vdm-data-list">';
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $html .= '<div class="vdm-data-list-row"><dt>' . esc_html((string) ($row['label'] ?? '')) . '</dt><dd>' . esc_html((string) ($row['value'] ?? '')) . '</dd></div>';
        }
        return $html . '</dl>';
    }

    /** @param array<string,mixed> $props */
    private static function table(array $props): string
    {
        $headers = is_array($props['headers'] ?? null) ? array_values($props['headers']) : [];
        $rows = is_array($props['rows'] ?? null) ? array_values($props['rows']) : [];
        $html = '<div class="vdm-table-scroll"><table class="vdm-table"><thead><tr>';
        foreach ($headers as $header) {
            $html .= '<th>' . esc_html((string) $header) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $html .= '<tr>';
            foreach ($headers as $index => $unused) {
                $html .= '<td>' . esc_html((string) ($row[$index] ?? '')) . '</td>';
            }
            $html .= '</tr>';
        }
        return $html . '</tbody></table></div>';
    }

    /** @param array<string,mixed> $props */
    private static function eventValue(array $props): string
    {
        $event = self::currentEvent();
        if ($event === null) {
            return self::contextPlaceholder('Eventværdi', 'Vises på en Event-detaljeside.');
        }
        $field = (string) ($props['field'] ?? 'title');
        $value = match ($field) {
            'date' => (string) ($event['startDate'] ?? ''),
            'time' => trim((string) ($event['startTime'] ?? '') . ((string) ($event['endTime'] ?? '') !== '' ? '–' . (string) $event['endTime'] : '')),
            'location' => (string) ($event['location'] ?? ''),
            'address' => (string) ($event['address'] ?? ''),
            'contact' => (string) ($event['contact'] ?? ''),
            'summary' => (string) ($event['summary'] ?? ''),
            'description' => wp_strip_all_tags((string) ($event['content'] ?? '')),
            default => (string) ($event['title'] ?? ''),
        };
        $label = !empty($props['showLabel']) ? trim((string) ($props['label'] ?? '')) : '';
        return '<div class="vdm-event-value">' . ($label !== '' ? '<span class="vdm-event-value-label">' . esc_html($label) . '</span>' : '') . '<span class="vdm-event-value-text">' . esc_html($value) . '</span></div>';
    }

    /** @param array<string,mixed> $props */
    private static function eventImage(array $props): string
    {
        $event = self::currentEvent();
        if ($event === null) {
            return self::contextPlaceholder('Eventbillede', 'Vises på en Event-detaljeside.');
        }
        $src = (string) ($event['image'] ?? '');
        return $src !== '' ? '<img class="vdm-event-node-image" src="' . esc_url($src) . '" alt="' . esc_attr((string) ($event['title'] ?? '')) . '">' : '<div class="vdm-image-placeholder">Intet eventbillede</div>';
    }

    /** @param array<string,mixed> $props */
    private static function eventField(array $props): string
    {
        $event = self::currentEvent();
        if ($event === null) {
            return self::contextPlaceholder('Eventfelt', 'Vises på en Event-detaljeside.');
        }
        $fieldId = sanitize_key((string) ($props['fieldId'] ?? ''));
        $definition = EventFieldRegistry::byId()[$fieldId] ?? null;
        $values = is_array($event['customFields'] ?? null) ? $event['customFields'] : [];
        $value = (string) ($values[$fieldId] ?? '');
        if (!is_array($definition) || $value === '') {
            return '';
        }
        $html = '<section class="vdm-event-field-node">';
        if (!array_key_exists('showHeading', $props) || !empty($props['showHeading'])) {
            $html .= '<h2>' . esc_html((string) ($definition['label'] ?? $fieldId)) . '</h2>';
        }
        if ((string) ($definition['type'] ?? '') === 'richtext') {
            $html .= '<div class="vdm-event-field-content">' . wp_kses_post(wpautop($value)) . '</div>';
        } else {
            $html .= '<p class="vdm-event-field-content">' . esc_html((string) ($definition['type'] ?? '') === 'boolean' && $value === '1' ? 'Ja' : $value) . '</p>';
        }
        return $html . '</section>';
    }

    /** @param array<string,mixed> $props */
    private static function eventFacts(array $props): string
    {
        $event = self::currentEvent();
        if ($event === null) {
            return self::contextPlaceholder('Eventfaktabånd', 'Dato, tid, sted, adresse og kontakt vises her på Event-detaljesiden.');
        }
        $map = [
            'showDate' => ['Dato', (string) ($event['startDate'] ?? '')],
            'showTime' => ['Tid', trim((string) ($event['startTime'] ?? '') . ((string) ($event['endTime'] ?? '') !== '' ? '–' . (string) $event['endTime'] : ''))],
            'showLocation' => ['Sted', (string) ($event['location'] ?? '')],
            'showAddress' => ['Adresse', (string) ($event['address'] ?? '')],
            'showContact' => ['Kontakt', (string) ($event['contact'] ?? '')],
        ];
        $html = '<dl class="vdm-event-facts-node">';
        $count = 0;
        foreach ($map as $flag => [$label, $value]) {
            if ((!array_key_exists($flag, $props) || !empty($props[$flag])) && $value !== '') {
                $count++;
                $html .= '<div class="vdm-event-fact-node"><dt>' . esc_html($label) . '</dt><dd>' . esc_html($value) . '</dd></div>';
            }
        }
        return $count > 0 ? $html . '</dl>' : '';
    }

    private static function vehicleDetail(): string
    {
        $postId = get_queried_object_id();
        return $postId > 0 && get_post_type($postId) === VehicleRepository::POST_TYPE
            ? VehicleRenderer::renderDetail($postId)
            : self::contextPlaceholder('Køretøjsdetalje', 'Vises på en Køretøjs-detaljeside.');
    }

    private static function galleryDetail(): string
    {
        $postId = get_queried_object_id();
        return $postId > 0 && get_post_type($postId) === GalleryRepository::POST_TYPE
            ? GalleryRenderer::renderDetail($postId)
            : self::contextPlaceholder('Albumvisning', 'Vises på en album-detaljeside.');
    }

    /** @return array<string,mixed>|null */
    private static function currentEvent(): ?array
    {
        $postId = get_queried_object_id();
        if ($postId <= 0 || get_post_type($postId) !== EventRepository::POST_TYPE) {
            return null;
        }
        return EventRepository::get($postId);
    }

    private static function contextPlaceholder(string $title, string $text): string
    {
        return '<div class="vdm-context-placeholder"><strong>' . esc_html($title) . '</strong><span>' . esc_html($text) . '</span></div>';
    }

    /** @param array<string,mixed> $node */
    private static function geometryStyle(array $node): string
    {
        $responsive = is_array($node['responsive'] ?? null) ? $node['responsive'] : [];
        $resolved = [];
        $last = ['x'=>0,'y'=>0,'w'=>12,'h'=>4,'fineX'=>0,'fineW'=>120];
        foreach (Breakpoint::ordered() as $breakpoint) {
            if (isset($responsive[$breakpoint]) && is_array($responsive[$breakpoint])) {
                $last = NodeSchema::normalizeGeometry($responsive[$breakpoint]);
            }
            $resolved[$breakpoint] = $last;
        }
        $prefixes = [Breakpoint::DESKTOP=>'d',Breakpoint::LAPTOP=>'l',Breakpoint::TABLET=>'t',Breakpoint::MOBILE=>'m'];
        $parts = [];
        foreach ($prefixes as $breakpoint => $prefix) {
            $geometry = $resolved[$breakpoint];
            foreach (['x','y','w','h'] as $key) {
                $parts[] = '--vdm-' . $prefix . '-' . $key . ':' . (int) $geometry[$key];
            }
            $parts[] = '--vdm-' . $prefix . '-fx:' . (int) ($geometry['fineX'] ?? ((int) $geometry['x'] * 10));
            $parts[] = '--vdm-' . $prefix . '-fw:' . (int) ($geometry['fineW'] ?? ((int) $geometry['w'] * 10));
        }
        return implode(';', $parts) . ';';
    }

    /** @param array<string,mixed> $node */
    private static function propertyStyle(array $node): string
    {
        $type = (string) $node['type'];
        $props = is_array($node['props'] ?? null) ? $node['props'] : [];
        $p = [];
        if (in_array($type,[NodeSchema::SECTION,NodeSchema::CONTAINER],true)) {
            $p[]='--vdm-background:'.($props['background']??'transparent');$p[]='--vdm-padding:'.(int)($props['padding']??0).'px';$p[]='--vdm-radius:'.(int)($props['radius']??0).'px';$p[]='--vdm-border-width:'.(int)($props['borderWidth']??0).'px';$p[]='--vdm-border-color:'.($props['borderColor']??'#d0d0d0');
        } elseif ($type===NodeSchema::TEXT) {
            $vertical=match((string)($props['verticalAlign']??'top')){'center'=>'center','bottom'=>'flex-end',default=>'flex-start'};
            $p[]='--vdm-color:'.($props['color']??'#222');$p[]='--vdm-font-size:'.(int)($props['fontSize']??18).'px';$p[]='--vdm-text-font-weight:'.(int)($props['fontWeight']??400);$p[]='--vdm-text-line-height:'.(float)($props['lineHeight']??1.5);$p[]='--vdm-text-letter-spacing:'.(float)($props['letterSpacing']??0).'px';$p[]='--vdm-text-align:'.($props['align']??'left');$p[]='--vdm-text-vertical-align:'.$vertical;$p[]='--vdm-text-background:'.($props['background']??'transparent');$p[]='--vdm-text-padding:'.(int)($props['padding']??0).'px';$p[]='--vdm-text-radius:'.(int)($props['radius']??0).'px';$p[]='--vdm-text-border-width:'.(int)($props['borderWidth']??0).'px';$p[]='--vdm-text-border-color:'.($props['borderColor']??'#d0d0d0');
        } elseif ($type===NodeSchema::IMAGE) {
            $p[]='--vdm-object-fit:'.($props['objectFit']??'cover');$p[]='--vdm-object-position-x:'.($props['positionX']??'center');$p[]='--vdm-object-position-y:'.($props['positionY']??'center');$p[]='--vdm-image-radius:'.(int)($props['radius']??0).'px';$p[]='--vdm-image-border-width:'.(int)($props['borderWidth']??0).'px';$p[]='--vdm-image-border-color:'.($props['borderColor']??'#d0d0d0');
        } elseif ($type===NodeSchema::BUTTON) {
            $align=(string)($props['align']??'left');$justify=match($align){'center'=>'center','right'=>'flex-end',default=>'flex-start'};
            $p[]='--vdm-button-background:'.($props['background']??'#2f4858');$p[]='--vdm-button-color:'.($props['color']??'#fff');$p[]='--vdm-button-hover-background:'.($props['hoverBackground']??'#243946');$p[]='--vdm-button-hover-color:'.($props['hoverColor']??'#fff');$p[]='--vdm-button-focus-color:'.($props['focusColor']??'#fff');$p[]='--vdm-button-radius:'.(int)($props['radius']??4).'px';$p[]='--vdm-button-padding-x:'.(int)($props['paddingX']??18).'px';$p[]='--vdm-button-padding-y:'.(int)($props['paddingY']??10).'px';$p[]='--vdm-button-font-size:'.(int)($props['fontSize']??16).'px';$p[]='--vdm-button-font-weight:'.(int)($props['fontWeight']??600);$p[]='--vdm-button-border-width:'.(int)($props['borderWidth']??0).'px';$p[]='--vdm-button-border-color:'.($props['borderColor']??'#2f4858');$p[]='--vdm-button-justify:'.$justify;$p[]='--vdm-button-width:'.($align==='stretch'?'100%':'auto');$p[]='--vdm-z-index:'.(int)($props['zIndex']??10);
        } elseif ($type===NodeSchema::LINK) {
            $justify=match((string)($props['align']??'left')){'center'=>'center','right'=>'flex-end',default=>'flex-start'};$p[]='--vdm-link-color:'.($props['color']??'#2271b1');$p[]='--vdm-link-hover:'.($props['hoverColor']??'#135e96');$p[]='--vdm-link-size:'.(int)($props['fontSize']??16).'px';$p[]='--vdm-link-weight:'.(int)($props['fontWeight']??400);$p[]='--vdm-link-justify:'.$justify;$p[]='--vdm-link-decoration:'.(!empty($props['underline'])?'underline':'none');
        } elseif ($type===NodeSchema::ICON) {
            $justify=match((string)($props['align']??'center')){'left'=>'flex-start','right'=>'flex-end',default=>'center'};$p[]='--vdm-icon-size:'.(int)($props['fontSize']??36).'px';$p[]='--vdm-icon-color:'.($props['color']??'#222');$p[]='--vdm-icon-bg:'.($props['background']??'transparent');$p[]='--vdm-icon-radius:'.(int)($props['radius']??0).'px';$p[]='--vdm-icon-justify:'.$justify;
        } elseif ($type===NodeSchema::BADGE) {
            $justify=match((string)($props['align']??'left')){'center'=>'center','right'=>'flex-end',default=>'flex-start'};$p[]='--vdm-badge-bg:'.($props['background']??'#2f4858');$p[]='--vdm-badge-color:'.($props['color']??'#fff');$p[]='--vdm-badge-radius:'.(int)($props['radius']??999).'px';$p[]='--vdm-badge-px:'.(int)($props['paddingX']??10).'px';$p[]='--vdm-badge-py:'.(int)($props['paddingY']??5).'px';$p[]='--vdm-badge-size:'.(int)($props['fontSize']??13).'px';$p[]='--vdm-badge-weight:'.(int)($props['fontWeight']??600);$p[]='--vdm-badge-justify:'.$justify;
        } elseif ($type===NodeSchema::DATA_LIST) {
            $p[]='--vdm-data-label:'.($props['labelColor']??'#555');$p[]='--vdm-data-value:'.($props['valueColor']??'#222');$p[]='--vdm-data-divider:'.($props['dividerColor']??'#d0d0d0');$p[]='--vdm-data-size:'.(int)($props['fontSize']??16).'px';$p[]='--vdm-data-gap:'.(int)($props['gap']??8).'px';$p[]='--vdm-data-divider-width:'.(!empty($props['showDividers'])?'1px':'0');
        } elseif ($type===NodeSchema::TABLE) {
            $p[]='--vdm-table-head-bg:'.($props['headerBackground']??'#f0f0f1');$p[]='--vdm-table-head-color:'.($props['headerColor']??'#222');$p[]='--vdm-table-cell-bg:'.($props['cellBackground']??'#fff');$p[]='--vdm-table-cell-color:'.($props['cellColor']??'#222');$p[]='--vdm-table-border:'.($props['borderColor']??'#d0d0d0');$p[]='--vdm-table-border-width:'.(int)($props['borderWidth']??1).'px';$p[]='--vdm-table-size:'.(int)($props['fontSize']??15).'px';$p[]='--vdm-table-stripe:'.(!empty($props['striped'])?'rgba(0,0,0,.035)':'transparent');
        } elseif ($type===NodeSchema::EVENT_VALUE) {
            $p[]='--vdm-event-value-size:'.(int)($props['fontSize']??24).'px';$p[]='--vdm-event-value-weight:'.(int)($props['fontWeight']??700);$p[]='--vdm-event-value-color:'.($props['color']??'#222');$p[]='--vdm-event-value-align:'.($props['align']??'left');
        } elseif ($type===NodeSchema::EVENT_IMAGE) {
            $p[]='--vdm-event-node-fit:'.($props['objectFit']??'cover');$p[]='--vdm-event-node-x:'.($props['positionX']??'center');$p[]='--vdm-event-node-y:'.($props['positionY']??'center');$p[]='--vdm-event-node-radius:'.(int)($props['radius']??0).'px';
        } elseif ($type===NodeSchema::EVENT_FIELD) {
            $p[]='--vdm-event-field-heading:'.($props['headingColor']??'#222');$p[]='--vdm-event-field-text:'.($props['textColor']??'#222');$p[]='--vdm-event-field-heading-size:'.(int)($props['headingSize']??24).'px';$p[]='--vdm-event-field-body-size:'.(int)($props['bodySize']??16).'px';$p[]='--vdm-event-field-bg:'.($props['background']??'transparent');$p[]='--vdm-event-field-padding:'.(int)($props['padding']??0).'px';$p[]='--vdm-event-field-radius:'.(int)($props['radius']??0).'px';
        } elseif ($type===NodeSchema::EVENT_FACTS) {
            $p[]='--vdm-event-facts-columns:'.(int)($props['columns']??5);$p[]='--vdm-event-facts-gap:'.(int)($props['gap']??8).'px';$p[]='--vdm-event-facts-accent:'.($props['accentColor']??'#2f4858');$p[]='--vdm-event-facts-bg:'.($props['background']??'#fff');$p[]='--vdm-event-facts-text:'.($props['textColor']??'#222');
        } elseif (in_array($type,[NodeSchema::CONTACT_FORM,NodeSchema::MEMBERSHIP_FORM],true)) {
            $p[]='--vdm-form-columns:'.(int)($props['columns']??2);$p[]='--vdm-form-gap:'.(int)($props['gap']??16).'px';$p[]='--vdm-form-field-gap:'.(int)($props['fieldGap']??16).'px';$p[]='--vdm-form-padding:'.(int)($props['padding']??20).'px';$p[]='--vdm-form-radius:'.(int)($props['radius']??6).'px';$p[]='--vdm-form-background:'.($props['background']??'#fff');$p[]='--vdm-form-field-background:'.($props['fieldBackground']??'#fff');$p[]='--vdm-form-text:'.($props['textColor']??'#222');$p[]='--vdm-form-label:'.($props['labelColor']??'#222');$p[]='--vdm-form-border:'.($props['borderColor']??'#d0d0d0');$p[]='--vdm-form-accent:'.($props['accentColor']??'#2f4858');$p[]='--vdm-form-button-text:'.($props['buttonTextColor']??'#fff');$p[]='--vdm-form-textarea-height:'.(int)($props['textareaHeight']??168).'px';$p[]='--vdm-form-consent-margin:'.(int)($props['consentMargin']??18).'px';$p[]='--vdm-form-button-padding-x:'.(int)($props['buttonPaddingX']??20).'px';$p[]='--vdm-form-button-padding-y:'.(int)($props['buttonPaddingY']??11).'px';
        } elseif ($type===NodeSchema::NAVIGATION) {
            $align=(string)($props['align']??'left');$justify=match($align){'center'=>'center','right'=>'flex-end',default=>'flex-start'};$p[]='--vdm-navigation-gap:'.(int)($props['gap']??24).'px';$p[]='--vdm-navigation-font-size:'.(int)($props['fontSize']??16).'px';$p[]='--vdm-navigation-font-weight:'.(int)($props['fontWeight']??600);$p[]='--vdm-navigation-text:'.($props['textColor']??'#222');$p[]='--vdm-navigation-hover:'.($props['hoverColor']??'#2271b1');$p[]='--vdm-navigation-background:'.($props['background']??'transparent');$p[]='--vdm-navigation-submenu-background:'.($props['submenuBackground']??'#fff');$p[]='--vdm-navigation-submenu-text:'.($props['submenuTextColor']??'#222');$p[]='--vdm-navigation-justify:'.$justify;
        } elseif ($type===NodeSchema::DIVIDER) {
            $p[]='--vdm-divider-color:'.($props['color']??'#d0d0d0');$p[]='--vdm-divider-thickness:'.(int)($props['thickness']??1).'px';
        }
        return $p===[]?'':implode(';',$p).';';
    }

    private function __construct(){}
}
