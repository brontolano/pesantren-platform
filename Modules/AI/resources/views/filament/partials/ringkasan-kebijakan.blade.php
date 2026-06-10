{{-- T-A011 — Ringkasan Kebijakan (template deterministik, BUKAN LLM).
     Dipakai langkah akhir Wizard DAN halaman persetujuan Kyai. --}}
<div class="grid gap-4 md:grid-cols-2">
    <div class="rounded-xl border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-950">
        <p class="mb-2 text-sm font-semibold text-green-800 dark:text-green-200">
            Asisten AI di pesantren ini AKAN:
        </p>
        <ul class="list-disc space-y-1 ps-5 text-sm text-green-900 dark:text-green-100">
            @foreach ($ringkasan['akan'] as $kalimat)
                <li>{{ $kalimat }}</li>
            @endforeach
        </ul>
    </div>
    <div class="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-950">
        <p class="mb-2 text-sm font-semibold text-red-800 dark:text-red-200">
            Dan TIDAK AKAN:
        </p>
        <ul class="list-disc space-y-1 ps-5 text-sm text-red-900 dark:text-red-100">
            @foreach ($ringkasan['tidak_akan'] as $kalimat)
                <li>{{ $kalimat }}</li>
            @endforeach
        </ul>
    </div>
</div>
