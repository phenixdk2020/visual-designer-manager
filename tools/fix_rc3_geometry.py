from pathlib import Path

path = Path(__file__).resolve().parents[1] / 'src/Transfer/SchemaOneMigrator.php'
text = path.read_text(encoding='utf-8')

old = """        $sourceX = max(0, min($units - 1, (int) ($raw['x'] ?? 0)));
        $sourceW = max(1, min($units - $sourceX, (int) ($raw['w'] ?? $units)));
        $fineX = max(0, min(119, (int) round(($sourceX * 120) / $units)));
        $fineW = max(1, min(120 - $fineX, (int) round(($sourceW * 120) / $units)));
        $x = max(0, min(11, (int) floor($fineX / 10)));
        $right = (int) ceil(($fineX + $fineW) / 10);
        $w = max(1, min(12 - $x, $right - $x));
"""

new = """        $factor = $units / 12;
        $x = (int) round(((int) ($raw['x'] ?? 0)) / $factor);
        $w = (int) round(((int) ($raw['w'] ?? $units)) / $factor);
        $x = max(0, min(11, $x));
        $w = max(1, min(12 - $x, $w));

        $sourceX = max(0, min($units - 1, (int) ($raw['x'] ?? 0)));
        $sourceW = max(1, min($units - $sourceX, (int) ($raw['w'] ?? $units)));
        $fineX = max(0, min(119, (int) round(($sourceX * 120) / $units)));
        $fineW = max(1, min(120 - $fineX, (int) round(($sourceW * 120) / $units)));
"""

if old not in text:
    raise SystemExit('Geometry method marker not found')

path.write_text(text.replace(old, new, 1), encoding='utf-8')
print('RC.3 geometry compatibility patch applied')
