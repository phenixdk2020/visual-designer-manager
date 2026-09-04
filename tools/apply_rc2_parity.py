from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def replace_once(path: str, old: str, new: str) -> None:
    file = ROOT / path
    text = file.read_text(encoding='utf-8')
    count = text.count(old)
    if count != 1:
        raise SystemExit(f'{path}: expected one match, got {count}')
    file.write_text(text.replace(old, new, 1), encoding='utf-8')


# PHP 8.0 compatibility for redirect helper.
replace_once(
    'src/Admin/ParityController.php',
    "private static function redirect(string $slug, string $message, string $status = 'success'): never",
    "private static function redirect(string $slug, string $message, string $status = 'success'): void",
)

# Preserve a precise 120-step horizontal coordinate in addition to the canonical 12-column editor geometry.
replace_once(
    'src/Model/NodeSchema.php',
    """    /** @param array<string,mixed> $geometry\n     *  @return array{x:int,y:int,w:int,h:int}\n     */\n    public static function normalizeGeometry(array $geometry): array\n    {\n        $x = max(0, min(11, (int) ($geometry['x'] ?? 0)));\n        $w = max(1, min(12 - $x, (int) ($geometry['w'] ?? 12)));\n\n        return [\n            'x' => $x,\n            'y' => max(0, min(2000, (int) ($geometry['y'] ?? 0))),\n            'w' => $w,\n            'h' => max(1, min(2000, (int) ($geometry['h'] ?? 4))),\n        ];\n    }\n""",
    """    /** @param array<string,mixed> $geometry\n     *  @return array{x:int,y:int,w:int,h:int,fineX:int,fineW:int}\n     */\n    public static function normalizeGeometry(array $geometry): array\n    {\n        $x = max(0, min(11, (int) ($geometry['x'] ?? 0)));\n        $w = max(1, min(12 - $x, (int) ($geometry['w'] ?? 12)));\n        $fineX = max(0, min(119, (int) ($geometry['fineX'] ?? ($x * 10))));\n        $fineW = max(1, min(120 - $fineX, (int) ($geometry['fineW'] ?? ($w * 10))));\n\n        return [\n            'x' => $x,\n            'y' => max(0, min(2000, (int) ($geometry['y'] ?? 0))),\n            'w' => $w,\n            'h' => max(1, min(2000, (int) ($geometry['h'] ?? 4))),\n            'fineX' => $fineX,\n            'fineW' => $fineW,\n        ];\n    }\n""",
)

replace_once(
    'src/Frontend/Renderer.php',
    """        $last = ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 4];\n""",
    """        $last = ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 4, 'fineX' => 0, 'fineW' => 120];\n""",
)
replace_once(
    'src/Frontend/Renderer.php',
    """            foreach (['x', 'y', 'w', 'h'] as $key) {\n                $parts[] = '--vdm-' . $prefix . '-' . $key . ':' . (int) $geometry[$key];\n            }\n""",
    """            foreach (['x', 'y', 'w', 'h'] as $key) {\n                $parts[] = '--vdm-' . $prefix . '-' . $key . ':' . (int) $geometry[$key];\n            }\n            $parts[] = '--vdm-' . $prefix . '-fx:' . (int) ($geometry['fineX'] ?? ((int) $geometry['x'] * 10));\n            $parts[] = '--vdm-' . $prefix . '-fw:' . (int) ($geometry['fineW'] ?? ((int) $geometry['w'] * 10));\n""",
)

