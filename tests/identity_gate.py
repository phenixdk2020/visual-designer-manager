from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
SKIP_PARTS = {'.git', '.idea', '.vscode', 'vendor', 'node_modules'}
TEXT_SUFFIXES = {
    '.php', '.js', '.css', '.json', '.md', '.txt', '.yml', '.yaml',
    '.xml', '.html', '.py', '.svg', '.scss', '.ts', '.tsx', '.jsx'
}

short_id = ''.join(('h', '1', '8'))
previous_name = ''.join(('h', 'a', 'n', 'g', 'a', 'r', '1', '8'))
previous_word = ''.join(('c', 'l', 'e', 'a', 'n'))


def path_has_previous_identifier(relative: Path) -> bool:
    folded = str(relative).casefold()
    if short_id in folded or previous_name in folded:
        return True

    for part in relative.parts:
        value = part.casefold()
        if value == previous_word or value.startswith(previous_word + '-') or value.startswith(previous_word + '_'):
            return True
    return False


def text_has_previous_identifier(text: str) -> bool:
    folded = text.casefold()
    if short_id in folded or previous_name in folded:
        return True

    standalone = r'(?<![a-z0-9_])' + re.escape(previous_word) + r'(?![a-z0-9_])'
    prefixed = r'(?<![a-z0-9_])' + re.escape(previous_word) + r'[-_]'
    camel = r'\b' + re.escape(previous_word.capitalize()) + r'[A-Z]'
    return bool(re.search(standalone, folded) or re.search(prefixed, folded) or re.search(camel, text))


failures = []

for path in ROOT.rglob('*'):
    if any(part in SKIP_PARTS for part in path.parts):
        continue

    relative = path.relative_to(ROOT)
    if path_has_previous_identifier(relative):
        failures.append(f'forbidden identifier in path: {relative}')

    if not path.is_file() or path.suffix.casefold() not in TEXT_SUFFIXES:
        continue

    try:
        text = path.read_text(encoding='utf-8')
    except UnicodeDecodeError:
        continue

    if text_has_previous_identifier(text):
        failures.append(f'forbidden identifier in file: {relative}')

if failures:
    print('V2 identity gate: FAIL')
    for failure in sorted(set(failures)):
        print(f' - {failure}')
    sys.exit(1)

print('V2 identity gate: PASS')
