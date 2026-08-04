<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const isOpen = ref(false);
const isConnected = ref(false);
const isConnecting = ref(false);
const errorMessage = ref('');
const statusText = ref('');
const input = ref('');
const messages = ref([]);
const isSubmitting = ref(false);
const socket = ref(null);
const requestId = ref(1);
const sessionId = `aipreneur-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
const resolveSocketScheme = () => {
    if (typeof window === 'undefined') {
        return 'wss';
    }

    return window.location.protocol === 'https:' ? 'wss' : 'ws';
};

const normalizeSocketUrl = (rawUrl) => {
    const defaultUrl = 'wss://hermes.aipreneur.co.id/ws';
    const trimmed = (rawUrl || defaultUrl).trim();
    const scheme = resolveSocketScheme();

    if (!trimmed) {
        return defaultUrl;
    }

    if (/^wss?:\/\//i.test(trimmed)) {
        const desiredScheme = resolveSocketScheme();
        if (desiredScheme === 'wss' && trimmed.startsWith('ws://')) {
            return trimmed.replace(/^ws:\/\//i, 'wss://');
        }
        if (desiredScheme === 'ws' && trimmed.startsWith('wss://')) {
            return trimmed.replace(/^wss:\/\//i, 'ws://');
        }
        return trimmed;
    }

    if (/^https?:\/\//i.test(trimmed)) {
        return trimmed.replace(/^https:\/\//i, 'wss://').replace(/^http:\/\//i, 'ws://');
    }

    if (trimmed.startsWith('//')) {
        return `${scheme}:${trimmed}`;
    }

    if (trimmed.startsWith('/')) {
        if (typeof window === 'undefined' || !window.location?.host) {
            return `${scheme}://${trimmed}`;
        }
        return `${scheme}://${window.location.host}${trimmed}`;
    }

    return `${scheme}://${trimmed}`;
};

const socketUrl = computed(() => normalizeSocketUrl(import.meta.env.VITE_HERMES_WS_URL || 'wss://hermes.aipreneur.co.id/ws'));
const initMethod = computed(() => (import.meta.env.VITE_HERMES_WS_INIT_METHOD || 'init').trim());
const chatMethod = computed(() => (import.meta.env.VITE_HERMES_WS_CHAT_METHOD || 'chat').trim());

const chatHost = import.meta.env.VITE_HERMES_CHAT_URL || '/hermes/chat';
const messagesPanel = ref(null);

const socketUrlCandidates = computed(() => {
    const configured = socketUrl.value;
    const fallback = normalizeSocketUrl('hermes.aipreneur.co.id/ws');
    const sameHost = normalizeSocketUrl('/ws');
    const seen = new Set();
    const candidates = [configured, fallback, sameHost];
    return candidates.filter((url) => {
        const clean = (url || '').trim();
        if (!clean) {
            return false;
        }
        if (seen.has(clean)) {
            return false;
        }
        seen.add(clean);
        return true;
    });
});

const closeMessage = () => {
    isConnected.value = false;
    isConnecting.value = false;
    socket.value = null;
};

const pushMessage = (role, text) => {
    messages.value.push({
        id: `${Date.now()}-${Math.random().toString(36).slice(2, 6)}`,
        role,
        text,
        createdAt: new Date().toISOString(),
    });
    nextTick(() => {
        const el = messagesPanel.value;
        if (el) {
            el.scrollTop = el.scrollHeight;
        }
    });
};

const setTempAssistant = () => {
    const temp = { id: `temp-${Date.now()}`, role: 'assistant', text: '', pending: true };
    messages.value.push(temp);
    return temp.id;
};

const updateMessageById = (id, updater) => {
    const i = messages.value.findIndex((m) => m.id === id);
    if (i === -1) return;
    messages.value[i] = { ...messages.value[i], ...updater };
    nextTick(() => {
        const el = messagesPanel.value;
        if (el) el.scrollTop = el.scrollHeight;
    });
};

const normalizeReply = (payload) => {
    if (!payload) return '';

    if (typeof payload === 'string') {
        return payload;
    }

    if (Array.isArray(payload)) {
        return payload
            .map((item) => (typeof item === 'string' ? item : item?.text || item?.content || item?.message || ''))
            .filter(Boolean)
            .join('\n')
            .trim();
    }

    const keys = [
        payload.reply,
        payload.message,
        payload.content,
        payload.text,
        payload.output,
        payload.response,
        payload.result?.reply,
        payload.result?.message,
        payload.result?.content,
    ];

    const direct = keys.find((item) => typeof item === 'string' && item.trim());
    if (direct) return direct.trim();

    const chunks = payload.chunk || payload.delta || payload.result?.chunk || payload.result?.delta;
    if (typeof chunks === 'string' && chunks.trim()) return chunks.trim();

    return '';
};