replace_once(
    'assets/frontend.css',
    """    --vdm-w:var(--vdm-d-w);\n    --vdm-h:var(--vdm-d-h);\n    grid-column:calc(var(--vdm-x) + 1) / span var(--vdm-w);\n    grid-row:calc(var(--vdm-y) + 1) / span var(--vdm-h);\n    min-width:0;\n""",
    """    --vdm-w:var(--vdm-d-w);\n    --vdm-h:var(--vdm-d-h);\n    --vdm-fx:var(--vdm-d-fx,calc(var(--vdm-x) * 10));\n    --vdm-fw:var(--vdm-d-fw,calc(var(--vdm-w) * 10));\n    grid-column:1 / -1;\n    grid-row:calc(var(--vdm-y) + 1) / span var(--vdm-h);\n    margin-left:calc(var(--vdm-fx) * 100% / 120);\n    width:calc(var(--vdm-fw) * 100% / 120);\n    min-width:0;\n""",
)
replace_once(
    'assets/frontend.css',
    ".vdm-node{--vdm-x:var(--vdm-l-x);--vdm-y:var(--vdm-l-y);--vdm-w:var(--vdm-l-w);--vdm-h:var(--vdm-l-h)}",
    ".vdm-node{--vdm-x:var(--vdm-l-x);--vdm-y:var(--vdm-l-y);--vdm-w:var(--vdm-l-w);--vdm-h:var(--vdm-l-h);--vdm-fx:var(--vdm-l-fx,calc(var(--vdm-x) * 10));--vdm-fw:var(--vdm-l-fw,calc(var(--vdm-w) * 10))}",
)
replace_once(
    'assets/frontend.css',
    ".vdm-node{--vdm-x:var(--vdm-t-x);--vdm-y:var(--vdm-t-y);--vdm-w:var(--vdm-t-w);--vdm-h:var(--vdm-t-h)}",
    ".vdm-node{--vdm-x:var(--vdm-t-x);--vdm-y:var(--vdm-t-y);--vdm-w:var(--vdm-t-w);--vdm-h:var(--vdm-t-h);--vdm-fx:var(--vdm-t-fx,calc(var(--vdm-x) * 10));--vdm-fw:var(--vdm-t-fw,calc(var(--vdm-w) * 10))}",
)
replace_once(
    'assets/frontend.css',
    ".vdm-node{--vdm-x:var(--vdm-m-x);--vdm-y:var(--vdm-m-y);--vdm-w:var(--vdm-m-w);--vdm-h:var(--vdm-m-h)}",
    ".vdm-node{--vdm-x:var(--vdm-m-x);--vdm-y:var(--vdm-m-y);--vdm-w:var(--vdm-m-w);--vdm-h:var(--vdm-m-h);--vdm-fx:var(--vdm-m-fx,calc(var(--vdm-x) * 10));--vdm-fw:var(--vdm-m-fw,calc(var(--vdm-w) * 10))}",
)

for key, prefix in [('desktop', 'd'), ('laptop', 'l'), ('tablet', 't'), ('mobile', 'm')]:
    old = f'#vdm-canvas[data-vdm-breakpoint="{key}"] .vdm-node{{--vdm-x:var(--vdm-{prefix}-x)!important;--vdm-y:var(--vdm-{prefix}-y)!important;--vdm-w:var(--vdm-{prefix}-w)!important;--vdm-h:var(--vdm-{prefix}-h)!important}}'
    new = f'#vdm-canvas[data-vdm-breakpoint="{key}"] .vdm-node{{--vdm-x:var(--vdm-{prefix}-x)!important;--vdm-y:var(--vdm-{prefix}-y)!important;--vdm-w:var(--vdm-{prefix}-w)!important;--vdm-h:var(--vdm-{prefix}-h)!important;--vdm-fx:var(--vdm-{prefix}-fx,calc(var(--vdm-x) * 10))!important;--vdm-fw:var(--vdm-{prefix}-fw,calc(var(--vdm-w) * 10))!important}}'
    replace_once('assets/designer.css', old, new)

