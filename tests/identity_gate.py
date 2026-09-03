from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[1]
SKIP_PARTS = {'.git', '.idea', '.vscode', 'vendor', 'node_modules'}
TEXT_SUFFIXES = {
    '.php', '.js', '.css', '.json', '.md', '.txt', '.yml', '.yaml',
    '.xml', '.html', '.py', '.svg', '.scss', '.ts', '.tsx', '.jsx'
}

forbidden = [
    ''.join(('h', '1', '8')),
    ''.join(('h', 'a', 'n', 'g', 'a', 'r', '1', '8')),
    ''.join(('c', 'l', 'e', 'a', 'n')),
]

failures = []

for path in ROOT.rglob('*'):
    if any(part in SKIP_PARTS for part in path.parts):
        continue

    relative = path.relative_to(ROOT)
    relative_folded = str(relative).casefold()
    for token in forbidden:
        if token in relative_folded:
            failures.append(f'forbidden identifier in path: {relative}')

    if not path.is_file() or path.suffix.casefold() not in TEXT_SUFFIXES:
        continue

    try:
        text = path.read_text(encoding='utf-8').casefold()
    except UnicodeDecodeError:
        continue

    for token in forbidden:
        if token in text:
            failures.append(f'forbidden identifier in file: {relative}')

if failures:
    print('V2 identity gate: FAIL')
    for failure in sorted(set(failures)):
        print(f' - {failure}')
    sys.exit(1)

print('V2 identity gate: PASS')
