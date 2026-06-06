<?php

namespace App\Http\Requests\PortalWali;

use Illuminate\Foundation\Http\FormRequest;

class BayarDariSaldoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('view_own_children');
    }

    public function rules(): array
    {
        return [
            'nominal' => ['required', 'numeric', 'min:1'],
        ];
    }
}