replace_once(
    'assets/designer.js',
    """        element.style.setProperty('--vdm-' + prefix + '-w', String(geometry.w));\n        element.style.setProperty('--vdm-' + prefix + '-h', String(geometry.h));\n""",
    """        element.style.setProperty('--vdm-' + prefix + '-w', String(geometry.w));\n        element.style.setProperty('--vdm-' + prefix + '-h', String(geometry.h));\n        element.style.setProperty('--vdm-' + prefix + '-fx', String(geometry.fineX ?? (geometry.x * 10)));\n        element.style.setProperty('--vdm-' + prefix + '-fw', String(geometry.fineW ?? (geometry.w * 10)));\n""",
)
replace_once(
    'assets/designer.js',
    """            geometry.x = node.type === 'section' && !node.parentId\n                ? 0\n                : Math.max(0, Math.min(maxX, interaction.startGeometry.x + deltaColumns));\n            geometry.y = Math.max(0, interaction.startGeometry.y + deltaRows);\n""",
    """            geometry.x = node.type === 'section' && !node.parentId\n                ? 0\n                : Math.max(0, Math.min(maxX, interaction.startGeometry.x + deltaColumns));\n            geometry.fineX = geometry.x * 10;\n            geometry.fineW = geometry.w * 10;\n            geometry.y = Math.max(0, interaction.startGeometry.y + deltaRows);\n""",
)
replace_once(
    'assets/designer.js',
    """                geometry.w = Math.max(1, Math.min(12 - geometry.x, interaction.startGeometry.w + deltaColumns));\n""",
    """                geometry.w = Math.max(1, Math.min(12 - geometry.x, interaction.startGeometry.w + deltaColumns));\n                geometry.fineX = geometry.x * 10;\n                geometry.fineW = geometry.w * 10;\n""",
)
replace_once(
    'assets/designer.js',
    """        if (key === 'x') {\n            geometry.x = Math.max(0, Math.min(11, value));\n            geometry.w = Math.min(geometry.w, 12 - geometry.x);\n""",
    """        if (key === 'x') {\n            geometry.x = Math.max(0, Math.min(11, value));\n            geometry.w = Math.min(geometry.w, 12 - geometry.x);\n            geometry.fineX = geometry.x * 10;\n            geometry.fineW = geometry.w * 10;\n""",
)
replace_once(
    'assets/designer.js',
    """        } else if (key === 'w') {\n            geometry.w = Math.max(1, Math.min(12 - geometry.x, value));\n""",
    """        } else if (key === 'w') {\n            geometry.w = Math.max(1, Math.min(12 - geometry.x, value));\n            geometry.fineX = geometry.x * 10;\n            geometry.fineW = geometry.w * 10;\n""",
)

# Export reusable field definitions with native packages.
replace_once(
    'src/Transfer/PortableExporter.php',
    """use VisualDesignerManager\\Events\\EventRepository;\nuse VisualDesignerManager\\Gallery\\GalleryRepository;\n""",
    """use VisualDesignerManager\\Events\\EventRepository;\nuse VisualDesignerManager\\Fields\\EventFieldRegistry;\nuse VisualDesignerManager\\Fields\\VehicleFieldRegistry;\nuse VisualDesignerManager\\Gallery\\GalleryRepository;\n""",
)
replace_once(
    'src/Transfer/PortableExporter.php',
    """            'settings/site-design.json' => PortablePackage::json(['settings' => SiteDesignRepository::get()]),\n            'media/index.json' => PortablePackage::json(['items' => $mediaPayload]),\n""",
    """            'settings/site-design.json' => PortablePackage::json(['settings' => SiteDesignRepository::get()]),\n            'settings/custom-fields.json' => PortablePackage::json([\n                'vehicleFields' => VehicleFieldRegistry::all(),\n                'eventFields' => EventFieldRegistry::all(),\n            ]),\n            'media/index.json' => PortablePackage::json(['items' => $mediaPayload]),\n""",
)

