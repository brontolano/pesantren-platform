from pathlib import Path
root = Path('app/Filament/Resources')
for path in root.rglob('*.php'):
    original = path.read_text(encoding='utf-8')
    lines = original.splitlines()
    seen = set()
    cleaned = []
    for line in lines:
        if line in ('use Filament\Resources\Resource;', 'use Filament\Schemas\Schema;'):
            if line in seen:
                continue
            seen.add(line)
        cleaned.append(line)
    new = '\n'.join(cleaned)
    if new != original:
        path.write_text(new, encoding='utf-8')
        print('Cleaned', path)
