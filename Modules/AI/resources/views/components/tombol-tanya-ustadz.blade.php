{{--
    T-A090 — <x-ai::tombol-tanya-ustadz :pertanyaan="..."> : tombol satu klik
    yang meneruskan pertanyaan ke antrean Tanya Ustadz (eskalasi MANUAL).

    POST ke route ai.tanya-ustadz.eskalasi (auth + tenant) →
    EscalationService::buatManual — idempoten per (user, hash pertanyaan):
    klik ganda tidak menggandakan antrean. Pertanyaan diredaksi PII PAKSA
    sebelum disimpan (pola PolicyEngine [M1]).
--}}
@props([
    'pertanyaan' => '',
    'label' => 'Tanya Ustadz',
])

<form
    method="POST"
    action="{{ route('ai.tanya-ustadz.eskalasi') }}"
    {{ $attributes->merge(['class' => 'ai-tombol-tanya-ustadz inline-block']) }}
>
    @csrf
    <input type="hidden" name="pertanyaan" value="{{ $pertanyaan }}">
    <button
        type="submit"
        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border bg-white px-4 py-2.5 text-sm font-semibold shadow-sm transition hover:opacity-90"
        style="border-color: #14532d; color: #14532d;"
        aria-label="Teruskan pertanyaan ini ke antrean Tanya Ustadz agar dijawab manusia"
    >
        <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm3.75 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm3.75 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
            <path d="M21 12c0 4.556-4.03 8.25-9 8.25-.88 0-1.73-.116-2.535-.332A8.99 8.99 0 0 1 4.5 21.04a8.97 8.97 0 0 0 1.106-2.852C4.273 16.755 3 14.504 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
        </svg>
        {{ $label }}
    </button>
</form>
