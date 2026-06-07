{{-- Thumbnail foto galeri untuk grid repeater admin.
     URL di-resolve via Gallery::resolveMediaUrl → /_tenant-storage/{path}
     (Storage::url 404 di Docker). Fallback eksplisit bila gambar gagal dimuat
     agar staff tahu fotonya bermasalah (bukan sekadar kotak kosong). --}}
@props(['url' => null])

<div class="overflow-hidden rounded-xl bg-gray-50 ring-1 ring-gray-200 dark:bg-white/5 dark:ring-white/10">
    @if ($url)
        <img
            src="{{ $url }}"
            alt="Foto galeri"
            loading="lazy"
            class="block aspect-video w-full object-cover"
            onerror="this.onerror=null;this.outerHTML='<div class=\'flex aspect-video w-full items-center justify-center gap-1.5 text-xs font-medium text-amber-600 dark:text-amber-400\'>&#9888; Gambar tidak dapat dimuat</div>';"
        >
    @else
        <div class="flex aspect-video w-full items-center justify-center text-xs text-gray-400">
            Belum ada foto
        </div>
    @endif
</div>
