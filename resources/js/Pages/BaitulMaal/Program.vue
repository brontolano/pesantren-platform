<script setup>
import { Head, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { AppShell, PageHeader, SectionCard, ListRow, StatusBadge, MoneyText, IconCircle, BottomSheet, UiButton, UiField, UiInput, EmptyState } from '@/Shared'

const selectClass = 'w-full rounded-2xl bg-white px-4 min-h-[48px] py-3 text-sm font-medium text-slate-900 ring-1 ring-inset ring-slate-200 transition-all duration-200 hover:ring-slate-300 focus:outline-none focus:ring-2 focus:ring-[var(--wali-primary,#059669)]'

const props = defineProps({
    navigation: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
    program: { type: Array, default: () => [] },
    sumberDana: { type: Array, default: () => [] },
})

const sheetOpen = ref(false)
const editing = ref(null)
const form = ref({ nama: '', deskripsi: '', target_nominal: 0, sumber_dana_id: '' })
const menyimpan = ref(false)

function bukaBaru() {
    editing.value = null
    form.value = { nama: '', deskripsi: '', target_nominal: 0, sumber_dana_id: '' }
    sheetOpen.value = true
}

function bukaEdit(p) {
    editing.value = p
    form.value = { nama: p.nama, deskripsi: p.deskripsi ?? '', target_nominal: p.target_nominal, aktif: p.aktif }
    sheetOpen.value = true
}

function simpan() {
    menyimpan.value = true
    const url = editing.value ? `/baitul-maal/program/${editing.value.id}` : '/baitul-maal/program'
    router.post(url, form.value, {
        preserveScroll: true,
        onFinish: () => { menyimpan.value = false; sheetOpen.value = false },
    })
}

function progres(p) {
    if (!p.target_nominal) return 0
    return Math.min(100, Math.round((p.terkumpul / p.target_nominal) * 100))
}
</script>

<template>
    <Head title="Program Donasi · Baitul Maal" />

    <AppShell :nav-items="navigation" brand-name="Baitul Maal">
        <div class="space-y-5">
            <PageHeader kicker="Baitul Maal" title="Program Donasi" subtitle="Kampanye penggalangan dana">
                <template v-if="can.kelola" #right>
                    <UiButton variant="primary" size="sm" icon-left="add" @click="bukaBaru">Baru</UiButton>
                </template>
            </PageHeader>

            <SectionCard flush>
                <EmptyState v-if="!program.length" icon="inbox" title="Belum ada program" message="Buat program donasi pertama Anda." />
                <div v-else class="divide-y divide-slate-100">
                    <button
                        v-for="p in program"
                        :key="p.id"
                        type="button"
                        class="block w-full px-1 py-3 text-left"
                        @click="can.kelola && bukaEdit(p)"
                    >
                        <ListRow :title="p.nama" :subtitle="`${p.jumlah_donasi} donasi`">
                            <template #leading><IconCircle icon="inbox" tone="emerald" /></template>
                            <template #trailing>
                                <StatusBadge :status="p.aktif ? 'lunas' : 'ditolak'" :label="p.aktif ? 'Aktif' : 'Nonaktif'" size="sm" />
                            </template>
                        </ListRow>
                        <div class="mt-2 px-1">
                            <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-violet-500" :style="{ width: progres(p) + '%' }" />
                            </div>
                            <div class="mt-1 flex items-center justify-between text-xs text-slate-500">
                                <MoneyText :amount="p.terkumpul" size="sm" tone="emerald" />
                                <span>dari <MoneyText :amount="p.target_nominal" size="sm" /></span>
                            </div>
                        </div>
                    </button>
                </div>
            </SectionCard>
        </div>

        <BottomSheet v-model="sheetOpen" :title="editing ? 'Ubah Program' : 'Program Baru'">
            <div class="space-y-3">
                <UiField label="Nama Program">
                    <template #default="{ fieldId, describedby }">
                        <UiInput :id="fieldId" v-model="form.nama" type="text" :aria-describedby="describedby" />
                    </template>
                </UiField>
                <UiField label="Deskripsi (opsional)">
                    <template #default="{ fieldId, describedby }">
                        <UiInput :id="fieldId" v-model="form.deskripsi" type="text" :aria-describedby="describedby" />
                    </template>
                </UiField>
                <UiField label="Target Nominal (Rp)">
                    <template #default="{ fieldId, describedby }">
                        <UiInput :id="fieldId" v-model.number="form.target_nominal" type="number" min="0" :aria-describedby="describedby" />
                    </template>
                </UiField>
                <UiField v-if="!editing" label="Sumber Dana">
                    <template #default="{ fieldId }">
                        <select :id="fieldId" v-model="form.sumber_dana_id" :class="selectClass" required>
                            <option value="" disabled>Pilih sumber dana…</option>
                            <option v-for="s in sumberDana" :key="s.id" :value="s.id">{{ s.kode_dana }} — {{ s.nama_sumber }}</option>
                        </select>
                    </template>
                </UiField>
            </div>
            <template #footer>
                <div class="flex gap-2">
                    <UiButton variant="ghost" block :disabled="menyimpan" @click="sheetOpen = false">Batal</UiButton>
                    <UiButton variant="primary" icon-left="check" block :disabled="menyimpan || !form.nama || (!editing && !form.sumber_dana_id)" @click="simpan">
                        Simpan
                    </UiButton>
                </div>
            </template>
        </BottomSheet>
    </AppShell>
</template>
