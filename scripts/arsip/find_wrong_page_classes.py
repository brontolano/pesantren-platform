from pathlib import Path
import re
root = Path('app/Filament/Resources')
for path in root.rglob('Pages/*.php'):
    text = path.read_text(encoding='utf-8')
    if 'extends Page' in text and 'protected static string $resource' not in text:
        print(path)
