<script setup>
// Layout utama: sidebar + area konten (slot) + toast flash. Dipakai semua halaman ber-sidebar.
import { computed, onMounted, ref, watch } from 'vue'; // state toast + reminder global
import { Head, usePage, router } from '@inertiajs/vue3'; // Head judul tab, usePage shared props, router aksi
import Sidebar from './Sidebar.vue';              // sidebar navigasi

// Kembali ke akun owner asli saat sedang "masuk sebagai" peran lain.
const stopImpersonate = () => router.post('/impersonate/stop');

// Judul halaman (opsional) → dipakai di <Head>
defineProps({ title: { type: String, default: '' } });

const page = usePage();          // akses flash.status
const toast = ref(null);         // pesan toast aktif
const remindersOpen = ref(false);
const reminders = computed(() => page.props.workReminders || []);
const notificationSupported = typeof window !== 'undefined' && 'Notification' in window;
const notificationPermission = ref(notificationSupported ? Notification.permission : 'unsupported');

const deadlineText = (item) => {
    if (item.days_left < 0) return `Lewat ${Math.abs(item.days_left)} hari`;
    if (item.days_left === 0) return 'Deadline hari ini';
    return `${item.days_left} hari lagi`;
};

const sendSystemReminder = () => {
    if (!notificationSupported || Notification.permission !== 'granted' || !reminders.value.length) return;

    const today = new Date().toISOString().slice(0, 10);
    const key = `work-reminder:${page.props.auth?.user?.id}:${today}`;
    if (localStorage.getItem(key)) return;

    const overdue = reminders.value.filter((item) => item.days_left < 0).length;
    const body = overdue
        ? `${overdue} pekerjaan lewat deadline, ${reminders.value.length - overdue} segera jatuh tempo.`
        : `${reminders.value.length} pekerjaan akan segera jatuh tempo.`;
    const notification = new Notification('Reminder pekerjaan', { body, tag: key });
    notification.onclick = () => {
        window.focus();
        window.location.href = reminders.value[0].url;
        notification.close();
    };
    localStorage.setItem(key, '1');
};

const enableNotifications = async () => {
    if (!notificationSupported) return;
    notificationPermission.value = await Notification.requestPermission();
    sendSystemReminder();
};

onMounted(sendSystemReminder);

// Pantau flash.status; tiap ada pesan baru → tampilkan toast, hilang 3 detik
watch(
    () => page.props.flash?.status,
    (status) => {
        if (status) {
            toast.value = status;                          // set pesan
            setTimeout(() => (toast.value = null), 3000);  // auto-hide
        }
    },
    { immediate: true }, // jalankan sekali saat mount (flash dari redirect awal)
);
</script>

<template>
    <!-- Judul tab per halaman -->
    <Head v-if="title" :title="`${title} — System AI Preneur`" />

    <!-- Sidebar tetap di kiri -->
    <Sidebar />

    <!-- Bilah "sedang menyamar": muncul saat owner masuk sebagai peran lain.
         Amber mencolok supaya tak lupa ini bukan akun sendiri. -->
    <div v-if="page.props.impersonating"
         class="fixed top-14 md:top-0 right-0 left-0 md:left-56 z-30 bg-amber-500 text-amber-950 text-sm px-4 py-2 flex items-center justify-between shadow">
        <span>Kamu sedang masuk sebagai <b>{{ page.props.auth?.user?.name }}</b> ({{ page.props.auth?.user?.role }}) — hanya untuk melihat aksesnya.</span>
        <button type="button" @click="stopImpersonate"
                class="bg-amber-950 text-amber-50 font-semibold px-3 py-1 rounded-lg hover:bg-amber-900 whitespace-nowrap">
            Kembali ke akun saya
        </button>
    </div>

    <!-- Konten digeser 56 (lebar sidebar) di md+. Di mobile beri jarak atas utk
         bilah hamburger (h-14); bila menyamar aktif, tambah tinggi bilah amber. -->
    <div class="md:ml-56" :class="page.props.impersonating ? 'pt-24 md:pt-10' : 'pt-14 md:pt-0'">
        <slot />
    </div>

    <!-- Reminder global: tetap dapat dibuka dari halaman mana pun. -->
    <div v-if="reminders.length" class="fixed top-16 md:top-4 right-4 z-40">
        <button type="button" @click="remindersOpen = !remindersOpen"
                class="relative w-11 h-11 rounded-full bg-white border border-amber-200 text-amber-700 shadow-lg flex items-center justify-center hover:bg-amber-50"
                title="Reminder pekerjaan">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 00-4-5.7V5a2 2 0 10-4 0v.3A6 6 0 006 11v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0a3 3 0 01-6 0" /></svg>
            <span class="absolute -top-1 -right-1 min-w-5 h-5 px-1 rounded-full bg-red-600 text-white text-[10px] font-bold flex items-center justify-center">{{ reminders.length }}</span>
        </button>

        <div v-if="remindersOpen" class="mt-2 w-80 max-w-[calc(100vw-2rem)] bg-white border border-slate-200 rounded-2xl shadow-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100">
                <p class="font-bold text-sm text-slate-700">Pekerjaan perlu dikejar</p>
                <button v-if="notificationSupported && notificationPermission === 'default'" type="button" @click="enableNotifications"
                        class="mt-1 text-xs font-semibold text-brand-700 hover:underline">
                    Aktifkan notifikasi perangkat
                </button>
                <p v-else-if="notificationPermission === 'denied'" class="mt-1 text-[11px] text-slate-400">Notifikasi perangkat diblokir browser.</p>
            </div>
            <div class="max-h-80 overflow-y-auto divide-y divide-slate-100">
                <a v-for="item in reminders" :key="item.id" :href="item.url" class="block px-4 py-3 hover:bg-slate-50">
                    <p class="text-sm font-semibold text-slate-700 truncate">{{ item.title }}</p>
                    <p :class="['mt-0.5 text-xs font-medium', item.days_left < 0 ? 'text-red-600' : item.days_left === 0 ? 'text-amber-700' : 'text-slate-500']">
                        {{ deadlineText(item) }} · {{ item.deadline }}
                    </p>
                </a>
            </div>
        </div>
    </div>

    <!-- Toast flash message di kanan bawah -->
    <div v-if="toast" class="fixed bottom-5 right-5 z-50 bg-brand-700 text-white text-sm px-4 py-3 rounded-xl shadow-lg">
        {{ toast }}
    </div>
</template>
