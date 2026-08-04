<script setup>
// Layout utama: sidebar + area konten (slot) + toast flash. Dipakai semua halaman ber-sidebar.
import { computed, onMounted, ref, watch } from 'vue'; // state toast + reminder global
import { Head, usePage, router } from '@inertiajs/vue3'; // Head judul tab, usePage shared props, router aksi
import Sidebar from './Sidebar.vue'; // sidebar navigasi

// Kembali ke akun owner asli saat sedang "masuk sebagai" peran lain.
const stopImpersonate = () => router.post('/impersonate/stop');

// Judul halaman (opsional) → dipakai di <Head>
defineProps({ title: { type: String, default: '' } });

const page = usePage(); // akses flash.status
const toast = ref(null); // pesan toast aktif
const remindersOpen = ref(false);
const chatOpen = ref(false);
const hermesLoading = ref(false);
const hermesResult = ref({
    text: 'Pilih aksi di bawah untuk memicu Hermes Agent.',
    actions: [],
});
const reminders = computed(() => page.props.workReminders || []);
const serverNotifications = computed(() => page.props.serverNotifications || []);
const unreadNotifications = computed(() => Number(page.props.unreadNotificationsCount || 0));
const bellCount = computed(() => reminders.value.length + unreadNotifications.value);
const hasBellItems = computed(() => reminders.value.length > 0 || serverNotifications.value.length > 0);
const canUseHermesChat = computed(() => page.props.auth?.user?.menus?.daily_report === true);
const notificationSupported = typeof window !== 'undefined' && 'Notification' in window;
const notificationPermission = ref(notificationSupported ? Notification.permission : 'unsupported');
const csrf = () => document.querySelector('meta[name=csrf-token]')?.content || '';

const hermesActions = [
    {
        label: 'Buat OKR',
        message: 'buat okr',
        intentHint: 'Buka halaman OKR dengan preset langkah cepat.',
    },
    {
        label: 'Detail Report',
        message: 'check detail report',
        intentHint: 'Tampilkan ringkasan harian untuk hari ini.',
    },
    {
        label: 'Buka Daily Report',
        message: 'buka /daily-report',
        intentHint: 'Buka halaman laporan harian Hermes.',
    },
];

const runHermesAction = async (message) => {
    if (!canUseHermesChat.value) return;

    const normalizedMessage = message?.trim() || '';
    if (!normalizedMessage || hermesLoading.value) return;

    if (normalizedMessage === 'buka /daily-report') {
        window.location.href = '/daily-report';
        return;
    }

    hermesLoading.value = true;

    try {
        const res = await fetch('/hermes/chat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf(),
            },
            body: JSON.stringify({ message: normalizedMessage }),
        });

        const payload = await res.json().catch(() => ({ ok: false, reply: 'Tidak bisa membaca respons dari Hermes.' }));

        if (!res.ok || payload.ok === false) {
            hermesResult.value = {
                text: payload.reply || payload.message || 'Hermes belum merespons dengan benar.',
                actions: [],
            };
        } else {
            hermesResult.value = {
                text: (payload.reply || payload.message || 'Hermes merespons tanpa teks.').trim(),
                actions: Array.isArray(payload.actions) ? payload.actions : [],
            };
        }
    } catch {
        hermesResult.value = {
            text: 'Tidak bisa menghubungi Hermes sekarang, coba lagi beberapa saat lagi.',
            actions: [],
        };
    } finally {
        hermesLoading.value = false;
    }
};

const openHermesAction = (action) => {
    if (!action?.url) return;
    window.location.href = action.url;
};

const deadlineText = (item) => {
    if (item.days_left < 0) return `Lewat ${Math.abs(item.days_left)} hari`;
    if (item.days_left === 0) return 'Deadline hari ini';
    return `${item.days_left} hari lagi`;
};

const waktuNotifikasi = (value) =>
    value ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '';

const sendSystemNotifications = () => {
    if (!notificationSupported || Notification.permission !== 'granted') return;

    // Setiap penugasan server memiliki id sendiri, sehingga dapat dimunculkan
    // sekali per perangkat—tidak hilang hanya karena reminder deadline kosong.
    serverNotifications.value
        .filter((item) => !item.read_at)
        .forEach((item) => {
            const key = `server-notification:${item.id}`;
            if (localStorage.getItem(key)) return;

            const notification = new Notification(item.title, { body: item.message, tag: key });
            notification.onclick = () => {
                window.focus();
                if (item.url) window.location.href = item.url;
                notification.close();
            };
            localStorage.setItem(key, '1');
        });

    if (!reminders.value.length) return;
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
    sendSystemNotifications();
};

const bukaServerNotification = (item) => {
    router.patch(
        `/notifications/${item.id}/read`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                if (item.url) router.visit(item.url);
            },
        },
    );
};

