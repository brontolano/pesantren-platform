<script setup>
import { Head } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import {
    AppShell, PageHeader, SectionCard, ListRow, StatusBadge, Chip,
    UiField, UiInput, EmptyState,
} from '@/Shared'

const props = defineProps({
    pendaftar: { type: Array, default: () => [] },
    navigation: { type: Array, default: () => [] },
})

const navIcons = {
    beranda: 'chart',
    pendaftar: 'user',
    verifikasi: 'check-circle',
    hasil: 'document',
}

const filterStatus = ref('semua')
const cari = ref('')

const filterOpsi = [
    { value: 'semua', label: 'Semua' },
    { value: 'menunggu', label: 'Menunggu' },
    { value: 'lunas', label: 'Lengkap' },
    { value: 'ditolak', label: 'Ditolak' },
]

const terfilter = computed(() => {
    const q = cari.value.trim().toLowerCase()
    return props.pendaftar.filter((p) => {
        const cocokStatus = filterStatus.value === 'semua' || p.status === filterStatus.value
        const cocokCari = !q || p.nama.toLowerCase().includes(q) || p.no.toLowerCase().includes(q)
        return cocokStatus && cocokCari
    })
})
</script>

<template>
    <Head title="Pendaftar — PPDB Panitia" />

    <AppShell :nav-items="navigation" :nav-icons="navIcons" brand-name="PPDB Panitia">
        <div class="space-y-5">
            <PageHeader
                kicker="PPDB"
                title="Daftar Pendaftar"
                :subtitle="`${terfilter.length} calon santri ditampilkan`"
            />

            <!-- Pencarian -->
            <UiField label="Cari calon santri">
                <template #default="{ fieldId, describedby }">
                    <UiInput
                        :id="fieldId"
                        :aria-describedby="describedby"
                        v-model="cari"
                        placeholder="Ketik nama atau nomor pendaftaran…"
                    />
                </template>
            </UiField>

            <!-- Filter status -->
            <div class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                <Chip
                    v-for="f in filterOpsi"
                    :key="f.value"
                    :active="f.value === filterStatus"
                    size="sm"
                    @click="filterStatus = f.value"
                >
                    {{ f.label }}
                </Chip>
            </div>

            <!-- Daftar -->
            <SectionCard v-if="terfilter.length" flush>
                <div class="divide-y divide-slate-100">
                    <ListRow
                        v-for="p in terfilter"
                        :key="p.id"
                        href="/ppdb-panitia/detail"
                        :title="p.nama"
                        :subtitle="`${p.no} · ${p.program}`"
                        chevron
                    >
                        <template #leading>
                            <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-blue-50 text-sm font-bold text-blue-600">
                                {{ p.nama.charAt(0) }}
                            </span>
                        </template>
                        <template #trailing>
                            <StatusBadge :status="p.status" size="sm" />
                        </template>
                    </ListRow>
                </div>
            </SectionCard>

            <EmptyState
                v-else
                icon="inbox"
                title="Tidak ada pendaftar"
                message="Tidak ada calon santri yang cocok dengan filter atau pencarian saat ini."
            />
        </div>
    </AppShell>
</template>
