<?php

namespace App\Http\Requests\PortalWali;

use Illuminate\Foundation\Http\FormRequest;

class StorePortalWaliIzinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('view_own_children');
    }

    public function rules(): array
    {
        return [
            'santri_id' => ['required', 'integer'],
            'jenis_izin' => ['required', 'in:sakit,pulang,khusus'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'alasan' => ['required', 'string', 'max:1000'],
            'catatan_wali' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
