<?php

namespace App\Http\Requests\PortalWali;

use Illuminate\Foundation\Http\FormRequest;

class StorePortalWaliPembayaranRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('view_own_children');
    }

    public function rules(): array
    {
        return [
            'nominal' => ['required', 'numeric', 'min:1'],
            'metode' => ['required', 'string', 'max:50'],
            'tanggal_pembayaran' => ['required', 'date'],
            'nomor_bukti' => ['nullable', 'string', 'max:100'],
            'catatan' => ['nullable', 'string', 'max:1000'],
            'bukti_pembayaran' => ['required', 'image', 'max:2048'],
        ];
    }
}
