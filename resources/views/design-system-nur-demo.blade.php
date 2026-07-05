<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Design System "Nur" — Demo</title>
    @vite('resources/css/app.css')
</head>
<body class="bg-[var(--surface-page)] p-8 font-body text-[var(--text-body)]">
    <h1 class="font-display font-extrabold text-[var(--fs-3xl)] text-[var(--text-strong)] mb-6">
        Design System "Nur"
    </h1>

    <div class="flex flex-wrap gap-3 mb-8">
        <x-ui.button variant="primary">Tombol Utama</x-ui.button>
        <x-ui.button variant="outline">Tombol Outline</x-ui.button>
        <x-ui.button variant="ghost">Tombol Ghost</x-ui.button>
        <x-ui.badge variant="accent">-20%</x-ui.badge>
        <x-ui.badge variant="gold">★ 4.9</x-ui.badge>
        <x-ui.badge variant="success">Lunas</x-ui.badge>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 max-w-2xl">
        <x-ui.product-card
            title="Sarung Tenun Premium"
            price="Rp180.000"
            old-price="Rp225.000"
            discount="20%"
        />
        <x-ui.product-card
            title="Kopiah Rajut"
            price="Rp65.000"
        />
    </div>
</body>
</html>
