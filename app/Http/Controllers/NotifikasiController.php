<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Notifikasi\Models\LogNotifikasi;

/**
 * F4 — Riwayat notifikasi lintas-peran (/notifikasi): 20 terbaru milik
 * user yang login. Query sama persis dengan
 * PortalWaliService::getNotificationPayload() (target = email atau name),
 * digeneralisasi supaya dipakai peran apa pun.
 */
class NotifikasiController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $targets = array_filter([
            $user->email,
            $user->name,
        ]);

        $notifications = LogNotifikasi::query()
            ->where(function (Builder $query) use ($targets): void {
                foreach ($targets as $target) {
                    $query->orWhere('target', $target);
                }
            })
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->map(fn (LogNotifikasi $item): array => [
                'id' => $item->id,
                'jenis' => $item->jenis,
                'channel' => $item->channel,
                'target' => $item->target,
                'status' => $item->status,
                'errorMessage' => $item->error_message,
                'createdAt' => optional($item->created_at)?->format('Y-m-d H:i:s'),
                'payload' => $item->payload ?? [],
            ])
            ->values();

        return Inertia::render('Notifikasi/Index', [
            'notifications' => $notifications,
            'summary' => [
                'sentCount' => $notifications->where('status', 'sent')->count(),
                'failedCount' => $notifications->where('status', 'failed')->count(),
            ],
        ]);
    }
}