# Import optional field definitions before content so field values are retained; old native packages remain accepted.
replace_once(
    'src/Transfer/PortableImporter.php',
    """use VisualDesignerManager\\Events\\EventRepository;\nuse VisualDesignerManager\\Gallery\\GalleryRepository;\n""",
    """use VisualDesignerManager\\Events\\EventRepository;\nuse VisualDesignerManager\\Fields\\EventFieldRegistry;\nuse VisualDesignerManager\\Fields\\VehicleFieldRegistry;\nuse VisualDesignerManager\\Gallery\\GalleryRepository;\n""",
)
replace_once(
    'src/Transfer/PortableImporter.php',
    """        $createdPosts = [];\n        $createdMenuItems = [];\n        $createdMenus = [];\n\n        try {\n""",
    """        $createdPosts = [];\n        $createdMenuItems = [];\n        $createdMenus = [];\n        $previousVehicleFields = null;\n        $previousEventFields = null;\n\n        try {\n""",
)
replace_once(
    'src/Transfer/PortableImporter.php',
    """            $siteDesignPayload = PortablePackage::readJson($zip, 'settings/site-design.json');\n\n            $header = is_array($headerPayload['document'] ?? null) ? $headerPayload['document'] : [];\n""",
    """            $siteDesignPayload = PortablePackage::readJson($zip, 'settings/site-design.json');\n            $customFieldPayload = $zip->locateName('settings/custom-fields.json') !== false\n                ? PortablePackage::readJson($zip, 'settings/custom-fields.json')\n                : [];\n\n            $header = is_array($headerPayload['document'] ?? null) ? $headerPayload['document'] : [];\n""",
)
replace_once(
    'src/Transfer/PortableImporter.php',
    """            $sourceKey = self::sourceKey($site, $manifest);\n""",
    """            $previousVehicleFields = VehicleFieldRegistry::all();\n            $previousEventFields = EventFieldRegistry::all();\n            if (is_array($customFieldPayload['vehicleFields'] ?? null)) {\n                VehicleFieldRegistry::save($customFieldPayload['vehicleFields']);\n            }\n            if (is_array($customFieldPayload['eventFields'] ?? null)) {\n                EventFieldRegistry::save($customFieldPayload['eventFields']);\n            }\n\n            $sourceKey = self::sourceKey($site, $manifest);\n""",
)
replace_once(
    'src/Transfer/PortableImporter.php',
    """        } catch (\\Throwable $exception) {\n            self::rollbackCreated($createdMenuItems, $createdMenus, $createdPosts);\n            throw $exception;\n""",
    """        } catch (\\Throwable $exception) {\n            self::rollbackCreated($createdMenuItems, $createdMenus, $createdPosts);\n            if (is_array($previousVehicleFields)) {\n                VehicleFieldRegistry::save($previousVehicleFields);\n            }\n            if (is_array($previousEventFields)) {\n                EventFieldRegistry::save($previousEventFields);\n            }\n            throw $exception;\n""",
)

