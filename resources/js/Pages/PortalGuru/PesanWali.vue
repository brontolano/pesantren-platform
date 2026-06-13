<script setup>
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import PortalGuruLayout from '../../Layouts/PortalGuruLayout.vue'
import {
    PageHeader,
    SectionCard,
    UiCard,
    UiField,
    UiButton,
    SegmentedControl,
    StatusBadge,
    EmptyState,
    UiIcon,
} from '@/Shared'

defineOptions({
    layout: PortalGuruLayout,
})

const props = defineProps({
    santri: { type: Array, default: () => [] },
    templates: { type: Array, default: () => [] },
    moderasiPerhatian: { type: Boolean, default: true },
    riwayat: { type: Array, default: () => [] },
    istilah: { type: Object, default: () => ({}) },
})

const form = useForm({
    santri_id: '',
    jenis: 'apresiasi',
    isi: '',
    client_ref: crypto.randomUUID(),
})

const templateUntukJenis = computed(() =>
    props.templates.filter((t) => t.jenis === form.jenis),
)

function pakaiTemplate(t) {
    form.jenis = t.jenis
    form.isi = t.isi
}

function kirim() {
    form.post('/guru/pesan', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('isi')
            form.client_ref = crypto.randomUUID()
        },
    })
}

const labelJenis = {
    apresiasi: 'Apresiasi',
    perhatian: 'Perhatian',
    info: 'Info',
}

const warnaJenis = {
    apresiasi: 'bg-emerald-50 text-emerald-700',
    perhatian: 'bg-amber-50 text-amber-700',
    info: 'bg-sky-50 text-sky-700',
}

const warnaStatus = {
    menunggu_moderasi: 'bg-amber-50 text-amber-700',
    terkirim: 'bg-emerald-50 text-emerald-700',
    ditolak: 'bg-rose-50 text-rose-700',
    draft: 'bg-slate-100 text-slate-600',
}

const labelStatus = {
    menunggu_moderasi: 'Menunggu Moderasi',
    terkirim: 'Terkirim',
    ditolak: 'Ditolak',
    draft: 'Draf',
}
</script>

