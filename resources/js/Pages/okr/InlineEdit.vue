<script setup>
// Ubah-di-tempat: klik teks/angka → jadi input, Enter/blur simpan, Esc batal.
// Inti pengurangan klik di halaman OKR — tak perlu buka modal untuk mengubah
// judul objective, keterangan, judul KR, atau realisasi manual.
//
// Komponen ini HANYA mengurus toggle baca↔ubah & mengembalikan nilai lewat
// event `save`; pemanggil yang memutuskan cara menampilkan (slot) & endpoint.
import { ref, nextTick } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    type: { type: String, default: 'text' },        // 'text' | 'number' | 'textarea'
    editable: { type: Boolean, default: true },
    inputClass: { type: String, default: '' },
});
const emit = defineEmits(['save']);

const editing = ref(false);
const draft = ref('');
const el = ref(null);

// Masuk mode ubah: salin nilai sekarang, fokuskan, seleksi (kecuali textarea).
const mulai = async () => {
    if (!props.editable) return;
    draft.value = props.modelValue ?? '';
    editing.value = true;
    await nextTick();
    el.value?.focus();
    if (props.type !== 'textarea') el.value?.select?.();
};

// Simpan hanya bila berubah — hindari request sia-sia saat cuma klik lalu blur.
const simpan = () => {
    if (!editing.value) return;
    editing.value = false;
    const v = props.type === 'number' ? Number(draft.value) : draft.value;
    if (v !== props.modelValue) emit('save', v);
};
const batal = () => { editing.value = false; };
</script>

<template>
    <!-- textarea: Enter = baris baru, simpan saat blur -->
    <textarea v-if="editing && type === 'textarea'" ref="el" v-model="draft" rows="2"
              :class="inputClass" @keydown.esc.prevent="batal" @blur="simpan"></textarea>
    <!-- text/number: Enter = simpan -->
    <input v-else-if="editing" ref="el" v-model="draft" :type="type" :class="inputClass"
           @keydown.enter.prevent="simpan" @keydown.esc.prevent="batal" @blur="simpan" />
    <!-- mode baca: klik untuk ubah (bila editable) -->
    <button v-else type="button"
            :class="['text-left', editable ? 'cursor-text hover:bg-brand-50/70 rounded transition-colors' : 'cursor-default']"
            @click="mulai">
        <slot />
    </button>
</template>
