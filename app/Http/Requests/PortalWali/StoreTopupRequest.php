<?php

namespace App\Http\Requests\PortalWali;

use Illuminate\Foundation\Http\FormRequest;

class StoreTopupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('view_own_children');
    }

    public function rules(): array
    {
        return [
            'nominal' => ['required', 'numeric', 'min:1'],
            'tanggal_transfer' => ['required', 'date'],
            'nama_pengirim' => ['required', 'string', 'max:150'],
            'bukti' => ['required', 'image', 'max:2048'],
        ];
    }
}