const isJsonRpcResponse = (payload) =>
    payload &&
    typeof payload === 'object' &&
    typeof payload.jsonrpc === 'string' &&
    ('result' in payload || 'error' in payload || 'params' in payload) &&
    'id' in payload;

const handleSocketMessage = (event) => {
    let payload = null;

    try {
        payload = JSON.parse(event.data);
    } catch {
        pushMessage('assistant', event.data);
        return;
    }

    if (isJsonRpcResponse(payload)) {
        const text = normalizeReply(payload.result || payload.error);
        const target = payload.id || `${Date.now()}`;

        if (text) {
            const existing = messages.value
                .map((m, i) => ({ m, i }))
                .find((row) => row.m.role === 'assistant' && row.m.pending && row.m.id.startsWith(`pending-${target}`));

            if (existing) {
                const newText = `${messages.value[existing.i].text}${text}`;
                updateMessageById(messages.value[existing.i].id, { text: newText, pending: false });
                return;
            }
        }

        if (text) {
            pushMessage('assistant', text);
            return;
        }

        if (payload.error) {
            const msg = payload.error.message || 'Hermes mengembalikan error.';
            pushMessage('system', `Hermes error: ${msg}`);
        }

        return;
    }

    const text = normalizeReply(payload);
    if (text) {
        pushMessage('assistant', text);
        return;
    }

    if (payload.event && payload.text) {
        pushMessage('assistant', payload.text);
        return;
    }

    if (payload.type === 'ping') {
        return;
    }
};

const buildRequest = (method, params) => ({
    jsonrpc: '2.0',
    id: requestId.value++,
    method,
    params,
});

const startSession = () => {
    closeMessage();
    statusText.value = 'Menghubungkan ke Hermes…';
    isConnecting.value = true;
    errorMessage.value = '';

    const candidates = socketUrlCandidates.value;

    if (!candidates.length) {
        statusText.value = 'Endpoint WebSocket belum disetel';
        isConnecting.value = false;
        return;
    }

    const connectWithIndex = (index) => {
        const target = candidates[index];
        if (!target || !isConnecting.value) {
            return;
        }

        statusText.value = `Menghubungkan ke Hermes... (${index + 1}/${candidates.length})`;

        const ws = new WebSocket(target);
        ws.onmessage = handleSocketMessage;
        socket.value = ws;

        ws.onopen = () => {
            isConnected.value = true;
            isConnecting.value = false;
            statusText.value = `Terhubung (${target})`;
            errorMessage.value = '';

            ws.send(
                JSON.stringify(
                    buildRequest(initMethod.value, {
                        sessionId,
                        source: 'system_aipreneur',
                        userId: page.props.auth?.user?.id ?? null,
                        user: page.props.auth?.user?.name ?? null,
                        email: page.props.auth?.user?.email ?? null,
                        location: page.url || window.location.pathname || '/okr',
                        ts: new Date().toISOString(),
                    }),
                ),
            );
        };

        ws.onerror = () => {
            if (!isConnecting.value) {
                return;
            }

            statusText.value = `Gagal handshake di ${target}`;

        };

        ws.onclose = (event) => {
            if (isConnected.value) {
                isConnected.value = false;
            }

            if (!isConnecting.value) {
                statusText.value = 'Koneksi tertutup';
                socket.value = null;
                return;
            }

            if (index + 1 < candidates.length) {
                setTimeout(() => connectWithIndex(index + 1), 350);
                return;
            }

            isConnecting.value = false;
            statusText.value = `Koneksi ditutup: ${event?.reason || 'tanpa reason'}`;
            errorMessage.value = `Hermes WS tidak bisa dibuka: ${target}`;
            socket.value = null;
        };
    };

    connectWithIndex(0);
};

const closeSocket = () => {
    if (socket.value && socket.value.readyState <= 1) {
        socket.value.close();
    }
    closeMessage();
};

const fallbackToHttp = async (messageText, pendingId) => {
    try {
        const res = await fetch(chatHost, {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ message: messageText }),
        });

        const data = await res.json().catch(() => null);
        const reply = data?.reply || data?.message || 'Tidak ada balasan.';

        if (!res.ok) {
            updateMessageById(pendingId, {
                text: `Fallback HTTP gagal (${res.status}). ${reply}`,
                pending: false,
            });
            return;
        }

        updateMessageById(pendingId, {
            text: reply,
            pending: false,
            source: data?.source || 'system',
        });
    } catch (error) {
        updateMessageById(pendingId, {
            text: `Tidak bisa terhubung Hermes: ${String(error?.message || error)}`,
            pending: false,
        });
    }
};