<template>
    <div class="space-y-5">
        <Head>
            <title>Pesan Wali</title>
        </Head>

        <PageHeader
            kicker="Pesan Wali"
            :title="`Kirim Pesan ke Wali ${istilah.santri || 'Santri'}`"
        />
        <p class="-mt-2 text-sm text-slate-500">
            Hanya {{ (istilah.santri || 'santri').toLowerCase() }} binaan Anda yang bisa dipilih.
            <span v-if="moderasiPerhatian">Pesan jenis <strong>perhatian</strong> ditinjau pengurus dulu sebelum terkirim.</span>
        </p>

        <!-- Form kirim -->
        <form @submit.prevent="kirim">
            <UiCard padding="md" class="space-y-4">
                <UiField :label="istilah.santri || 'Santri'" :error="form.errors.santri_id">
                    <template #default="{ fieldId, describedby, invalid }">
                        <select
                            :id="fieldId"
                            v-model="form.santri_id"
                            :aria-describedby="describedby"
                            required
                            class="min-h-[48px] w-full rounded-2xl bg-white px-4 text-sm font-medium text-slate-900 ring-1 ring-inset transition-all duration-200 focus:outline-none focus:ring-2"
                            :class="invalid ? 'ring-rose-300 focus:ring-rose-500' : 'ring-slate-200 hover:ring-slate-300 focus:ring-[var(--wali-primary,#059669)]'"
                        >
                            <option value="" disabled>Pilih…</option>
                            <option v-for="s in santri" :key="s.id" :value="s.id">{{ s.nama }} ({{ s.nis || '—' }})</option>
                        </select>
                    </template>
                </UiField>

                <UiField label="Jenis Pesan">
                    <SegmentedControl
                        v-model="form.jenis"
                        :options="Object.entries(labelJenis).map(([value, label]) => ({ value, label }))"
                    />
                </UiField>

                <UiField v-if="templateUntukJenis.length > 0" label="Template Siap-Pakai">
                    <div class="flex flex-wrap gap-2">
                        <UiButton
                            v-for="t in templateUntukJenis"
                            :key="t.label"
                            variant="secondary"
                            size="sm"
                            type="button"
                            @click="pakaiTemplate(t)"
                        >
                            {{ t.label }}
                        </UiButton>
                    </div>
                </UiField>

                <UiField label="Isi Pesan" :error="form.errors.isi">
                    <template #default="{ fieldId, describedby, invalid }">
                        <textarea
                            :id="fieldId"
                            v-model="form.isi"
                            :aria-describedby="describedby"
                            rows="5"
                            required
                            minlength="5"
                            maxlength="2000"
                            placeholder="Pilih template lalu sunting, atau tulis sendiri…"
                            class="w-full rounded-2xl bg-white px-4 py-3 text-sm font-medium leading-6 text-slate-900 ring-1 ring-inset transition-all duration-200 placeholder:font-normal placeholder:text-slate-400 focus:outline-none focus:ring-2"
                            :class="invalid ? 'ring-rose-300 focus:ring-rose-500' : 'ring-slate-200 hover:ring-slate-300 focus:ring-[var(--wali-primary,#059669)]'"
                        />
                    </template>
                </UiField>

                <UiButton
                    type="submit"
                    block
                    :loading="form.processing"
                    :disabled="form.processing"
                >
                    {{ form.jenis === 'perhatian' && moderasiPerhatian ? 'Ajukan (lewat moderasi)' : 'Kirim ke Wali' }}
                </UiButton>
            </UiCard>
        </form>

        <!-- Riwayat -->
        <SectionCard v-if="riwayat.length === 0" title="Riwayat Pesan Saya" flush>
            <EmptyState
                icon="inbox"
                title="Belum ada pesan"
                message="Pesan yang Anda kirim akan tampil di sini."
            />
        </SectionCard>

        <section v-else class="space-y-3">
            <PageHeader title="Riwayat Pesan Saya" />
            <div class="space-y-3">
                <UiCard v-for="p in riwayat" :key="p.id" padding="md">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-base font-semibold text-slate-900">{{ p.santri }}</p>
                        <StatusBadge
                            v-if="p.status === 'terkirim'"
                            status="terverifikasi"
                            :label="labelStatus.terkirim"
                            size="sm"
                        />
                        <StatusBadge
                            v-else-if="p.status === 'menunggu_moderasi'"
                            status="menunggu"
                            :label="labelStatus.menunggu_moderasi"
                            size="sm"
                        />
                        <StatusBadge
                            v-else-if="p.status === 'ditolak'"
                            status="ditolak"
                            :label="labelStatus.ditolak"
                            size="sm"
                        />
                        <StatusBadge
                            v-else
                            :status="p.status"
                            :label="labelStatus[p.status] || p.status"
                            size="sm"
                        />
                    </div>
                    <div class="mt-1 flex items-center gap-2 text-xs text-slate-500">
                        <span class="rounded-full px-2 py-0.5" :class="warnaJenis[p.jenis]">{{ labelJenis[p.jenis] || p.jenis }}</span>
                        <span>{{ p.sent_at || p.created_at }}</span>
                        <span v-if="p.dikirim_via" class="text-slate-400">via {{ p.dikirim_via }}</span>
                    </div>
                    <p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ p.isi }}</p>
                    <p v-if="p.alasan_tolak" class="mt-2 flex items-start gap-1.5 rounded-xl bg-rose-50 px-3 py-2 text-xs text-rose-700">
                        <UiIcon name="x-circle" class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                        <span>Ditolak moderator: {{ p.alasan_tolak }}</span>
                    </p>
                </UiCard>
            </div>
        </section>
    </div>
</template>
