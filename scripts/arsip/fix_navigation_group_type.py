from pathlib import Path
root = Path('app/Filament/Resources')
for path in root.rglob('*.php'):
    text = path.read_text(encoding='utf-8')
    new_text = text.replace('protected static string|UnitEnum|null $navigationGroup', 'protected static UnitEnum|string|null $navigationGroup')
    if new_text != text:
        path.write_text(new_text, encoding='utf-8')
        print('Patched', path)
