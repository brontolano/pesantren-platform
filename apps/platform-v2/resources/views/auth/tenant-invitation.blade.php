<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Aktifkan akun admin · {{ $organization->name }}</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body><main class="login-wrap"><section class="login-card card" aria-labelledby="invite-title">
    <p class="eyebrow">Undangan admin pertama</p><h1 id="invite-title">Aktifkan akun {{ $organization->name }}</h1>
    <p>Undangan berlaku sampai {{ $invitation->expires_at->timezone('Asia/Jakarta')->format('d M Y, H:i') }} WIB dan hanya dapat dipakai sekali.</p>
    @if($errors->any())<div class="notice notice-error" role="alert">{{ $errors->first() }}</div>@endif
    <form class="login-form" method="post" action="{{ route('tenant.invitation.store', $token) }}">@csrf
        <div class="field"><label for="name">Nama admin</label><input id="name" name="name" class="input" autocomplete="name" required value="{{ old('name') }}"></div>
        <div class="field"><label for="password">Buat kata sandi</label><input id="password" name="password" class="input" type="password" autocomplete="new-password" minlength="12" required><span class="field-hint">Gunakan minimal 12 karakter.</span></div>
        <div class="field"><label for="password_confirmation">Ulangi kata sandi</label><input id="password_confirmation" name="password_confirmation" class="input" type="password" autocomplete="new-password" minlength="12" required></div>
        <button class="button" type="submit">Aktifkan akun</button>
    </form>
</section></main></body></html>
