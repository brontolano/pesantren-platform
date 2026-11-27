<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Masuk · {{ $organization->name }}</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body><main class="login-wrap"><section class="login-card card" aria-labelledby="login-title">
    <a class="brand" href="/" style="color:var(--po-ink);padding:0;margin-bottom:24px"><span class="brand-mark">{{ mb_substr($organization->name,0,1) }}</span><span>{{ $organization->name }}</span></a>
    <p class="eyebrow">Ruang organisasi</p><h1 id="login-title">Masuk ke panel organisasi</h1><p>Akun ini hanya berlaku untuk {{ $organization->canonical_domain }}.</p>
    @if($errors->any())<div class="notice notice-error" role="alert">{{ $errors->first() }}</div>@endif
    <form class="login-form" method="post" action="{{ route('tenant.login.store') }}">@csrf
        <div class="field"><label for="email">Email admin</label><input id="email" name="email" class="input" type="email" autocomplete="username" required value="{{ old('email') }}"></div>
        <div class="field"><label for="password">Kata sandi</label><input id="password" name="password" class="input" type="password" autocomplete="current-password" required></div>
        <button class="button" type="submit">Masuk</button>
    </form>
</section></main></body></html>