const sendChat = async () => {
    const text = input.value.trim();
    if (!text || isSubmitting.value) return;

    isSubmitting.value = true;
    pushMessage('user', text);
    input.value = '';

    const tempId = setTempAssistant();

    const payload = {
        sessionId,
        message: text,
        ts: new Date().toISOString(),
        source: 'system_aipreneur',
    };

    if (socket.value && socket.value.readyState === WebSocket.OPEN) {
        const messageId = requestId.value;
        const rpc = buildRequest(chatMethod.value, payload);

        updateMessageById(tempId, { id: `pending-${messageId}`, pending: true });

        try {
            socket.value.send(JSON.stringify(rpc));
        } catch (error) {
            updateMessageById(tempId, {
                text: `Gagal kirim ke WebSocket: ${String(error?.message || error)}`,
                pending: false,
            });
            closeSocket();
            await fallbackToHttp(text, tempId);
        }
    } else {
        updateMessageById(tempId, { text: 'WebSocket belum siap, mencoba fallback HTTP…', pending: false });
        await fallbackToHttp(text, tempId);
    }

    isSubmitting.value = false;
};

const onToggle = () => {
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        if (!socket.value || socket.value.readyState !== WebSocket.OPEN) {
            messages.value = messages.value.length ? messages.value : [
                { id: `intro-${Date.now()}`, role: 'system', text: 'Siap terhubung ke Hermes.', createdAt: new Date().toISOString() },
            ];
            startSession();
        }
    }
};

watch(
    () => isOpen.value,
    (value) => {
        if (!value) {
            closeSocket();
        }
    },
);

onBeforeUnmount(() => {
    closeSocket();
});
</script>

<template>
    <div class="fixed bottom-6 right-6 z-50">
        <button
            type="button"
            @click="onToggle"
            class="bg-blue-600 hover:bg-blue-700 text-white rounded-full p-4 shadow-lg focus:outline-none transition transform hover:scale-105"
            aria-label="Hermes AI Assistant"
        >
            <svg v-if="!isOpen" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
                />
            </svg>
            <svg v-else class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

        <div
            v-if="isOpen"
            class="absolute bottom-16 right-0 w-96 h-[500px] bg-white rounded-lg shadow-2xl border border-gray-200 overflow-hidden flex flex-col transition-all duration-300"
        >
            <div class="bg-blue-600 text-white p-3 font-semibold flex justify-between items-center">
                <span>Hermes AI Assistant</span>
                <button type="button" @click="isOpen = false" class="text-white hover:text-gray-200">✕</button>
            </div>
            <div class="bg-slate-50 border-b border-slate-200 px-3 py-2 text-xs text-slate-500 flex justify-between items-center">
                <span>{{ statusText || 'Menyiapkan…' }}</span>
                <span>{{ isConnected ? 'Streaming aktif' : 'Tidak terkoneksi' }}</span>
            </div>

            <div ref="messagesPanel" class="flex-1 overflow-y-auto p-3 space-y-2 text-sm">
                <div
                    v-for="item in messages"
                    :key="item.id"
                    class="rounded-xl px-3 py-2 max-w-[85%]"
                    :class="[
                        item.role === 'user' ? 'ml-auto bg-brand-600 text-white' : item.role === 'system' ? 'bg-yellow-100 text-yellow-900' : 'bg-slate-100 text-slate-700',
                    ]"
                >
                    <p class="whitespace-pre-wrap">{{ item.text }}</p>
                    <p v-if="item.pending" class="text-[11px] opacity-70 mt-1">mengetik…</p>
                </div>
            </div>

            <form
                class="p-3 border-t border-slate-200"
                @submit.prevent="sendChat"
            >
                <p v-if="errorMessage" class="text-xs text-red-600 mb-2">{{ errorMessage }}</p>
                <div class="flex gap-2">
                    <input
                        v-model="input"
                        type="text"
                        maxlength="2000"
                        placeholder="Tanya tugas audi di kanban..."
                        class="flex-1 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400"
                        :disabled="isSubmitting"
                    />
                    <button
                        type="submit"
                        :disabled="!input.trim() || isSubmitting"
                        class="px-4 py-2 rounded-lg bg-brand-600 text-white text-sm font-semibold disabled:opacity-50 hover:bg-brand-700"
                    >
                        {{ isSubmitting ? 'Kirim…' : 'Kirim' }}
                    </button>
                </div>
                <p class="mt-1.5 text-[11px] text-slate-400">Koneksi: JSON-RPC via WebSocket (`/ws`) + fallback HTTP (`/hermes/chat`).</p>
            </form>
        </div>
    </div>
</template>