# Carry field definitions, content values, neutral site shell geometry and exact horizontal coordinates through schema conversion.
replace_once(
    'src/Transfer/SchemaOneMigrator.php',
    """            $modulesPayload = self::readJson($zip, 'modules/modules.json');\n            $navigationPayload = self::readJson($zip, 'navigation/navigation.json');\n""",
    """            $modulesPayload = self::readJson($zip, 'modules/modules.json');\n            $customFieldsPayload = self::readJson($zip, 'modules/custom-fields.json');\n            $navigationPayload = self::readJson($zip, 'navigation/navigation.json');\n""",
)
replace_once(
    'src/Transfer/SchemaOneMigrator.php',
    """            $siteDesign = SiteDesignRepository::defaults();\n            $siteDesign['shellEnabled'] = true;\n\n            $payloads = [\n""",
    """            $siteDesign = self::convertSiteDesign($templatesPayload);\n            $customFields = self::convertFieldDefinitions($customFieldsPayload);\n\n            $payloads = [\n""",
)
replace_once(
    'src/Transfer/SchemaOneMigrator.php',
    """                'settings/site-design.json' => PortablePackage::json(['settings' => $siteDesign]),\n                'media/index.json' => PortablePackage::json(['items' => $media]),\n""",
    """                'settings/site-design.json' => PortablePackage::json(['settings' => $siteDesign]),\n                'settings/custom-fields.json' => PortablePackage::json($customFields),\n                'media/index.json' => PortablePackage::json(['items' => $media]),\n""",
)
replace_once(
    'src/Transfer/SchemaOneMigrator.php',
    """                    'content' => self::moduleContent((string) ($fields['description'] ?? ''), (array) ($record['attributes'] ?? [])),\n                ];\n""",
    """                    'content' => self::moduleContent((string) ($fields['description'] ?? ''), (array) ($record['attributes'] ?? [])),\n                    'customFields' => self::attributeValues((array) ($record['attributes'] ?? [])),\n                ];\n""",
)
replace_once(
    'src/Transfer/SchemaOneMigrator.php',
    """                    'content' => wp_kses_post((string) ($fields['description'] ?? '')),\n                    'specs' => $extra,\n                ], $fixed);\n""",
    """                    'content' => wp_kses_post((string) ($fields['description'] ?? '')),\n                    'specs' => $extra,\n                    'customFields' => self::attributeValues((array) ($record['attributes'] ?? [])),\n                ], $fixed);\n""",
)
replace_once(
    'src/Transfer/SchemaOneMigrator.php',
    """    /** @return array<string,mixed> */\n    private static function convertSite(array $site): array\n""",
    """    /** @param array<string,mixed> $templates @return array<string,mixed> */\n    private static function convertSiteDesign(array $templates): array\n    {\n        $design = SiteDesignRepository::defaults();\n        $design['shellEnabled'] = true;\n        $design['contentPadding'] = 0;\n        foreach ((array) ($templates['records'] ?? []) as $record) {\n            if (!is_array($record) || empty($record['active'])) {\n                continue;\n            }\n            $settings = is_array($record['settings'] ?? null) ? $record['settings'] : [];\n            $width = (int) ($settings['contentWidth'] ?? 0);\n            if ($width >= 640 && $width <= 2400) {\n                $design['maxWidth'] = $width;\n                break;\n            }\n        }\n        return SiteDesignRepository::normalize($design);\n    }\n\n    /** @param array<string,mixed> $payload @return array{vehicleFields:list<array<string,mixed>>,eventFields:list<array<string,mixed>>} */\n    private static function convertFieldDefinitions(array $payload): array\n    {\n        $vehicles = [];\n        foreach ((array) ($payload['vehicleFields'] ?? []) as $index => $row) {\n            if (!is_array($row)) {\n                continue;\n            }\n            $id = sanitize_key((string) ($row['id'] ?? ''));\n            $label = sanitize_text_field((string) ($row['label'] ?? ''));\n            if ($id === '' || $label === '') {\n                continue;\n            }\n            $vehicles[] = [\n                'id' => $id,\n                'label' => $label,\n                'type' => sanitize_key((string) ($row['type'] ?? 'text')),\n                'unit' => sanitize_text_field((string) ($row['unit'] ?? '')),\n                'enabled' => array_key_exists('enabled', $row) ? (bool) $row['enabled'] : true,\n                'order' => (int) ($row['order'] ?? (($index + 1) * 10)),\n            ];\n        }\n        $events = [];\n        foreach ((array) ($payload['eventFields'] ?? []) as $index => $row) {\n            if (!is_array($row)) {\n                continue;\n            }\n            $id = sanitize_key((string) ($row['id'] ?? ''));\n            $label = sanitize_text_field((string) ($row['label'] ?? ''));\n            if ($id === '' || $label === '') {\n                continue;\n            }\n            $events[] = [\n                'id' => $id,\n                'label' => $label,\n                'type' => sanitize_key((string) ($row['type'] ?? 'text')),\n                'enabled' => array_key_exists('enabled', $row) ? (bool) $row['enabled'] : true,\n                'required' => !empty($row['required']),\n                'showCard' => !empty($row['showCard']),\n                'showDetail' => array_key_exists('showDetail', $row) ? (bool) $row['showDetail'] : true,\n                'order' => (int) ($row['order'] ?? (($index + 1) * 10)),\n            ];\n        }\n        return ['vehicleFields' => $vehicles, 'eventFields' => $events];\n    }\n\n    /** @param list<array<string,mixed>> $attributes @return array<string,string> */\n    private static function attributeValues(array $attributes): array\n    {\n        $values = [];\n        foreach ($attributes as $attribute) {\n            if (!is_array($attribute) || empty($attribute['enabled'])) {\n                continue;\n            }\n            $key = sanitize_key((string) ($attribute['key'] ?? ''));\n            $value = wp_kses_post((string) ($attribute['value'] ?? ''));\n            if ($key !== '' && $value !== '') {\n                $values[$key] = $value;\n            }\n        }\n        return $values;\n    }\n\n    /** @return array<string,mixed> */\n    private static function convertSite(array $site): array\n""",
)
replace_once(
    'src/Transfer/SchemaOneMigrator.php',
    """    /** @param array<string,mixed> $raw\n     *  @param array{x:int,y:int,w:int,h:int} $fallback\n     *  @return array{x:int,y:int,w:int,h:int}\n     */\n    private static function convertDeviceGeometry(array $raw, int $units, array $fallback): array\n    {\n        if ($raw === []) {\n            return $fallback;\n        }\n        $factor = $units / 12;\n        $x = (int) round(((int) ($raw['x'] ?? 0)) / $factor);\n        $w = (int) round(((int) ($raw['w'] ?? $units)) / $factor);\n        $x = max(0, min(11, $x));\n        $w = max(1, min(12 - $x, $w));\n        return [\n            'x' => $x,\n            'y' => max(0, (int) ($raw['y'] ?? 0)),\n            'w' => $w,\n            'h' => max(1, (int) ($raw['h'] ?? ($fallback['h'] ?? 4))),\n        ];\n    }\n""",
    """    /** @param array<string,mixed> $raw\n     *  @param array<string,int> $fallback\n     *  @return array{x:int,y:int,w:int,h:int,fineX:int,fineW:int}\n     */\n    private static function convertDeviceGeometry(array $raw, int $units, array $fallback): array\n    {\n        if ($raw === []) {\n            $x = max(0, min(11, (int) ($fallback['x'] ?? 0)));\n            $w = max(1, min(12 - $x, (int) ($fallback['w'] ?? 12)));\n            return array_merge($fallback, [\n                'fineX' => (int) ($fallback['fineX'] ?? ($x * 10)),\n                'fineW' => (int) ($fallback['fineW'] ?? ($w * 10)),\n            ]);\n        }\n        $sourceX = max(0, min($units - 1, (int) ($raw['x'] ?? 0)));\n        $sourceW = max(1, min($units - $sourceX, (int) ($raw['w'] ?? $units)));\n        $fineX = max(0, min(119, (int) round(($sourceX * 120) / $units)));\n        $fineW = max(1, min(120 - $fineX, (int) round(($sourceW * 120) / $units)));\n        $x = max(0, min(11, (int) floor($fineX / 10)));\n        $right = (int) ceil(($fineX + $fineW) / 10);\n        $w = max(1, min(12 - $x, $right - $x));\n        return [\n            'x' => $x,\n            'y' => max(0, (int) ($raw['y'] ?? 0)),\n            'w' => $w,\n            'h' => max(1, (int) ($raw['h'] ?? ($fallback['h'] ?? 4))),\n            'fineX' => $fineX,\n            'fineW' => $fineW,\n        ];\n    }\n""",
)

