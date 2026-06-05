# Backup Workflow n8n — Notifikasi WhatsApp (WAHA)

Sumber kebenaran workflow n8n **`ASF PPDB WA Notify (WAHA)`** (id live: `Q6sFptetoI7uM6Oy`).
Workflow hidup di volume `n8n_data` pada VPS; file ini backup agar bisa di-restore bila n8n di-reset.

## Alur

```
Platform Laravel ──POST──> https://n8n.asy-syifaa.com/webhook/ppdb-wa
        │                          │
        │                   PPDB Webhook → Validate Payload → Send WAHA → Respond
        │                                                         │
        └─ 2 pemanggil:                                  POST http://waha:3000/api/sendText
           1. PpdbWhatsappNotifier  (field `to`)         (header X-Api-Key)
           2. WaGatewayService      (field `target`)     → WhatsApp
```

Webhook **generik**: node `Validate Payload` menerima `to` **atau** `target`, jadi 3 jalur
(registrasi sukses, push notifikasi, broadcast) memakai pipa yang sama.

## Variabel environment (di service `n8n`, docker-compose.production.yml)

| Var | Nilai |
|---|---|
| `N8N_BLOCK_ENV_ACCESS_IN_NODE` | `false` (wajib — node Code baca `$env`) |
| `N8N_WAHA_BASE_URL` | `http://waha:3000` |
| `N8N_WAHA_API_KEY` | `${WAHA_API_KEY}` |
| `N8N_WAHA_SESSION` | `default` |

## 3 bug yang sudah diperbaiki (jangan terulang saat restore)

1. **Validate Payload** harus baca `item.json.body` (webhook v2 membungkus body POST), bukan `item.json` langsung.
2. **Send WAHA** harus pakai `this.helpers.httpRequest(...)` — `fetch` TIDAK ada di Code node n8n.
3. **`$env`** diblokir default → set `N8N_BLOCK_ENV_ACCESS_IN_NODE=false` lalu recreate container n8n.

## Cara restore

1. Buka https://n8n.asy-syifaa.com → **Workflows** → menu (…) → **Import from File**.
2. Pilih `ASF-PPDB-WA-Notify.json`.
3. Pastikan env di atas ada (kalau perlu `docker compose ... up -d --force-recreate n8n`).
4. **Activate** workflow (toggle kanan atas) — path produksi `/webhook/ppdb-wa` baru hidup setelah aktif.

> ⚠️ Token MCP n8n yang pernah dibagikan di chat adalah JWT tanpa kedaluwarsa — sebaiknya **dirotasi**.
