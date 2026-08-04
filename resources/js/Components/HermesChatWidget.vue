<script setup>
import { ref } from 'vue';

const isOpen = ref(false);
const iframeUrl = 'https://hermes.aipreneur.co.id/';
</script>

<template>
    <div class="fixed bottom-6 right-6 z-50">
        <button
            type="button"
            @click="isOpen = !isOpen"
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
            <iframe :src="iframeUrl" class="w-full flex-grow border-none" allow="microphone; clipboard-read; clipboard-write"></iframe>
        </div>
    </div>
</template>
