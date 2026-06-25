<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\DemoAutoLoginController;
use App\Models\User;
use App\Services\Jemaah\JemaahIdentifierResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
