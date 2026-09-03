from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
path = ROOT / 'src' / 'Admin' / 'DesignerController.php'
text = path.read_text(encoding='utf-8')
old = "            'themeColors' => self::themeColors(),\n"
new = "            'themeColors' => DesignerController::themeColors(),\n"
count = text.count(old)
if count != 1:
    raise SystemExit(f'Expected one Designer themeColors marker, found {count}')
path.write_text(text.replace(old, new, 1), encoding='utf-8')
print('Prepared beta.2 Designer marker')
