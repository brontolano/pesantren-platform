from pathlib import Path
root = Path('app/Filament/Resources')
for path in root.rglob('*.php'):
    text = path.read_text(encoding='utf-8')
    new_text = text
    new_text = new_text.replace(
        "use Filament\\Resources\\Resource;`r`nuse Filament\\Schemas\\Schema;",
        "use Filament\\Resources\\Resource;\nuse Filament\\Schemas\\Schema;"
    )
    new_text = new_text.replace("use Filament\\Resources\\Resource;", "use Filament\\Resources\\Resource;")
    new_text = new_text.replace("use Filament\\Schemas\\Schema;", "use Filament\\Schemas\\Schema;")
    new_text = new_text.replace('return $form', 'return $schema')
    new_text = new_text.replace('public static function form($form)', 'public static function form(Schema $schema): Schema')
    if new_text != text:
        path.write_text(new_text, encoding='utf-8')
