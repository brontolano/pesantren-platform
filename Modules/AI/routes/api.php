<?php

use Illuminate\Support\Facades\Route;
use Modules\AI\Http\Controllers\WaWebhookController;

/*
 * T-A050 — webhook WA inbound (POST /api/ai/webhook/wa).
 *
 * Endpoint hidup di domain CENTRAL (n8n satu untuk semua tenant); resolusi
 * tenant terjadi DI DALAM controller (payload 'tenant' / peta session /
 * env AI_WA_TENANT pilot). Di luar auth web — keamanan = header
 * X-Webhook-Token (hash_equals, Aturan Emas #10: tanpa mekanisme auth baru).
 * Throttle group 'api' standar tetap berlaku (volume pilot kecil).
 */
Route::post('/ai/webhook/wa', WaWebhookController::class)->name('ai.webhook.wa');
