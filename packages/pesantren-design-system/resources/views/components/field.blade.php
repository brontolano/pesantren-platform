@props(['label', 'name', 'hint' => null, 'error' => null, 'type' => 'text'])
@php($controlId = $attributes->get('id', $name))

<div class="pu-field">
    <label class="pu-field__label" for="{{ $controlId }}">
        {{ $label }}
        @if ($attributes->has('required'))
            <span class="pu-field__required">(wajib)</span>
        @endif
    </label>
    <input
        id="{{ $controlId }}"
        name="{{ $name }}"
        type="{{ $type }}"
        {{ $attributes->except(['id', 'required'])->class(['pu-input', 'pu-input--invalid' => filled($error)]) }}
        @if ($attributes->has('required')) required aria-required="true" @endif
        @if ($error) aria-invalid="true" aria-describedby="{{ $controlId }}-error" @elseif ($hint) aria-describedby="{{ $controlId }}-hint" @endif
    >
    @if ($hint && ! $error)
        <p id="{{ $controlId }}-hint" class="pu-field__hint">{{ $hint }}</p>
    @endif
    @if ($error)
        <p id="{{ $controlId }}-error" class="pu-field__error" role="alert">{{ $error }}</p>
    @endif
</div>