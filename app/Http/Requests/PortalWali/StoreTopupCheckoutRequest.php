<?php

namespace App\Http\Requests\PortalWali;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Keuangan\Services\PaymentMethodCatalog;

/**
 * Validasi checkout Top Up Tabungan (DEMO gaya marketplace).
 *
 * Hanya butuh nominal + kode metode pembayaran (tanpa upload bukti — DEMO).
 */
class StoreTopupCheckoutRequest extends FormRequest
{
    public const NOMINAL_MIN = 10000;

    public const NOMINAL_MAX = 10000000;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('view_own_children');
    }

    public function rules(): array
    {
        return [
            'nominal' => ['required', 'numeric', 'min:' . self::NOMINAL_MIN, 'max:' . self::NOMINAL_MAX],
            'metode' => ['required', 'string', Rule::in((new PaymentMethodCatalog())->codes())],
        ];
    }

    public function messages(): array
    {
        return [
            'nominal.required' => 'Nominal top up wajib diisi.',
            'nominal.numeric' => 'Nominal harus berupa angka.',
            'nominal.min' => 'Nominal top up minimal Rp ' . number_format(self::NOMINAL_MIN, 0, ',', '.') . '.',
            'nominal.max' => 'Nominal top up maksimal Rp ' . number_format(self::NOMINAL_MAX, 0, ',', '.') . '.',
            'metode.required' => 'Silakan pilih metode pembayaran.',
            'metode.in' => 'Metode pembayaran tidak dikenali.',
        ];
    }
}
