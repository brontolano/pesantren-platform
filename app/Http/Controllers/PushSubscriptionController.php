<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * F6 — Web Push (VAPID). Opt-in per user: subscribe/unsubscribe browser push.
 * Route auth-gated saja (peran apa pun) — lihat routes/tenant.php.
 */
class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'endpoint' => ['required', 'string', 'max:2048'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Data langganan push tidak valid.', 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $user = $request->user();

        $subscription = PushSubscription::updateOrCreate(
            ['endpoint_hash' => PushSubscription::hashEndpoint($data['endpoint'])],
            [
                'tenant_id' => tenant('id'),
                'user_id' => $user->id,
                'endpoint' => $data['endpoint'],
                'p256dh' => $data['keys']['p256dh'],
                'auth' => $data['keys']['auth'],
            ],
        );

        return response()->json(['status' => 'subscribed', 'id' => $subscription->id], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'endpoint' => ['required', 'string', 'max:2048'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $deleted = PushSubscription::query()
            ->where('user_id', $request->user()->id)
            ->where('endpoint_hash', PushSubscription::hashEndpoint($data['endpoint']))
            ->delete();

        return response()->json(['status' => 'unsubscribed', 'deleted' => $deleted]);
    }
}