# Improve the visual fallback for structured content that still travels through a text node.
replace_once(
    'src/Transfer/SchemaOneMigrator.php',
    """        if ($type === 'badge') {\n            return ['content' => '<p><strong>' . esc_html((string) ($props['text'] ?? '')) . '</strong></p>', 'color' => self::color((string) ($props['textColor'] ?? ''), '#222222'), 'fontSize' => max(8, min(120, (int) ($props['fontSize'] ?? 13)))];\n        }\n""",
    """        if ($type === 'badge') {\n            $background = self::color((string) ($props['background'] ?? ''), '#c3ae83');\n            $textColor = self::color((string) ($props['textColor'] ?? ''), '#222222');\n            $paddingX = max(0, min(120, (int) ($props['paddingX'] ?? 12)));\n            $paddingY = max(0, min(120, (int) ($props['paddingY'] ?? 5)));\n            $radius = max(0, min(100, (int) ($props['radius'] ?? 20)));\n            $html = '<span style="display:inline-block;background:' . esc_attr($background) . ';color:' . esc_attr($textColor) . ';padding:' . $paddingY . 'px ' . $paddingX . 'px;border-radius:' . $radius . 'px;font-weight:' . self::fontWeight($props['fontWeight'] ?? 700) . '">' . esc_html((string) ($props['text'] ?? '')) . '</span>';\n            return ['content' => $html, 'color' => $textColor, 'fontSize' => max(8, min(120, (int) ($props['fontSize'] ?? 13)))];\n        }\n""",
)

print('RC.2 parity patches applied')
