<?php

namespace Modules\PPDB\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\PPDB\Models\CalonSantriDokumen;
use Modules\PPDB\Models\TagihanPpdb;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serving file PPDB (dokumen peserta & bukti bayar) dari disk PRIVAT, dengan
 * kontrol akses: panitia (manage_santri) boleh semua; peserta hanya file
 * milik pendaftarannya sendiri. Tidak mengekspos path/disk ke publik.
 */
class PpdbFileController extends Controller
{
    public function dokumen(Request $request, int $dokumen): StreamedResponse
    {
        $doc = CalonSantriDokumen::query()->with('calonSantri')->findOrFail($dokumen);

        $this->authorizeAccess($request, $doc->calonSantri?->user_id);

        $disk = (is_array($doc->metadata) ? ($doc->metadata['disk'] ?? null) : null)
            ?: config('filesystems.default', 'local');

        abort_unless(is_string($doc->file_url) && Storage::disk($disk)->exists($doc->file_url), 404);

        return Storage::disk($disk)->download($doc->file_url);
    }

    public function bukti(Request $request, int $tagihan): StreamedResponse
    {
        $t = TagihanPpdb::query()->with('calonSantri')->findOrFail($tagihan);

        $this->authorizeAccess($request, $t->calonSantri?->user_id);

        $disk = config('filesystems.default', 'local');

        abort_unless(is_string($t->bukti_transfer) && Storage::disk($disk)->exists($t->bukti_transfer), 404);

        return Storage::disk($disk)->download($t->bukti_transfer);
    }

    /**
     * Panitia (manage_santri) boleh akses semua; selain itu hanya pemilik
     * (peserta yang user_id-nya cocok dgn pendaftaran terkait file).
     */
    private function authorizeAccess(Request $request, ?int $ownerUserId): void
    {
        $user = $request->user();
        abort_if($user === null, 403);

        if ($user->can('manage_santri')) {
            return;
        }

        abort_unless($ownerUserId !== null && (int) $ownerUserId === (int) $user->id, 403);
    }
}
