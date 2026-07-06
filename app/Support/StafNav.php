<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Bottom-nav STANDAR seluruh halaman detail staf (owner 2026-07-06): persis
 * mockup staf-app-preview — Beranda · Menu · Aktivitas · Akun. Tab Menu/
 * Aktivitas/Akun membuka shell /staf pada tab terkait (?tab=). Navigasi
 * kontekstual (Gerbang/Tamu/Log dst) TIDAK lagi di bottom-nav — lewat grid
 * Menu + tombol Kembali di halaman.
 */
final class StafNav
{
    /** @return array<int, array{key:string,label:string,href:string,icon:string,active:bool}> */
    public static function items(): array
    {
        return [
            ['key' => 'beranda', 'label' => 'Beranda', 'href' => '/staf', 'icon' => 'home', 'active' => false],
            ['key' => 'menu', 'label' => 'Menu', 'href' => '/staf?tab=menu', 'icon' => 'grid', 'active' => false],
            ['key' => 'aktivitas', 'label' => 'Aktivitas', 'href' => '/staf?tab=aktivitas', 'icon' => 'bell', 'active' => false],
            ['key' => 'akun', 'label' => 'Akun', 'href' => '/staf?tab=akun', 'icon' => 'user', 'active' => false],
        ];
    }
}
