<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\DemoAutoLoginController;
use App\Models\User;
use App\Services\Jemaah\JemaahAccountService;
use App\Services\Jemaah\JemaahIdentifierResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Modules\PPDB\Models\UserOtp;
use Modules\PPDB\Services\PesertaOtpService;
use Spatie\Permission\Models\Role;

/**
 * Auth aplikasi NATIVE (Android) — token Sanctum, per-tenant (resolve domain).
 *
 * - login  : peran "jemaah" (No.WA/email + password) → token. Pola disalin dari
 *            JemaahAuthController (throttle + gagal-seragam anti-enumerasi).
 * - me/logout: butuh `auth:sanctum`.
 * - demo   : autologin per-peran TOKEN (mirror DemoAutoLoginController) — DIGERBANG
 *            DEMO_AUTOLOGIN + tenant demo; selain itu 404. Aman walau ter-deploy.
 *
 * NOL logika uang/nilai. Token disimpan di DB tenant (personal_access_tokens).
 */
class AuthApiController extends Controller
{
    private const LOGIN_MAX_ATTEMPTS = 5;

    private const LOGIN_FAILED = 'Nomor WhatsApp / email atau password salah.';

    public function __construct(
        private readonly JemaahIdentifierResolver $resolver,
        private readonly JemaahAccountService $accountService,
    ) {}

