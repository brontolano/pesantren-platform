<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PortalGuruLayout from '../../Layouts/PortalGuruLayout.vue'
import {
    PageHeader,
    SectionCard,
    UiCard,
    UiButton,
    UiField,
    UiInput,
    StatCard,
    EmptyState,
} from '@/Shared'

defineOptions({
    layout: PortalGuruLayout,
})

const props = defineProps({
    kelas: { type: Object, required: true },
    periode: { type: Object, required: true },
    rekap: { type: Array, default: () => [] },
    rubrik_ubudiyah_url: { type: String, default: null },
})

const terbuka = ref(null) // santri_id yang form catatannya terbuka
const catatanDraft = ref('')
const saving = ref(false)
const errorMessage = ref(null)

function bukaCatatan(row) {
    terbuka.value = row.santri_id
    catatanDraft.value = row.catatan_walikelas || ''
    errorMessage.value = null
}

function simpanCatatan() {
    saving.value = true
    errorMessage.value = null

    router.post(`/guru/wali-kelas/${props.kelas.id}/catatan`, {
        santri_id: terbuka.value,
        catatan: catatanDraft.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            terbuka.value = null
        },
        onError: (errors) => {
            errorMessage.value = Object.values(errors)[0] || 'Catatan tidak valid.'
        },
        onFinish: () => {
            saving.value = false
        },
    })
}
</script>

<template>
    <div class="space-y-5">
        <Head>
            <title>Wali Kelas</title>
        </Head>

        <PageHeader
            kicker="Wali Kelas"
            :title="kelas.nama"
            :subtitle="`${periode.nama} · kehadiran, rata nilai, poin disiplin`"
        >
            <template #right>
                <UiButton
                    v-if="rubrik_ubudiyah_url"
                    :href="rubrik_ubudiyah_url"
                    variant="secondary"
                    size="sm"
                    iconRight="arrow-up-right"
                >
                    Input Rubrik Ubudiyah
                </UiButton>
            </template>
        </PageHeader>

        <EmptyState
            v-if="rekap.length === 0"
            icon="user"
            title="Belum ada santri aktif di kelas ini"
            message="Santri yang aktif pada periode ini akan muncul di sini."
        />

        <div v-else class="space-y-3">
            <SectionCard v-for="row in rekap" :key="row.santri_id" flush>
                <UiCard padding="md">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-900">{{ row.nama }}</p>
                            <p class="text-xs text-slate-400 tabular-nums">{{ row.nis }}</p>
                        </div>
                        <UiButton
                            type="button"
                            variant="ghost"
                            size="sm"
                            iconLeft="plus"
                            @click="bukaCatatan(row)"
                        >
                            {{ row.catatan_walikelas ? 'Edit Catatan' : 'Catatan' }}
                        </UiButton>
                    </div>

                    <div class="mt-3 grid grid-cols-3 gap-2">
                        <StatCard
                            tone="emerald"
                            icon="check-circle"
                            label="Hadir"
                            :value="`${row.kehadiran_persen}%`"
                            compact
                        />
                        <StatCard
                            tone="blue"
                            icon="chart"
                            label="Rata Nilai"
                            :value="row.rata_nilai ?? '—'"
                            compact
                        />
                        <StatCard
                            tone="rose"
                            icon="ban"
                            label="Poin"
                            :value="row.poin_disiplin"
                            compact
                        />
                    </div>

                    <p v-if="row.catatan_walikelas && terbuka !== row.santri_id" class="mt-3 text-xs italic text-slate-500">
                        "{{ row.catatan_walikelas }}"
                    </p>

                    <div v-if="terbuka === row.santri_id" class="mt-4 space-y-3">
                        <UiField label="Catatan wali kelas" :error="errorMessage">
                            <template #default="{ fieldId, describedby, invalid }">
                                <UiInput
                                    :id="fieldId"
                                    v-model="catatanDraft"
                                    type="textarea"
                                    :rows="3"
                                    :invalid="invalid"
                                    :aria-describedby="describedby"
                                    placeholder="Catatan wali kelas untuk rapor…"
                                />
                            </template>
                        </UiField>
                        <div class="flex gap-2">
                            <UiButton
                                type="button"
                                variant="primary"
                                size="sm"
                                block
                                :loading="saving"
                                :disabled="saving || !catatanDraft"
                                @click="simpanCatatan()"
                            >
                                {{ saving ? 'Menyimpan…' : 'Simpan ke Rapor' }}
                            </UiButton>
                            <UiButton
                                type="button"
                                variant="secondary"
                                size="sm"
                                @click="terbuka = null"
                            >
                                Batal
                            </UiButton>
                        </div>
                    </div>
                </UiCard>
            </SectionCard>
        </div>
    </div>
</template>
