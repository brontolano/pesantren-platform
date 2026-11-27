<!doctype html><html lang="id"><body>
<h1>Undangan admin {{ $organization->name }}</h1>
<p>Anda diundang menjadi admin pertama untuk organisasi {{ $organization->name }}.</p>
<p><a href="{{ $invitationUrl }}">Buat akun admin</a></p>
<p>Tautan hanya dapat dipakai sekali dan kedaluwarsa pada {{ $expiresAt->timezone('Asia/Jakarta')->format('d M Y, H:i') }} WIB. Jika Anda tidak mengharapkan undangan ini, abaikan pesan ini.</p>
</body></html>
