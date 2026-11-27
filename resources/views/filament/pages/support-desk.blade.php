<x-filament-panels::page>
    <div class="grid gap-5 xl:grid-cols-[minmax(280px,.8fr)_minmax(0,1.5fr)]">
        <section class="space-y-3" aria-label="Daftar kasus dukungan">
            <p class="text-sm text-gray-600 dark:text-gray-300">Kasus dari semua pesantren. Catatan internal hanya terlihat oleh tim platform.</p>
            @forelse ($tickets as $ticket)
                <button type="button" wire:click="selectTicket({{ $ticket->id }})" @class([
                    'w-full rounded-xl border p-4 text-left transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-600',
                    'border-primary-500 bg-primary-50 dark:bg-primary-950/30' => $selected?->id === $ticket->id,
                    'border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900' => $selected?->id !== $ticket->id,
                ])>
                    <div class="flex items-start justify-between gap-2">
                        <span class="text-xs font-semibold text-gray-500">{{ $ticket->ticket_number }}</span>
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs dark:bg-gray-800">{{ ucfirst($ticket->status) }}</span>
                    </div>
                    <span class="mt-2 block font-semibold text-gray-950 dark:text-white">{{ $ticket->subject }}</span>
                    <span class="mt-1 block text-sm text-gray-600 dark:text-gray-300">{{ $ticket->tenant?->name ?? $ticket->tenant_id }}</span>
                    <span class="mt-2 block text-xs text-gray-500">{{ $ticket->last_reply_at?->translatedFormat('d M Y, H:i') ?? $ticket->created_at?->translatedFormat('d M Y, H:i') }}</span>
                </button>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-600 dark:border-gray-700 dark:text-gray-300">Belum ada kasus dukungan.</div>
            @endforelse
        </section>

        <section class="min-w-0 rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900 sm:p-6">
            @if ($selected)
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-gray-200 pb-4 dark:border-white/10">
                    <div>
                        <p class="text-xs font-semibold text-gray-500">{{ $selected->ticket_number }} · {{ $selected->tenant?->name }}</p>
                        <h2 class="mt-1 text-lg font-bold text-gray-950 dark:text-white">{{ $selected->subject }}</h2>
                        <p class="mt-1 text-sm text-gray-500">Kategori: {{ ucfirst($selected->category) }} · Dampak: {{ str_replace('_', ' ', ucfirst($selected->impact)) }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <select wire:model="status" aria-label="Status kasus" class="min-h-11 rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-800">
                            <option value="open">Terbuka</option><option value="waiting">Menunggu</option><option value="closed">Selesai</option>
                        </select>
                        <select wire:model="priority" aria-label="Prioritas kasus" class="min-h-11 rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-800">
                            <option value="low">Rendah</option><option value="normal">Normal</option><option value="high">Tinggi</option><option value="urgent">Mendesak</option>
                        </select>
                        <x-filament::button color="gray" wire:click="updateStatus">Simpan status</x-filament::button>
                    </div>
                </div>
                <div class="my-4 rounded-lg bg-gray-50 p-4 text-sm leading-6 text-gray-700 dark:bg-gray-800 dark:text-gray-200 whitespace-pre-line">{{ $selected->description }}</div>
                <div class="max-h-[50vh] space-y-3 overflow-y-auto pr-1" aria-label="Percakapan kasus">
                    @foreach ($selected->replies as $reply)
                        <article class="rounded-lg border p-3 {{ $reply->is_internal ? 'border-amber-300 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/30' : 'border-gray-200 dark:border-gray-700' }}">
                            <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500">
                                <span class="font-semibold">{{ $reply->author_name }} @if ($reply->is_internal)<span class="ml-1 text-amber-800 dark:text-amber-200">· Catatan internal</span>@endif</span>
                                <time datetime="{{ $reply->created_at->toIso8601String() }}">{{ $reply->created_at->translatedFormat('d M Y, H:i') }}</time>
                            </div>
                            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-800 dark:text-gray-100">{{ $reply->body }}</p>
                        </article>
                    @endforeach
                </div>
                <form wire:submit="reply" class="mt-5 space-y-3 border-t border-gray-200 pt-4 dark:border-white/10">
                    <label for="support-reply" class="block text-sm font-semibold text-gray-800 dark:text-gray-100">Balas atau catat tindak lanjut</label>
                    <textarea id="support-reply" wire:model="replyBody" rows="4" maxlength="10000" class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-800" placeholder="Tulis informasi yang membantu menyelesaikan kasus ini."></textarea>
                    @error('replyBody')<p class="text-sm text-danger-600">{{ $message }}</p>@enderror
                    <label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" wire:model="internalNote" class="rounded border-gray-300"> Catatan internal (tidak terlihat oleh tenant)</label>
                    <div class="flex justify-end"><x-filament::button type="submit" wire:loading.attr="disabled" wire:target="reply">Kirim</x-filament::button></div>
                </form>
            @else
                <div class="py-16 text-center">
                    <h2 class="font-semibold text-gray-900 dark:text-white">Pilih kasus dukungan</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Isi kasus akan tampil di sini.</p>
                </div>
            @endif
        </section>
    </div>
</x-filament-panels::page>