    /** POST /api/v1/auth/login — login jemaah → token. */
    public function login(Request $request): JsonResponse
    {
        if (! $this->hasTenant()) {
            return response()->json(['ok' => false, 'message' => 'Pesantren tidak dikenali.'], 404);
        }

        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string'],
            'device' => ['nullable', 'string', 'max:255'],
        ]);

        $throttleKey = 'native-login:'.(tenant()?->getTenantKey() ?? 'central').':'
            .sha1(mb_strtolower(trim($data['identifier'])).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, self::LOGIN_MAX_ATTEMPTS)) {
            $detik = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'identifier' => "Terlalu banyak percobaan. Coba lagi dalam {$detik} detik.",
            ]);
        }

        $user = $this->resolver->resolve($data['identifier']);

        // Gagal SERAGAM (anti-enumerasi): akun tak ada / bukan jemaah / belum aktif
        // / password salah → satu pesan generik + selalu konsumsi jatah throttle.
        if ($user === null
            || $user->must_reset_password
            || ! $user->hasRole('jemaah')
            || ! Hash::check($data['password'], $user->password)
        ) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'identifier' => self::LOGIN_FAILED,
            ]);
        }

        RateLimiter::clear($throttleKey);

        return $this->tokenResponse($user, $data['device'] ?? 'native');
    }

    /** GET /api/v1/auth/me — profil dari token (auth:sanctum). */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['ok' => true, 'data' => $this->userPayload($request->user())]);
    }

    /** POST /api/v1/auth/logout — cabut token aktif (auth:sanctum). */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();
        if ($token !== null && method_exists($token, 'delete')) {
            $token->delete();
        }

        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/v1/auth/panel-link — tukar token Sanctum → URL auto-login panel web (SSO).
     *
     * Panel Filament autentik via COOKIE sesi web; aplikasi native pegang TOKEN. Endpoint ini
     * mengembalikan URL bertanda-tangan SEKALI-PAKAI (TTL pendek) yang, saat dibuka WebView,
     * men-set sesi web (lihat PanelMasukController) → panel terbuka tanpa login ulang.
     * NOL logika uang. Path di-whitelist (cegah open-redirect).
     */
    public function panelLink(Request $request): JsonResponse
    {
        $data = $request->validate(['path' => ['required', 'string', 'max:200']]);
        $path = '/'.ltrim($data['path'], '/');

        $allowed = ['/admin', '/app', '/pimpinan'];
        $ok = collect($allowed)->contains(
            fn (string $p) => $path === $p || str_starts_with($path, $p.'/')
        );
        abort_unless($ok, 422, 'Path tidak diizinkan.');

        $ttl = 60; // detik
        $url = URL::temporarySignedRoute('auth.panel-masuk', now()->addSeconds($ttl), [
            'user' => $request->user()->getKey(),
            'path' => $path,
            'nonce' => (string) Str::uuid(),
        ]);

        return response()->json(['ok' => true, 'data' => [
            'url' => $url,
            'expires_in' => $ttl,
        ]]);
    }

    /** POST /api/v1/auth/demo/{peran} — autologin demo TOKEN (gerbang demo). */
    public function demoLogin(string $peran): JsonResponse
    {
        abort_unless((bool) config('demo.autologin_enabled'), 404);
        $tenantKey = optional(tenant())->getTenantKey();
        abort_unless($tenantKey === config('demo.autologin_tenant'), 404);

        $persona = DemoAutoLoginController::personas()[$peran] ?? null;
        abort_unless($persona !== null, 404);

        $user = User::withoutGlobalScopes()->firstOrCreate(
            ['email' => $persona['email']],
            [
                'name' => $persona['name'],
                'password' => Hash::make(Str::random(40)),
                'tenant_id' => $tenantKey,
            ],
        );

        if (Role::where('name', $persona['role'])->exists() && ! $user->hasRole($persona['role'])) {
            $user->syncRoles([$persona['role']]);
        }

        return $this->tokenResponse($user, 'native-demo');
    }

    /**
     * POST /api/v1/auth/daftar — registrasi mandiri jemaah (No.WA + nama → OTP WA).
     * ANTI-ENUMERASI: respons selalu netral (JemaahAccountService menangani senyap).
     */
    public function daftar(Request $request): JsonResponse
    {
        if (! $this->hasTenant()) {
            return response()->json(['ok' => false, 'message' => 'Pesantren tidak dikenali.'], 404);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:120'],
        ], [], [
            'name' => 'nama',
            'phone' => 'nomor WhatsApp',
            'email' => 'email',
        ]);

        $result = $this->accountService->daftar($data['name'], $data['phone'], $data['email'] ?? null);

        return response()->json(['ok' => true, 'data' => [
            'identifier' => $result['phone'],
            'message' => 'Jika data Anda valid, kode aktivasi 6 angka dikirim ke WhatsApp. Masukkan kode itu untuk mengaktifkan akun.',
        ]]);
    }

    /**
     * POST /api/v1/auth/aktivasi — verifikasi OTP + set password → token (langsung
     * masuk). Anti-enumerasi: akun tak ada / sudah aktif → galat netral pada kode.
     */
    public function aktivasi(Request $request): JsonResponse
    {
        if (! $this->hasTenant()) {
            return response()->json(['ok' => false, 'message' => 'Pesantren tidak dikenali.'], 404);
        }

        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:120'],
            'kode' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [], [
            'identifier' => 'nomor WhatsApp / email',
            'kode' => 'kode verifikasi',
            'password' => 'password',
        ]);

        $user = $this->resolver->resolve($data['identifier']);

        if ($user === null || ! $user->must_reset_password) {
            throw ValidationException::withMessages([
                'kode' => 'Kode tidak valid atau akun tidak dapat diaktivasi. Periksa kembali atau minta kode baru.',
            ]);
        }

        app(PesertaOtpService::class)
            ->verify($user, UserOtp::PURPOSE_JEMAAH_AKTIVASI, $data['kode']);

        $this->accountService->markActivated($user, $data['password']);

        return $this->tokenResponse($user->fresh(), 'native');
    }

    /**
     * POST /api/v1/auth/kirim-ulang — kirim ulang OTP aktivasi. Respons selalu
     * netral (anti-enumerasi), tak peduli akun ada/aktif/tidak.
     */
    public function kirimUlang(Request $request): JsonResponse
    {
        if (! $this->hasTenant()) {
            return response()->json(['ok' => false, 'message' => 'Pesantren tidak dikenali.'], 404);
        }

        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:120'],
        ], [], [
            'identifier' => 'nomor WhatsApp / email',
        ]);

        try {
            $this->accountService->requestActivationOtp($data['identifier']);
        } catch (ValidationException) {
            // Jangan bocorkan eksistensi/status/rate-limit.
        }

        return response()->json(['ok' => true, 'data' => [
            'message' => 'Jika akun yang belum aktif terkait data itu ada, kode aktivasi dikirim ulang ke WhatsApp-nya.',
        ]]);
    }

    private function hasTenant(): bool
    {
        return tenancy()->initialized && tenant() !== null;
    }

    private function tokenResponse(User $user, string $device): JsonResponse
    {
        // Pra-aktivasi (tenants:migrate belum jalan) → degradasi ramah, bukan 500.
        if (! Schema::hasTable('personal_access_tokens')) {
            return response()->json([
                'ok' => false,
                'message' => 'Fitur masuk aplikasi belum diaktifkan. Hubungi admin pesantren.',
            ], 503);
        }

        $token = $user->createToken(Str::limit($device, 60, ''))->plainTextToken;

        return response()->json([
            'ok' => true,
            'data' => [
                'token' => $token,
                'user' => $this->userPayload($user),
            ],
        ]);
    }

    /** @return array<string, mixed>|null */
    private function userPayload(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => (string) $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames()->values()->all(),
            'canComment' => (bool) $user->can('komentar'),
        ];
    }
}
