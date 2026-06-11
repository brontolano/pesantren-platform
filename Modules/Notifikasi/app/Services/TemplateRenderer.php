<?php

namespace Modules\Notifikasi\Services;

class TemplateRenderer
{
    public function render(string $content, array $variables = []): string
    {
        // strtr sekali-jalan: placeholder yang DIKETIK user di dalam nilai
        // variabel (mis. "{{nama_pesantren}}" dalam isi pesan) tidak ikut
        // diekspansi oleh putaran replace berikutnya.
        $map = [];
        foreach ($variables as $key => $value) {
            $map['{{'.$key.'}}'] = (string) $value;
        }

        return strtr($content, $map);
    }
}
