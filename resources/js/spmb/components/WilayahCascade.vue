<script setup>
// Cascade wilayah (Provinsi → Kabupaten/Kota → Kecamatan → Desa/Kelurahan).
// Opsi diambil dari endpoint /spmb/wilayah/* (read-only, by NAMA). Nilai lama
// yang tak ada di daftar tetap dipertahankan ("data lama"). Ubah satu level
// mereset level di bawahnya.
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

async function loadProvinsi() {
    provinsiList.value = await fetchNames('/spmb/wilayah/provinsi');
}
async function loadKabupaten(prov) {
    kabupatenList.value = prov ? await fetchNames(`/spmb/wilayah/kabupaten?provinsi=${encodeURIComponent(prov)}`) : [];
}
async function loadKecamatan(kab) {
    kecamatanList.value = kab ? await fetchNames(`/spmb/wilayah/kecamatan?kabupaten=${encodeURIComponent(kab)}`) : [];
}
async function loadDesa(kec) {
    desaList.value = kec ? await fetchNames(`/spmb/wilayah/desa?kecamatan=${encodeURIComponent(kec)}`) : [];
}

// Saat user mengganti level: emit nilai baru + reset anak-anaknya, lalu muat ulang.
async function onProvinsi(val) {
    emit('update:provinsi', val);
    emit('update:kabupatenKota', '');
    emit('update:kecamatan', '');
    emit('update:desaKelurahan', '');
    kecamatanList.value = [];
    desaList.value = [];
    await loadKabupaten(val);
}
async function onKabupaten(val) {
    emit('update:kabupatenKota', val);
    emit('update:kecamatan', '');
    emit('update:desaKelurahan', '');
    desaList.value = [];
    await loadKecamatan(val);
}
async function onKecamatan(val) {
    emit('update:kecamatan', val);
    emit('update:desaKelurahan', '');
    await loadDesa(val);
}

// Muat berantai untuk nilai awal (mode edit) tanpa mereset apa pun.
onMounted(async () => {
    await loadProvinsi();
    if (props.provinsi) await loadKabupaten(props.provinsi);
    if (props.kabupatenKota) await loadKecamatan(props.kabupatenKota);
    if (props.kecamatan) await loadDesa(props.kecamatan);
});

// Bila parent meng-reset nilai (mis. setelah simpan), jaga opsi tetap selaras.
watch(() => props.provinsi, (v) => { if (!v) kabupatenList.value = []; });
</script>

<template>
    <div class="grid grid-cols-1 gap-4">
        <MdField
            type="select" label="Provinsi" :required="required" :disabled="disabled"
            :model-value="provinsi" :options="toOptions(provinsiList, provinsi)"
            :error="errors.provinsi" @update:model-value="onProvinsi"
        />
        <MdField
            type="select" label="Kabupaten / Kota" :required="required" :disabled="disabled || !provinsi"
            :model-value="kabupatenKota" :options="toOptions(kabupatenList, kabupatenKota)"
            :error="errors.kabupaten_kota" @update:model-value="onKabupaten"
        />
        <MdField
            type="select" label="Kecamatan" :required="required" :disabled="disabled || !kabupatenKota"
            :model-value="kecamatan" :options="toOptions(kecamatanList, kecamatan)"
            :error="errors.kecamatan" @update:model-value="onKecamatan"
        />
        <MdField
            type="select" label="Desa / Kelurahan" :required="required" :disabled="disabled || !kecamatan"
            :model-value="desaKelurahan" :options="toOptions(desaList, desaKelurahan)"
            :error="errors.desa_kelurahan" @update:model-value="(v) => emit('update:desaKelurahan', v)"
        />
    </div>
</template>
