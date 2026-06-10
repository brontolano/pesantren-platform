{{--
    T-A090 — <x-ai::rating-jawaban :agent="..."> : bintang 1–5 kepuasan
    jawaban per agen.

    POST ke route ai.rating-jawaban (auth + tenant) →
    ResearchTelemetry::rekamRating — NO-OP SENYAP bila tenant tidak opt-in
    riset (pagar T-A013 sudah di service); UI tetap mengucapkan terima kasih
    tanpa membeda-bedakan (tidak membocorkan status opt-in tenant).

    Satu form, lima tombol submit (name="rating") — tanpa JavaScript wajib,
    target sentuh 44×44 px (h-11 w-11).
--}}
@props([
    'agent',
    'label' => 'Seberapa membantu jawaban ini?',
])

<form
    method="POST"
    action="{{ route('ai.rating-jawaban') }}"
    {{ $attributes->merge(['class' => 'ai-rating-jawaban']) }}
>
    @csrf
    <input type="hidden" name="agent" value="{{ $agent }}">
    <fieldset class="border-0 p-0">
        <legend class="mb-1 text-xs text-gray-600 dark:text-gray-300">{{ $label }}</legend>
        <div class="flex items-center gap-1" role="group" aria-label="Pilih nilai 1 sampai 5 bintang">
            @for ($i = 1; $i <= 5; $i++)
                <button
                    type="submit"
                    name="rating"
                    value="{{ $i }}"
                    class="inline-flex h-11 w-11 items-center justify-center rounded-lg text-2xl leading-none transition hover:bg-gray-100 dark:hover:bg-gray-700"
                    style="color: #b45309;"
                    aria-label="Beri nilai {{ $i }} dari 5"
                >
                    <span aria-hidden="true">&#9733;</span>
                </button>
            @endfor
        </div>
    </fieldset>
</form>
