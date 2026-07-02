from pathlib import Path

root = Path('app/Filament/Resources')
broken_pattern = "use Filament\\Resources\\Resource;`r`nuse Filament\\Schemas\\Schema;"
for path in root.rglob('*.php'):
    text = path.read_text(encoding='utf-8')
    if broken_pattern in text:
        print('Found broken import in', path)
    new = text.replace(broken_pattern, "use Filament\\Resources\\Resource;\nuse Filament\\Schemas\\Schema;")
    new = new.replace('use Filament\\Resources\\Resource;', 'use Filament\\Resources\\Resource;')
    new = new.replace('use Filament\\Schemas\\Schema;', 'use Filament\\Schemas\\Schema;')
    new = new.replace('return $form', 'return $schema')
    new = new.replace('public static function form($form)', 'public static function form(Schema $schema): Schema')
    if new != text:
        path.write_text(new, encoding='utf-8')
        print('Fixed', path)
