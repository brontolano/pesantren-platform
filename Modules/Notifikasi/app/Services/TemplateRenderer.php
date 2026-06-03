<?php

namespace Modules\Notifikasi\Services;

class TemplateRenderer
{
    public function render(string $content, array $variables = []): string
    {
        $rendered = $content;

        foreach ($variables as $key => $value) {
            $rendered = str_replace('{{'.$key.'}}', (string) $value, $rendered);
        }

        return $rendered;
    }
}
