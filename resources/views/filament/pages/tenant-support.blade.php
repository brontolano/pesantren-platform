<x-filament-panels::page>
    <div class="grid gap-5 xl:grid-cols-[minmax(280px,.8fr)_minmax(0,1.4fr)]">
        <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900 sm:p-5">
            <h2 class="text-base font-bold text-gray-950 dark:text-white">Minta bantuan</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Ceritakan kendala yang terjadi. Tim platform akan menindaklanjutinya.</p>
            <form wire:submit="openTicket" class="mt-4 space-y-4">
                <div>
                    <label for="support-category" class="mb-1 block text-sm font-medium">Jenis bantuan</label>
                    <select id="support-category" wire:model="category" class="min-h-11 w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-800">
                        <option value="akun">Akun dan akses</option><option value="website">Website pesantren</option><option value="modul">Fitur atau modul</option><option value="teknis">Gangguan teknis</option><option value="lainnya">Lainnya</option>
                    </select>
                </div>
                <div>
                    <label for="support-subject" class="mb-1 block text-sm font-medium">Ringkasan kendala</label>
                    <input id="support-subject" wire:model="subject" maxlength="180" autocomplete="off" class="min-h-11 w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-800">
                    @error('subject')<p class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="support-impact" class="mb-1 block text-sm font-medium">Dampak pekerjaan</label>
                    <select id="support-impact" wire:model="impact" class="min-h-11 w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-800">
                        <option value="tidak_bisa_kerja">Pekerjaan utama terhenti</option><option value="sebagian_terhambat">Sebagian pekerjaan terhambat</option><option value="pertanyaan">Pertanyaan, pekerjaan tetap berjalan</option>
                    </select>
                    @error('impact')<p class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="support-description" class="mb-1 block text-sm font-medium">Apa yang terjadi?</label>
                    <textarea id="support-description" wire:model="description" rows="5" maxlength="10000" class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-800" placeholder="Sebutkan langkah yang dilakukan dan pesan yang muncul."></textarea>
                    @error('description')<p class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
                </div>
                <x-filament::button type="submit" class="w-full" wire:loading.attr="disabled" wire:target="openTicket">Kirim permintaan bantuan</x-filament::button>
            </form>
        </section>

        <section class="min-w-0 space-y-4" aria-label="Kasus bantuan pesantren">
            <h2 class="text-base font-bold text-gray-950 dark:text-white">Riwayat bantuan</h2>
            @forelse ($tickets as $ticket)
                <article class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900 sm:p-5">
                    <button type="button" wire:click="$set('selectedTicketId', {{ $ticket->id }})" class="min-h-11 w-full text-left focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="text-xs font-semibold text-gray-500">{{ $ticket->ticket_number }} · {{ ucfirst($ticket->category) }}</span>
                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium dark:bg-gray-800">{{ ['open' => 'Terbuka', 'waiting' => 'Menunggu', 'closed' => 'Selesai'][$ticket->status] ?? $ticket->status }}</span>
                        </div>
                        <h3 class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $ticket->subject }}</h3>
                        <p class="mt-1 text-xs text-gray-500">Diperbarui {{ ($ticket->last_reply_at ?? $ticket->created_at)->translatedFormat('d M Y, H:i') }}</p>
                    </button>
                    @if ($selected?->id === $ticket->id)
                        <div class="mt-4 space-y-3 border-t border-gray-200 pt-4 dark:border-white/10">
                            <p class="whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-300">{{ $ticket->description }}</p>
                            @foreach ($ticket->replies as $reply)
                                <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                                    <div class="flex justify-between gap-2 text-xs text-gray-500"><span class="font-semibold">{{ $reply->author_name }}</span><time datetime="{{ $reply->created_at->toIso8601String() }}">{{ $reply->created_at->translatedFormat('d M Y, H:i') }}</time></div>
                                    <p class="mt-2 whitespace-pre-line text-sm leading-6">{{ $reply->body }}</p>
                                </div>
                            @endforeach
                            @if ($ticket->status !== 'closed')
                                <form wire:submit="reply" class="space-y-2">
                                    <label for="tenant-support-reply" class="block text-sm font-medium">Tambahkan informasi</label>
                                    <textarea id="tenant-support-reply" wire:model="replyBody" rows="3" maxlength="10000" class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-800"></textarea>
                                    @error('replyBody')<p class="text-sm text-danger-600">{{ $message }}</p>@enderror
                                    <div class="flex justify-end"><x-filament::button type="submit" size="sm" wire:loading.attr="disabled" wire:target="reply">Kirim balasan</x-filament::button></div>
                                </form>
                            @endif
                        </div>
                    @endif
                </article>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 p-8 text-center dark:border-gray-700">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Belum ada permintaan bantuan</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Jika memerlukan bantuan, kirim ringkasan melalui formulir ini.</p>
                </div>
            @endforelse
        </section>
    </div>
</x-filament-panels::page>
