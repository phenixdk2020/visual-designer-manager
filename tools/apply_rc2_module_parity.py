from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PATH = ROOT / 'src/Transfer/SchemaOneMigrator.php'


def replace_once(old: str, new: str) -> None:
    text = PATH.read_text(encoding='utf-8')
    count = text.count(old)
    if count != 1:
        raise SystemExit(f'expected one match, got {count}')
    PATH.write_text(text.replace(old, new, 1), encoding='utf-8')


replace_once(
    """            'text', 'table', 'datalist', 'icon', 'badge' => NodeSchema::TEXT,\n            'image' => NodeSchema::IMAGE,\n""",
    """            'text', 'table', 'datalist', 'icon', 'badge' => NodeSchema::TEXT,\n            'eventlist' => NodeSchema::EVENTS,\n            'vehiclelist' => NodeSchema::VEHICLES,\n            'gallerylist' => NodeSchema::GALLERIES,\n            'image' => NodeSchema::IMAGE,\n""",
)

replace_once(
    """        if ($type === 'image') {\n""",
    """        if ($type === 'eventlist') {\n            $filter = (string) ($props['dateFilter'] ?? 'upcoming');\n            return [\n                'count' => max(1, min(50, (int) ($props['limit'] ?? 12))),\n                'showPast' => $filter !== 'upcoming',\n                'columns' => max(1, min(4, (int) ($props['columns'] ?? 3))),\n                'gap' => max(0, min(80, (int) ($props['cardGap'] ?? 18))),\n                'padding' => max(0, min(80, (int) ($props['cardPadding'] ?? 12))),\n                'radius' => max(0, min(60, (int) ($props['cardRadius'] ?? 4))),\n                'cardBackground' => self::color((string) ($props['cardBackground'] ?? ''), '#ffffff'),\n                'textColor' => self::color((string) ($props['textColor'] ?? ''), '#30382a'),\n                'headingColor' => self::color((string) ($props['textColor'] ?? ''), '#30382a'),\n                'accentColor' => self::color((string) ($props['accentColor'] ?? ''), '#c3ae83'),\n                'showImage' => !array_key_exists('showImage', $props) || !empty($props['showImage']),\n                'showSummary' => !array_key_exists('showSummary', $props) || !empty($props['showSummary']),\n                'showFacts' => (!array_key_exists('showDate', $props) || !empty($props['showDate'])) || (!array_key_exists('showLocation', $props) || !empty($props['showLocation'])),\n            ];\n        }\n        if ($type === 'vehiclelist') {\n            return [\n                'count' => max(1, min(100, (int) ($props['limit'] ?? 24))),\n                'columns' => max(1, min(4, (int) ($props['columns'] ?? 3))),\n                'gap' => max(0, min(80, (int) ($props['cardGap'] ?? 18))),\n                'padding' => max(0, min(80, (int) ($props['cardPadding'] ?? 12))),\n                'radius' => max(0, min(60, (int) ($props['cardRadius'] ?? 4))),\n                'cardBackground' => self::color((string) ($props['cardBackground'] ?? ''), '#ffffff'),\n                'textColor' => self::color((string) ($props['textColor'] ?? ''), '#30382a'),\n                'headingColor' => self::color((string) ($props['textColor'] ?? ''), '#30382a'),\n                'accentColor' => self::color((string) ($props['accentColor'] ?? ''), '#c3ae83'),\n                'showImage' => !array_key_exists('showImage', $props) || !empty($props['showImage']),\n                'showSummary' => !array_key_exists('showSummary', $props) || !empty($props['showSummary']),\n                'showFacts' => true,\n            ];\n        }\n        if ($type === 'gallerylist') {\n            return [\n                'count' => max(1, min(100, (int) ($props['limit'] ?? 24))),\n                'columns' => max(1, min(4, (int) ($props['columns'] ?? 3))),\n                'gap' => max(0, min(80, (int) ($props['cardGap'] ?? 18))),\n                'padding' => max(0, min(80, (int) ($props['cardPadding'] ?? 12))),\n                'radius' => max(0, min(60, (int) ($props['cardRadius'] ?? 4))),\n                'cardBackground' => self::color((string) ($props['cardBackground'] ?? ''), '#ffffff'),\n                'textColor' => self::color((string) ($props['textColor'] ?? ''), '#30382a'),\n                'headingColor' => self::color((string) ($props['textColor'] ?? ''), '#30382a'),\n                'accentColor' => self::color((string) ($props['accentColor'] ?? ''), '#c3ae83'),\n                'showCover' => !array_key_exists('showImage', $props) || !empty($props['showImage']),\n                'showSummary' => !array_key_exists('showSummary', $props) || !empty($props['showSummary']),\n            ];\n        }\n        if ($type === 'image') {\n""",
)

replace_once(
    """    private static function injectModuleNode(array &$layout, string $module): void\n    {\n        if (!in_array($module, [NodeSchema::EVENTS, NodeSchema::VEHICLES, NodeSchema::GALLERIES], true)) {\n            return;\n        }\n""",
    """    private static function injectModuleNode(array &$layout, string $module): void\n    {\n        if (!in_array($module, [NodeSchema::EVENTS, NodeSchema::VEHICLES, NodeSchema::GALLERIES], true)) {\n            return;\n        }\n        foreach ((array) ($layout['nodes'] ?? []) as $existing) {\n            if (is_array($existing) && (string) ($existing['type'] ?? '') === $module) {\n                return;\n            }\n        }\n""",
)

print('RC.2 module parity applied')
