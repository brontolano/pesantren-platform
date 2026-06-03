<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold">Ringkasan PPDB</h1>
        <p class="text-sm text-gray-600">Statistik pendaftaran dan hasil seleksi calon santri untuk tenant aktif.</p>
    </div>

    <div class="grid gap-4 lg:grid-cols-5">
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-gray-500">Total Calon Santri</p>
            <p class="mt-3 text-3xl font-semibold">{{ $counts['total'] }}</p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-gray-500">Lulus</p>
            <p class="mt-3 text-3xl font-semibold">{{ $counts['lulus'] }}</p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-gray-500">Cadangan</p>
            <p class="mt-3 text-3xl font-semibold">{{ $counts['cadangan'] }}</p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-gray-500">Ditolak</p>
            <p class="mt-3 text-3xl font-semibold">{{ $counts['ditolak'] }}</p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-gray-500">Belum Diverifikasi</p>
            <p class="mt-3 text-3xl font-semibold">{{ $counts['belum_verifikasi'] }}</p>
        </div>
    </div>
</div>
