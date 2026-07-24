<script setup>
// Satu kartu sparkline (small multiple) untuk lampiran tren. Skala metriknya
// sendiri — view/subscriber ratusan ribu, omset ratusan juta, tak satu sumbu.
import { statusText } from './helpers.js';

defineProps({
    spark: { type: Object, required: true },   // { metric, label, now, percent, awal, akhir, geo }
});
</script>

<template>
    <div>
        <h3 class="text-lg font-semibold text-slate-800">{{ spark.label }}</h3>
        <div class="font-sans flex items-baseline gap-2 mt-1 mb-3">
            <span class="text-[15px] font-semibold text-slate-600">{{ spark.now }}</span>
            <span class="text-[13px] font-bold tabular-nums" :class="statusText(spark.percent)">{{ spark.percent === null ? '—' : spark.percent + '%' }}</span>
        </div>
        <svg :viewBox="`0 0 ${spark.geo.W} ${spark.geo.H}`" preserveAspectRatio="none" class="w-full h-16 block overflow-visible" role="img" :aria-label="`Tren ${spark.label}`">
            <defs>
                <linearGradient :id="`spark-${spark.metric}`" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#2c4bff" stop-opacity="0.16" />
                    <stop offset="100%" stop-color="#2c4bff" stop-opacity="0" />
                </linearGradient>
            </defs>
            <path :d="spark.geo.area" :fill="`url(#spark-${spark.metric})`" />
            <path :d="spark.geo.targetPath" fill="none" stroke="#94a3b8" stroke-width="1.4" stroke-dasharray="4 4" opacity="0.75" />
            <path :d="spark.geo.actualPath" fill="none" stroke="#2c4bff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
            <circle :cx="spark.geo.lastX" :cy="spark.geo.lastY" r="3.2" fill="#2c4bff" />
        </svg>
        <div class="font-sans flex justify-between text-[11px] text-slate-400 mt-2">
            <span>{{ spark.awal }}</span><span>{{ spark.akhir }}</span>
        </div>
    </div>
</template>
