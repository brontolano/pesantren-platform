<script setup>
// Cascade wilayah (Provinsi → Kabupaten/Kota → Kecamatan → Desa/Kelurahan).
// Opsi diambil dari endpoint /spmb/wilayah/* (read-only, by NAMA). Daftar anak
// dimuat REAKTIF terhadap nilai induk — jadi saat nilai di-set dari luar (fitur
// "salin alamat"), dropdown ikut menyesuaikan otomatis. `scope` (mis. '_ayah')
// dipakai untuk mencocokkan key error per pihak.
import { onMounted, ref, watch } from 'vue';
import MdField from '@/spmb/components/MdField.vue';

const props = defineProps({
    provinsi: { type: String, default: '' },
    kabupatenKota: { type: String, default: '' },
    kecamatan: { type: String, default: '' },
    desaKelurahan: { type: String, default: '' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    errors: { type: Object, default: () => ({}) },
    scope: { type: String, default: '' }, // '' | '_ayah' | '_ibu' | '_wali'
});

const emit = defineEmits([
    'update:provinsi', 'update:kabupatenKota', 'update:kecamatan', 'update:desaKelurahan',
]);

const provinsiList = ref([]);
const kabupatenList = ref([]);
const kecamatanList = ref([]);
const desaList = ref([]);

async function fetchNames(url) {
    try {
        const res = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!res.ok) return [];
        const data = await res.json();
        return Array.isArray(data) ? data : [];
    } catch {
        return [];
    }
}

// Susun opsi {value,label}; pertahankan nilai lama agar tak hilang saat simpan.
function toOptions(names, current) {
    const opts = names.map((n) => ({ value: n, label: n }));
    if (current && !names.includes(current)) {
        opts.unshift({ value: current, label: `${current} (data lama)` });
    }
    return opts;
}

const err = (level) => props.errors[`${level}${props.scope}`];

// Daftar anak dimuat reaktif terhadap nilai induk (sumber: user ATAU salin alamat).
onMounted(async () => { provinsiList.value = await fetchNames('/spmb/wilayah/provinsi'); });

watch(() => props.provinsi, async (v) => {
    kabupatenList.value = v ? await fetchNames(`/spmb/wilayah/kabupaten?provinsi=${encodeURIComponent(v)}`) : [];
}, { immediate: true });

watch(() => props.kabupatenKota, async (v) => {
    kecamatanList.value = v ? await fetchNames(`/spmb/wilayah/kecamatan?kabupaten=${encodeURIComponent(v)}`) : [];
}, { immediate: true });

watch(() => props.kecamatan, async (v) => {
    desaList.value = v ? await fetchNames(`/spmb/wilayah/desa?kecamatan=${encodeURIComponent(v)}`) : [];
}, { immediate: true });

// Saat user mengganti level: emit nilai + reset anak-anaknya (watcher memuat ulang).
const onProvinsi = (val) => {
    emit('update:provinsi', val);
    emit('update:kabupatenKota', '');
    emit('update:kecamatan', '');
    emit('update:desaKelurahan', '');
};
const onKabupaten = (val) => {
    emit('update:kabupatenKota', val);
    emit('update:kecamatan', '');
    emit('update:desaKelurahan', '');
};
const onKecamatan = (val) => {
    emit('update:kecamatan', val);
    emit('update:desaKelurahan', '');
};
</script>

<template>
    <div class="grid grid-cols-1 gap-4">
        <MdField
            type="select" label="Provinsi" :required="required" :disabled="disabled"
            :model-value="provinsi" :options="toOptions(provinsiList, provinsi)"
            :error="err('provinsi')" @update:model-value="onProvinsi"
        />
        <MdField
            type="select" label="Kabupaten / Kota" :required="required" :disabled="disabled || !provinsi"
            :model-value="kabupatenKota" :options="toOptions(kabupatenList, kabupatenKota)"
            :error="err('kabupaten_kota')" @update:model-value="onKabupaten"
        />
        <MdField
            type="select" label="Kecamatan" :required="required" :disabled="disabled || !kabupatenKota"
            :model-value="kecamatan" :options="toOptions(kecamatanList, kecamatan)"
            :error="err('kecamatan')" @update:model-value="onKecamatan"
        />
        <MdField
            type="select" label="Desa / Kelurahan" :required="required" :disabled="disabled || !kecamatan"
            :model-value="desaKelurahan" :options="toOptions(desaList, desaKelurahan)"
            :error="err('desa_kelurahan')" @update:model-value="(v) => emit('update:desaKelurahan', v)"
        />
    </div>
</template>
