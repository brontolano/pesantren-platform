<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * F4 — Halaman akun lintas-peran (/akun): profil + ubah password.
 * Dapat diakses oleh SIAPA PUN user tenant yang login (guru, kasir,
 * pimpinan, wali, pengelola_uks, dll) — tidak terikat satu portal,
 * hanya middleware `auth`, tanpa permission khusus.
 */
class AkunController extends Controller
{
    public function profil(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Akun/Profil', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? null,
            ],
            'from' => $request->query('from'),
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors([
                'current_password' => 'Kata sandi saat ini tidak sesuai.',
            ])->withInput($request->except(['password', 'password_confirmation', 'current_password']));
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        return back()->with('flash', [
            'success' => 'Kata sandi berhasil diperbarui.',
        ]);
    }
}
