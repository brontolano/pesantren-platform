<?php

use Modules\Core\Support\IstilahRegistry;

if (! function_exists('istilah')) {
    /**
     * Label istilah pesantren per tenant (WP-31).
     * istilah('musyrif') => 'Musyrif' atau label custom tenant (mis. 'Murabbi').
     */
    function istilah(string $kunci, ?string $default = null): string
    {
        return IstilahRegistry::label($kunci, $default);
    }
}