const tandaiSemuaDibaca = () => router.patch('/notifications/read-all', {}, { preserveScroll: true });

onMounted(sendSystemNotifications);

// Layout dipertahankan oleh Inertia antarhalaman. Watch ini memastikan
// notifikasi penugasan yang baru ikut diproses tanpa menunggu reload penuh.
watch(() => serverNotifications.value.map((item) => `${item.id}:${item.read_at || ''}`).join('|'), sendSystemNotifications);

// Pantau flash.status; tiap ada pesan baru → tampilkan toast, hilang 3 detik
watch(
    () => page.props.flash?.status,
    (status) => {
        if (status) {
            toast.value = status; // set pesan
            setTimeout(() => (toast.value = null), 3000); // auto-hide
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
    <div
        v-if="page.props.impersonating"
        class="fixed top-14 md:top-0 right-0 left-0 md:left-56 z-30 bg-amber-500 text-amber-950 text-sm px-4 py-2 flex items-center justify-between shadow"
    >
        <span
            >Kamu sedang masuk sebagai <b>{{ page.props.auth?.user?.name }}</b> ({{ page.props.auth?.user?.role }}) — hanya untuk melihat
            aksesnya.</span
        >
        <button
            type="button"
            class="bg-amber-950 text-amber-50 font-semibold px-3 py-1 rounded-lg hover:bg-amber-900 whitespace-nowrap"
            @click="stopImpersonate"
        >
            Kembali ke akun saya
        </button>
    </div>

    <!-- Konten digeser 56 (lebar sidebar) di md+. Di mobile beri jarak atas utk
         bilah hamburger (h-14); bila menyamar aktif, tambah tinggi bilah amber. -->
    <div class="md:ml-56" :class="page.props.impersonating ? 'pt-24 md:pt-10' : 'pt-14 md:pt-0'">
        <slot />
    </div>

    <!-- Lonceng global: menggabungkan riwayat notifikasi server dan reminder
         deadline hasil hitung. Tetap dapat dibuka dari halaman mana pun. -->
    <div v-if="hasBellItems" class="fixed top-16 md:top-4 right-4 z-40">
        <button
            type="button"
            class="relative w-11 h-11 rounded-full bg-white border border-amber-200 text-amber-700 shadow-lg flex items-center justify-center hover:bg-amber-50"
            title="Notifikasi dan reminder"
            @click="remindersOpen = !remindersOpen"
        >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 00-4-5.7V5a2 2 0 10-4 0v.3A6 6 0 006 11v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0a3 3 0 01-6 0"
                />
            </svg>
            <span
                v-if="bellCount"
                class="absolute -top-1 -right-1 min-w-5 h-5 px-1 rounded-full bg-red-600 text-white text-[10px] font-bold flex items-center justify-center"
                >{{ bellCount }}</span
            >
        </button>

        <div
            v-if="remindersOpen"
            class="mt-2 w-80 max-w-[calc(100vw-2rem)] bg-white border border-slate-200 rounded-2xl shadow-xl overflow-hidden"
        >
            <div class="px-4 py-3 border-b border-slate-100 flex items-start justify-between gap-3">
                <div>
                    <p class="font-bold text-sm text-slate-700">Notifikasi pekerjaan</p>
                    <p class="text-[11px] text-slate-400">Tersimpan di server untuk akun ini.</p>
                    <button
                        v-if="notificationSupported && notificationPermission === 'default'"
                        type="button"
                        class="mt-1 text-xs font-semibold text-brand-700 hover:underline"
                        @click="enableNotifications"
                    >
                        Aktifkan notifikasi perangkat
                    </button>
                    <p v-else-if="notificationPermission === 'denied'" class="mt-1 text-[11px] text-slate-400">
                        Notifikasi perangkat diblokir browser.
                    </p>
                </div>
                <button
                    v-if="unreadNotifications"
                    type="button"
                    class="text-[11px] font-semibold text-brand-700 hover:underline whitespace-nowrap"
                    @click="tandaiSemuaDibaca"
                >
                    Baca semua
                </button>
            </div>

            <!-- Notifikasi penugasan persisten. Item tanpa URL (mis. target
                 omzet untuk staff) tetap dapat dibaca dan ditandai selesai. -->
            <div v-if="serverNotifications.length" class="max-h-64 overflow-y-auto divide-y divide-slate-100">
                <button
                    v-for="item in serverNotifications"
                    :key="item.id"
                    type="button"
                    :class="['w-full text-left px-4 py-3 hover:bg-slate-50', !item.read_at ? 'bg-brand-50/50' : 'bg-white']"
                    @click="bukaServerNotification(item)"
                >
                    <div class="flex items-start gap-2">
                        <span v-if="!item.read_at" class="mt-1.5 w-2 h-2 rounded-full bg-brand-600 shrink-0"></span>
                        <div :class="!item.read_at ? '' : 'ml-4'">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <p class="text-sm font-semibold text-slate-700">{{ item.title }}</p>
                                <span
                                    v-if="item.priority?.name"
                                    class="px-1.5 py-0.5 rounded bg-slate-100 text-[9px] font-bold uppercase text-slate-500"
                                >
                                    {{ item.priority.name }}
                                </span>
                            </div>
                            <p class="mt-0.5 text-xs leading-relaxed text-slate-500">{{ item.message }}</p>
                            <p class="mt-1 text-[10px] text-slate-400">{{ waktuNotifikasi(item.created_at) }}</p>
                        </div>
                    </div>
                </button>
            </div>

            <div v-if="reminders.length" class="px-4 py-3 border-y border-slate-100">
                <p class="font-bold text-sm text-slate-700">Pekerjaan perlu dikejar</p>
            </div>
            <div v-if="reminders.length" class="max-h-64 overflow-y-auto divide-y divide-slate-100">
                <a v-for="item in reminders" :key="item.id" :href="item.url" class="block px-4 py-3 hover:bg-slate-50">
                    <p class="text-sm font-semibold text-slate-700 truncate">{{ item.title }}</p>
                    <p
                        :class="[
                            'mt-0.5 text-xs font-medium',
                            item.days_left < 0 ? 'text-red-600' : item.days_left === 0 ? 'text-amber-700' : 'text-slate-500',
                        ]"
                    >
                        {{ deadlineText(item) }} · {{ item.deadline }}
                    </p>
                </a>
            </div>
        </div>
    </div>

    <!-- Toast flash message di kanan bawah -->
    <div v-if="canUseHermesChat" class="fixed right-5 bottom-5 z-60">
        <div
            v-if="chatOpen"
            class="mb-3 w-[340px] max-w-[calc(100vw-2rem)] bg-white border border-slate-200 rounded-2xl shadow-xl overflow-hidden"
        >
            <div class="px-4 py-3 border-b border-slate-100 flex items-start justify-between gap-2">
                <div>
                    <p class="font-bold text-sm text-slate-700">Hermes Assistant</p>
                    <p class="text-[11px] text-slate-400">Tanya singkat, cek report, atau minta bantuan OKR.</p>
                </div>
                <button type="button" class="text-slate-400 hover:text-slate-600" @click="chatOpen = false">✕</button>
            </div>

            <div class="max-h-[calc(100vh-20rem)] overflow-y-auto px-3 py-3 space-y-3 bg-slate-50/40">
                <div class="text-sm">
                    <p class="font-semibold text-slate-700">Aksi cepat</p>
                    <p class="text-xs text-slate-500 mb-2">Klik tombol di bawah untuk memanggil Hermes Agent.</p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="action in hermesActions"
                            :key="action.label"
                            type="button"
                            :disabled="hermesLoading"
                            class="px-2 py-1.5 text-xs font-semibold rounded-lg border border-brand-200 hover:bg-white disabled:opacity-50"
                            @click="runHermesAction(action.message)"
                        >
                            {{ action.label }}
                        </button>
                    </div>
                </div>

                <div class="text-xs border border-slate-200 rounded-xl bg-white p-3">
                    <p class="font-semibold text-slate-700 mb-2">Hasil Hermes</p>
                    <p class="text-slate-600 leading-relaxed whitespace-pre-line" :class="hermesLoading ? 'opacity-70' : ''">
                        {{ hermesLoading ? 'Menghubungi Hermes ...' : hermesResult.text }}
                    </p>
                    <div v-if="hermesResult.actions?.length" class="mt-2 flex flex-wrap gap-1.5">
                        <button
                            v-for="action in hermesResult.actions"
                            :key="action.label"
                            type="button"
                            class="px-2 py-1 text-[11px] font-semibold text-brand-700 border border-brand-200 rounded-lg hover:bg-brand-50 bg-white"
                            @click="openHermesAction(action)"
                        >
                            {{ action.label }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <button
            type="button"
            class="w-11 h-11 rounded-full bg-brand-600 text-white shadow-xl flex items-center justify-center hover:bg-brand-700"
            title="Hermes Assistant"
            @click="chatOpen = !chatOpen"
        >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M8 10h.01M12 10h.01M16 10h.01M21 8a2 2 0 01-2 2h-1l-3 3v-3H8a2 2 0 110-4h10a2 2 0 012 2zm-4 8a2 2 0 01-2 2H8l-4 3V10a2 2 0 114 0v3h7l2 2z"
                />
            </svg>
            <span v-if="hermesResult.text" class="sr-only">Hasil Hermes terakhir tersedia.</span>
        </button>
    </div>

    <div v-if="toast" class="fixed bottom-5 right-5 z-50 bg-brand-700 text-white text-sm px-4 py-3 rounded-xl shadow-lg">
        {{ toast }}
    </div>
</template>
