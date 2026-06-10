<?php

namespace App\Http\Controllers\PortalWali;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PortalWali\Concerns\ResolvesPortalWaliContext;
use App\Services\PortalWali\PortalWaliService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Kesantrian\Services\SantriAccountService;

/**
 * Portal Wali — wali mengatur PIN login App Santri untuk anaknya sendiri.
 * Kepemilikan ditegakkan via accessibleSantri (wali hanya boleh anak yang
 * tertaut). PIN = 6 digit angka pilihan wali (mudah diingat anak).
 */
class PortalWaliSantriAkunController extends Controller
{
    use ResolvesPortalWaliContext;

    public function aturPin(Request $request, PortalWaliService $portalWaliService, SantriAccountService $akun): RedirectResponse
    {
        // accessibleSantri melempar 403/404 bila santri bukan anak wali ini.
        $santri = $this->accessibleSantri($request, $portalWaliService, (string) $request->route('santri'));

        $data = $request->validate([
            'pin' => ['required', 'string', 'regex:/^\d{6}$/'],
        ], [
            'pin.regex' => 'PIN harus 6 digit angka.',
        ]);

        $akun->setPin($santri, $data['pin']);

        return back()->with('success', 'PIN login App Santri untuk '.$santri->nama.' berhasil diperbarui.');
    }
}
