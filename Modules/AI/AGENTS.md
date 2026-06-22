# AI "Khadim Digital" — Peta Modul (AGENTS.md)
> Orientasi cepat. Untuk tugas di modul ini, muat: folder Modules/AI/ + .d.ts terkait + resources/js/Components/Ui/README.md. Acuan: docs/KONVENSI.md, docs/PLAYBOOK-IRISAN.md. Detail lengkap: Modules/AI/README.md.

**Domain:** Asisten AI ber-pagar syariah pesantren. Satu-satunya pintu LLM = AiGateway. Cakupan: Gateway LLM (Ollama) + router berjenjang, Guardrails (PolicyEngine/sitasi/disclaimer), Privasi (redaksi PII + anti-injection), RAG bersitasi, Eskalasi "Tanya Ustadz", Metering kredit, Eval gate rilis, FAQ-mining, chatbot WA wali, Generator asatidz (Soal/RPP/Ikhtisar-Kultum), Audit, Telemetri riset opt-in.

**Model inti:** (`Modules/AI/app/Models/`) AiAgent, AiPromptVersion, AiModelConfig (CENTRAL), AiTenantSetting + AiApproval (versioned + approval kyai), AiPolicy + AiPolicyRule, AiEscalation, AiKnowledgeSource + AiChunk + AiCitation (RAG), AiConversation + AiMessage (WA), AiCreditLedger + AiTenantBudget (CENTRAL), AiEvalCase + AiEvalRun (CENTRAL), AiResearchEvent (CENTRAL), AiFaqDraf, AiDrafSoal/AiDrafRpp/AiDrafIkhtisar.

**Service/Event inti:** (`app/Services/`) Gateway/AiGateway + ModelRouter + CircuitBreaker + OllamaProvider/NullProvider; Guardrails/PolicyEngine + CitationEnforcer + DisclaimerInjector; Privacy/PiiRedactor + ContentSanitizer; Rag/RagAnswerer + Retriever + IngestService; Escalation/EscalationService; Metering/CreditMeter + BudgetGuard; Settings/TenantSettingService; Research/FaqMiner + ResearchTelemetry; Wa/WaChatService + IntentRouter + WaBalasanService + WaliVerifier; Tools/ToolRegistry (read-only adapters). Jobs: ProsesPesanWaJob, IngestDocumentJob, Generate{Soal,Rpp,Ikhtisar}Job. Console: ai:health, ai:eval, ai:faq-mining, ai:anomali, ai:prompt, ai:eskalasi-pengingat dll.

**UI Filament:** TIDAK ada Resource — hanya Pages (`app/Filament/Pages/` → target `Modules/AI/app/Filament/`), grup nav `AI Khadim Digital`. Panel TENANT: PengaturanAi, PersetujuanAi, SumberPengetahuan, EskalasiTanyaUstadz, GeneratorSoal, GeneratorRpp, IkhtisarKitab, KotakMasukWa, UsulanFaq. Panel PLATFORM `admin` (super_admin): SuaraPasar (dashboard telemetri). Didaftarkan via `afterResolving(PanelRegistry)` di AIServiceProvider (bukan PanelProvider app).

**Endpoint/portal terkait:** `routes/web.php` (domain tenant): ai.ringkasan.signed, ai.tanya-ustadz.eskalasi, ai.rating-jawaban, ai.draf-soal.cetak, ai.draf-rpp.cetak/unduh, ai.draf-ikhtisar.cetak. `routes/api.php` (domain CENTRAL): ai.webhook.wa (POST /api/ai/webhook/wa — webhook WA, auth = header X-Webhook-Token). Tidak ada route AI di routes/tenant.php (Filament + RouteServiceProvider sendiri).

**Migrasi & seeder:** `database/migrations/` (TENANT, ikut tenants:migrate) + `database/migrations/central/` (CENTRAL: ai_model_config, ai_credit_ledger, ai_tenant_budget, prompt registry, ai_research_event, ai_eval — TIDAK ikut ke tenant). Seeder: AiAgentSeeder, AiModelConfigSeeder, AiPolicySeeder, AiTemplateRppSeeder, AiEvalCaseSeeder (impor idempoten dari `seeders/data/*.json`).

**Jebakan modul ini:**
- AI: satu-satunya pintu LLM = AiGateway (host/port Ollama hanya di config.php + Services/Gateway/; dilarang HTTP LLM langsung — ditangkap AiArchitectureLintTest). Eval gate BUKAN CI; jalankan `php artisan ai:eval --suite=...` sebelum rilis (injection wajib 100%). Test modul: worktree wt-ai + `phpunit.ai.xml` (DB `pesantren_platform_test_ai`).
- Policy-first: perilaku per pesantren hidup di DATA (ai_tenant_setting versioned + approval kyai), bukan kode; konten keagamaan HANYA dari seed GATE-3, jangan dikarang (Aturan Emas #8/#11).
- Tenancy: tabel CENTRAL (ai_model_config, ai_credit_ledger, ai_tenant_budget, ai_eval_*, ai_research_event) hidup di `migrations/central/`; SuaraPasar baca central dari panel admin.
- Notifikasi WA: outbound balasan HANYA lewat Modules/Notifikasi (WaBalasanService → WaGatewayService, Aturan Emas #5) → 1 workflow n8n generik → WAHA; cek field 'sent'/'gateway_status', BUKAN status eksekusi (selalu hijau walau gagal).
- File tenant: dokumen RAG di Sumber Pengetahuan — serve via route /_tenant-storage, BUKAN Storage::url (404 di Docker).
- Filament v5: grup `AI Khadim Digital` berikon + item berikon → panel authed 500; WAJIB TenantPanelAuthedSmokeTest tiap ubah Pages.

**Status rollout 5-lapisan:** Fase AI / terpisah — `ai` di module_registry berstatus **`development`** (ModuleRegistrySeeder, sort 19): hanya tampil di tenant demo/developer sampai Gate A lulus, BELUM `released`. PolicyEngine gerbang a0 menolak tenant non-demo. Modul UANG-adjacent (metering kredit + tool keuangan read-only) namun TIDAK posting jurnal. Butuh smoke authed (Pages Filament) + eval gate sebelum dirilis.
